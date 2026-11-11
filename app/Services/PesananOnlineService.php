<?php

namespace App\Services;

use App\Exceptions\PesananException;
use App\Models\AturanSumber;
use App\Models\AturanTagihan;
use App\Models\MemberAkun;
use App\Models\PesananOnline;
use App\Models\PesananOnlineBayar;
use App\Models\PesananOnlineItem;
use App\Models\PesananOnlineLog;
use App\Models\ProdukOnline;
use App\Models\Stock;
use App\Models\StokMember;
use App\Models\TokoOnlinePengaturan;
use App\Models\TokoOnlineRekening;
use App\Models\User;
use App\Notifications\PesananOnlineNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ERP BABA Tahap 2b–2c — pesanan online member (rancangan §6.1–6.2). Satu-satunya tempat aturan pesanan.
 *
 * Sumber: gudang (diproses admin di ERP) atau upline (diproses upline di Toko Saya aplikasi member; stok = stok_member upline).
 * Alur status:
 *   dikirim            → menunggu_ongkir ─(penjual isi ongkir)→ …
 *   transfer           → menunggu_bayar ─(pembeli unggah bukti)→ menunggu_cek ─(penjual terima)→ disiapkan  (tolak → menunggu_bayar)
 *   tagihan (gudang) / tunai (upline) → disiapkan (bayar menyusul: tagihan dengan bukti, tunai dicatat penjual)
 *   disiapkan          ─(penjual kirim / siap diambil: STOK PENJUAL KELUAR)→ dikirim / siap_diambil ─(Diterima / otomatis)→ diterima (+STOK PEMBELI)
 *   batal: pembeli saat menunggu ongkir/bayar; penjual/admin selama stok belum keluar; sistem bila lewat batas waktu bayar.
 * Penjual (pelaku) = User admin gudang (pesanan gudang) atau MemberAkun upline (pesanan ke dirinya). Admin juga boleh membatalkan pesanan upline.
 * Stok yang dipesan tetapi belum keluar dihitung "dipesan" sehingga katalog & checkout tidak menjual lebih dari stok.
 * Notifikasi ke pembeli dari sini hanya untuk tindakan admin gudang / sistem; tindakan antar member dikabarkan aplikasi member.
 */
class PesananOnlineService
{
    public const FOLDER_BUKTI = 'bukti-pesanan';
    public const MAKS_BUKTI = 4 * 1024 * 1024;
    public const MIME_BUKTI = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    private const DIPESAN = ['menunggu_ongkir', 'menunggu_bayar', 'menunggu_cek', 'disiapkan'];
    private const PERLU_BAYAR_SETELAH = ['disiapkan', 'dikirim', 'siap_diambil', 'diterima'];

    public function __construct(private HargaOnlineService $harga, private StokService $stok, private AplikasiMemberClient $member, private StokMemberService $stokMember)
    {
    }

    // ===================== stok =====================

    /** Jumlah yang sudah dipesan ke gudang tetapi stoknya belum keluar. */
    public function dipesan(int $itemId, int $cabangId): float
    {
        return (float) $this->qDipesan($itemId)->where('p.sumber', 'gudang')->where('p.sumber_cabang_id', $cabangId)->sum('pesanan_online_item.qty');
    }

    /** Jumlah yang sudah dipesan ke seorang upline tetapi belum keluar dari stoknya. */
    public function dipesanMember(int $itemId, int $memberId): int
    {
        return (int) $this->qDipesan($itemId)->where('p.sumber', 'upline')->where('p.sumber_member_id', $memberId)->sum('pesanan_online_item.qty');
    }

    private function qDipesan(int $itemId)
    {
        return PesananOnlineItem::query()->join('pesanan_online as p', 'p.id', '=', 'pesanan_online_item.pesanan_online_id')
            ->where('pesanan_online_item.item_id', $itemId)->whereIn('p.status', self::DIPESAN)->where('p.stok_keluar', false);
    }

    public function tersedia(int $itemId, int $cabangId): float
    {
        return max(0, floor($this->stok->getStok($itemId, $cabangId) - $this->dipesan($itemId, $cabangId)));
    }

    public function tersediaUpline(int $itemId, int $uplineId): int
    {
        return max(0, $this->stokMember->qty($uplineId, $itemId) - $this->dipesanMember($itemId, $uplineId));
    }

    // ===================== sumber barang & upline =====================

    /** Aturan sumber yang berlaku: akun → jabatan → semua. Tanpa aturan = gudang saja. @return array{gudang: bool, upline: bool} */
    public function sumberBoleh(MemberAkun $m): array
    {
        $aturan = AturanSumber::where('aktif', true)->get();
        $a = $aturan->first(fn ($x) => $x->cakupan === 'akun' && $x->member_id === $m->member_id)
            ?? ($m->jabatan_id ? $aturan->first(fn ($x) => $x->cakupan === 'jabatan' && $x->jabatan_id === $m->jabatan_id) : null)
            ?? $aturan->first(fn ($x) => $x->cakupan === 'semua');

        return $a ? ['gudang' => $a->boleh_gudang, 'upline' => $a->boleh_upline] : ['gudang' => true, 'upline' => false];
    }

    /** Upline terdekat yang benar-benar akun aktif (dikirim aplikasi member di profil). */
    public function upline(MemberAkun $m): ?MemberAkun
    {
        if (! $m->upline_member_id || $m->upline_member_id === $m->member_id) {
            return null;
        }
        $u = MemberAkun::where('member_id', $m->upline_member_id)->first();

        return $u && $u->aktif && $u->jabatan_id ? $u : null;
    }

    // ===================== tagihan =====================

    /** Aturan tagihan yang berlaku: khusus akun → jabatan → semua member. */
    public function aturanTagihan(MemberAkun $m): ?AturanTagihan
    {
        $aturan = AturanTagihan::where('aktif', true)->get();

        return $aturan->first(fn ($a) => $a->cakupan === 'akun' && $a->member_id === $m->member_id)
            ?? ($m->jabatan_id ? $aturan->first(fn ($a) => $a->cakupan === 'jabatan' && $a->jabatan_id === $m->jabatan_id) : null)
            ?? $aturan->first(fn ($a) => $a->cakupan === 'semua');
    }

    /** Total tagihan member yang belum lunas (pesanan tidak batal). */
    public function tagihanTerpakai(int $memberId): float
    {
        return (float) PesananOnline::where('member_id', $memberId)->where('cara_bayar', 'tagihan')
            ->where('status', '!=', 'batal')->where('status_bayar', '!=', 'lunas')->sum(DB::raw('total - nilai_retur'));
    }

    /** @return array{batas: float, terpakai: float, sisa: float, tempo_hari: int}|null */
    public function infoTagihan(MemberAkun $m): ?array
    {
        $a = $this->aturanTagihan($m);
        if (! $a) {
            return null;
        }
        $terpakai = $this->tagihanTerpakai($m->member_id);

        return ['batas' => (float) $a->batas_nominal, 'terpakai' => $terpakai, 'sisa' => max(0, (float) $a->batas_nominal - $terpakai), 'tempo_hari' => $a->tempo_hari];
    }

    // ===================== pembeli =====================

    /**
     * @param array{sumber?:string, cara_ambil:string, cara_bayar:string, kirim_nama?:string, kirim_hp?:string, kirim_alamat?:string, catatan?:string,
     *              item: array<int, array{produk_online_id:int, qty:int}>} $d
     */
    public function buat(MemberAkun $m, array $d): PesananOnline
    {
        $this->pastikanBolehBelanja($m);
        $sumber = ($d['sumber'] ?? 'gudang') === 'upline' ? 'upline' : 'gudang';
        $boleh = $this->sumberBoleh($m);
        if (! $boleh[$sumber]) {
            throw new PesananException($sumber === 'upline' ? 'Akun Anda belum diizinkan belanja ke upline.' : 'Akun Anda belum diizinkan belanja langsung ke gudang. Belanja ke upline.');
        }
        $upline = null;
        if ($sumber === 'upline') {
            $upline = $this->upline($m);
            if (! $upline) {
                throw new PesananException('Upline Anda belum bisa menerima pesanan (tidak ada upline aktif).');
            }
        }
        $caraAmbil = $d['cara_ambil'] ?? null;
        $caraBayar = $d['cara_bayar'] ?? null;
        if (! in_array($caraAmbil, ['ambil', 'kirim'], true)) {
            throw new PesananException('Pilih cara terima barang: ambil sendiri atau dikirim.');
        }
        $bayarBoleh = $sumber === 'upline' ? ['transfer', 'tunai'] : ['transfer', 'tagihan'];
        if (! in_array($caraBayar, $bayarBoleh, true)) {
            throw new PesananException($sumber === 'upline' ? 'Pilih cara bayar: transfer atau tunai.' : 'Pilih cara bayar: transfer atau tagihan.');
        }
        $kirim = ['nama' => trim((string) ($d['kirim_nama'] ?? '')), 'hp' => trim((string) ($d['kirim_hp'] ?? '')), 'alamat' => trim((string) ($d['kirim_alamat'] ?? ''))];
        if ($caraAmbil === 'kirim' && ($kirim['nama'] === '' || $kirim['hp'] === '' || mb_strlen($kirim['alamat']) < 10)) {
            throw new PesananException('Isi nama penerima, nomor HP, dan alamat lengkap untuk pengiriman.');
        }
        if (mb_strlen($kirim['nama']) > 100 || mb_strlen($kirim['hp']) > 30 || mb_strlen($kirim['alamat']) > 1000) {
            throw new PesananException('Data pengiriman terlalu panjang.');
        }
        $catatan = trim((string) ($d['catatan'] ?? ''));
        if (mb_strlen($catatan) > 500) {
            throw new PesananException('Catatan maksimal 500 huruf.');
        }

        $baris = [];
        foreach ((array) ($d['item'] ?? []) as $i) {
            $id = (int) ($i['produk_online_id'] ?? 0);
            $qty = $i['qty'] ?? 0;
            if ($id <= 0 || ! is_numeric($qty) || (int) $qty != $qty || (int) $qty < 1 || (int) $qty > 999) {
                throw new PesananException('Jumlah barang harus angka bulat 1–999.');
            }
            $baris[$id] = ($baris[$id] ?? 0) + (int) $qty;
        }
        if (! $baris) {
            throw new PesananException('Keranjang masih kosong.');
        }

        return DB::transaction(function () use ($m, $sumber, $upline, $baris, $caraAmbil, $caraBayar, $kirim, $catatan) {
            $produk = ProdukOnline::with(['item', 'harga'])->whereIn('id', array_keys($baris))->get()->keyBy('id');
            $cabangId = null;
            $isi = [];
            $subtotal = 0.0;
            foreach ($baris as $id => $qty) {
                $p = $produk->get($id);
                if (! $p || ! $p->aktif || ! $p->item || ! $p->item->is_active) {
                    throw new PesananException('Ada produk yang sudah tidak dijual. Muat ulang katalog lalu coba lagi.');
                }
                $h = $this->harga->untuk($p, $m, $sumber);
                if (! $h) {
                    throw new PesananException("{$p->nama_tampil} belum punya harga untuk akun Anda.");
                }
                if ($sumber === 'gudang') {
                    $cab = (int) $p->item->cabang_id;
                    if ($cabangId !== null && $cabangId !== $cab) {
                        throw new PesananException('Satu pesanan hanya bisa dari satu gudang. Pesan terpisah untuk gudang lain.');
                    }
                    $cabangId = $cab;
                    // kunci baris stok supaya dua checkout bersamaan tidak menjual stok yang sama
                    Stock::where('item_id', $p->item_id)->where('lokasi_id', $cab)->lockForUpdate()->first();
                    $ada = $this->tersedia($p->item_id, $cab);
                } else {
                    StokMember::where('member_id', $upline->member_id)->where('item_id', $p->item_id)->lockForUpdate()->first();
                    $ada = $this->tersediaUpline($p->item_id, $upline->member_id);
                }
                if ($ada < $qty) {
                    throw new PesananException($ada > 0 ? "Stok {$p->nama_tampil} tinggal {$ada}." : "Stok {$p->nama_tampil} sedang habis" . ($sumber === 'upline' ? ' di upline Anda.' : '.'));
                }
                $isi[] = ['produk_online_id' => $p->id, 'item_id' => $p->item_id, 'nama' => $p->nama_tampil, 'satuan' => $p->item->satuan,
                    'qty' => $qty, 'harga' => $h['harga'], 'dasar_harga' => $h['dasar'], 'subtotal' => $h['harga'] * $qty];
                $subtotal += $h['harga'] * $qty;
            }

            $jatuhTempo = null;
            if ($caraBayar === 'tagihan') {
                $aturan = $this->aturanTagihan($m);
                if (! $aturan) {
                    throw new PesananException('Akun Anda belum diizinkan membayar dengan tagihan. Pilih transfer.');
                }
                $terpakai = $this->tagihanTerpakai($m->member_id);
                if ($terpakai + $subtotal > (float) $aturan->batas_nominal) {
                    throw new PesananException('Melewati batas tagihan. Sisa batas Anda Rp ' . number_format(max(0, (float) $aturan->batas_nominal - $terpakai), 0, ',', '.') . '. Lunasi tagihan lama atau pilih transfer.');
                }
                $jatuhTempo = now()->addDays($aturan->tempo_hari)->toDateString();
            } elseif ($caraBayar === 'transfer') {
                $adaRek = $sumber === 'gudang'
                    ? TokoOnlineRekening::where('cabang_id', $cabangId)->where('aktif', true)->exists()
                    : TokoOnlineRekening::where('member_id', $upline->member_id)->where('aktif', true)->exists();
                if (! $adaRek) {
                    throw new PesananException($sumber === 'gudang' ? 'Gudang belum mencantumkan rekening tujuan transfer. Hubungi admin.' : 'Upline Anda belum mencantumkan rekening. Pilih tunai atau hubungi upline.');
                }
            }

            $status = $caraAmbil === 'kirim' ? 'menunggu_ongkir' : ($caraBayar === 'transfer' ? 'menunggu_bayar' : 'disiapkan');
            $p = PesananOnline::create([
                'nomor' => $this->nomorBaru(), 'member_id' => $m->member_id, 'sumber' => $sumber, 'sumber_cabang_id' => $cabangId,
                'sumber_member_id' => $upline?->member_id,
                'cara_ambil' => $caraAmbil, 'kirim_nama' => $caraAmbil === 'kirim' ? $kirim['nama'] : null,
                'kirim_hp' => $caraAmbil === 'kirim' ? $kirim['hp'] : null, 'kirim_alamat' => $caraAmbil === 'kirim' ? $kirim['alamat'] : null,
                'catatan' => $catatan ?: null, 'cara_bayar' => $caraBayar, 'subtotal' => $subtotal,
                'ongkir' => $caraAmbil === 'ambil' ? 0 : null, 'total' => $subtotal, 'status' => $status, 'status_bayar' => 'belum',
                'batas_bayar_at' => $status === 'menunggu_bayar' ? $this->batasBayar() : null, 'jatuh_tempo' => $jatuhTempo,
            ]);
            foreach ($isi as $row) {
                $p->item()->create($row);
            }
            $this->catat($p, $status, 'Pesanan dibuat (' . $caraBayar . ', ' . ($caraAmbil === 'kirim' ? 'dikirim' : 'ambil sendiri') . ($sumber === 'upline' ? ', ke upline ' . $upline->nama : '') . ').', 'member', $m->member_id, $m->nama);
            if ($sumber === 'gudang') {
                DB::afterCommit(fn () => $this->kabariStaf($p, 'baru'));
            }

            return $p->load('item');
        });
    }

    public function unggahBukti(PesananOnline $pesanan, MemberAkun $m, string $biner): PesananOnlineBayar
    {
        $this->pastikanPemilik($pesanan, $m);
        if ($biner === '' || strlen($biner) > self::MAKS_BUKTI) {
            throw new PesananException('Bukti transfer kosong atau lebih dari 4 MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($biner);
        $ext = self::MIME_BUKTI[$mime] ?? null;
        if (! $ext) {
            throw new PesananException('Bukti transfer harus foto (JPG/PNG/WebP) atau PDF.');
        }
        $hash = hash('sha256', $biner);
        $sudah = PesananOnlineBayar::with('pesanan')->where('hash', $hash)->first();
        if ($sudah) {
            throw new PesananException('Bukti ini sudah pernah diunggah' . ($sudah->pesanan ? " untuk pesanan {$sudah->pesanan->nomor}" : '') . '. Unggah bukti transfer yang lain.');
        }

        $path = self::FOLDER_BUKTI . '/' . now()->format('Ym') . '/' . $pesanan->nomor . '-' . Str::random(10) . '.' . $ext;
        Storage::disk('local')->put($path, $biner);
        try {
            return DB::transaction(function () use ($pesanan, $m, $path, $hash) {
                $p = $this->kunci($pesanan);
                if (! $this->perluBayar($p) || $p->status_bayar !== 'belum') {
                    throw new PesananException($p->status_bayar === 'menunggu_cek' ? 'Bukti sebelumnya masih dicek.' : 'Pesanan ini tidak sedang menunggu pembayaran.');
                }
                $transfer = $p->cara_bayar === 'transfer';
                $b = $p->bayar()->create(['file' => $path, 'hash' => $hash, 'nominal' => $p->sisa_bayar, 'status' => 'menunggu']);
                $p->update(['status_bayar' => 'menunggu_cek'] + ($transfer ? ['status' => 'menunggu_cek'] : []));
                $this->catat($p, $p->status, 'Bukti transfer diunggah (Rp ' . number_format($p->sisa_bayar, 0, ',', '.') . ').', 'member', $m->member_id, $m->nama);
                if ($p->sumber === 'gudang') {
                    DB::afterCommit(fn () => $this->kabariStaf($p, 'bukti'));
                }

                return $b;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    public function batalMember(PesananOnline $pesanan, MemberAkun $m, ?string $alasan): PesananOnline
    {
        $this->pastikanPemilik($pesanan, $m);

        return DB::transaction(function () use ($pesanan, $m, $alasan) {
            $p = $this->kunci($pesanan);
            if (! in_array($p->status, ['menunggu_ongkir', 'menunggu_bayar'], true)) {
                throw new PesananException($p->status === 'menunggu_cek'
                    ? 'Bukti bayar sedang dicek, pesanan tidak bisa dibatalkan sendiri. Hubungi ' . ($p->sumber === 'upline' ? 'upline Anda.' : 'admin.')
                    : 'Pesanan yang sudah diproses tidak bisa dibatalkan sendiri. Hubungi ' . ($p->sumber === 'upline' ? 'upline Anda.' : 'admin.'));
            }
            $alasan = mb_substr(trim((string) $alasan), 0, 200) ?: 'Dibatalkan pembeli';
            $p->update(['status' => 'batal', 'batal_at' => now(), 'batal_alasan' => $alasan]);
            $this->catat($p, 'batal', $alasan, 'member', $m->member_id, $m->nama);
            if ($p->sumber === 'gudang') {
                DB::afterCommit(fn () => $this->kabariStaf($p, 'batal'));
            }

            return $p;
        });
    }

    public function terimaMember(PesananOnline $pesanan, MemberAkun $m): PesananOnline
    {
        $this->pastikanPemilik($pesanan, $m);

        return DB::transaction(function () use ($pesanan, $m) {
            $p = $this->kunci($pesanan);
            if (! in_array($p->status, ['dikirim', 'siap_diambil'], true)) {
                throw new PesananException($p->status === 'diterima' ? 'Pesanan ini sudah diterima.' : 'Barang belum dikirim / belum siap diambil.');
            }
            $this->selesaikan($p, 'Barang diterima pembeli.', 'member', $m->member_id, $m->nama);

            return $p;
        });
    }

    // ===================== penjual: admin gudang (User) atau upline (MemberAkun) =====================

    /** Penjual boleh memproses pesanan ini? Admin: pesanan gudang (akses gudang dicek controller). Member: pesanan ke dirinya. */
    private function pastikanPenjual(PesananOnline $p, User|MemberAkun $oleh, bool $bolehAdminUpline = false): void
    {
        $ok = $oleh instanceof User
            ? ($p->sumber === 'gudang' || $bolehAdminUpline)
            : ($p->sumber === 'upline' && $p->sumber_member_id === $oleh->member_id);
        if (! $ok) {
            throw new PesananException($oleh instanceof User ? 'Pesanan ke upline diproses oleh upline di aplikasi member.' : 'Pesanan tidak ditemukan.');
        }
    }

    /** @return array{0: string, 1: int|null, 2: string|null} */
    private function pelaku(User|MemberAkun $oleh): array
    {
        return $oleh instanceof User ? ['admin', $oleh->id, $oleh->name] : ['member', $oleh->member_id, $oleh->nama];
    }

    public function aturOngkir(PesananOnline $pesanan, float $ongkir, User|MemberAkun $oleh): PesananOnline
    {
        $this->pastikanPenjual($pesanan, $oleh);
        if ($ongkir < 0 || $ongkir > 100000000) {
            throw new PesananException('Ongkir tidak valid.');
        }
        $p = DB::transaction(function () use ($pesanan, $ongkir, $oleh) {
            $p = $this->kunci($pesanan);
            if ($p->status !== 'menunggu_ongkir') {
                throw new PesananException('Ongkir hanya bisa diisi saat pesanan menunggu ongkir.');
            }
            $status = $p->cara_bayar === 'transfer' ? 'menunggu_bayar' : 'disiapkan';
            $p->update(['ongkir' => $ongkir, 'total' => (float) $p->subtotal + $ongkir, 'status' => $status,
                'batas_bayar_at' => $status === 'menunggu_bayar' ? $this->batasBayar() : null]);
            $this->catat($p, $status, 'Ongkir Rp ' . number_format($ongkir, 0, ',', '.') . ' diisi.', ...$this->pelaku($oleh));

            return $p;
        });
        if ($oleh instanceof User) {
            $this->kabariMember($p, 'Ongkir pesanan ' . $p->nomor . ' sudah diisi', $p->cara_bayar === 'transfer'
                ? 'Total Rp ' . number_format((float) $p->total, 0, ',', '.') . '. Silakan transfer lalu unggah bukti.'
                : 'Total Rp ' . number_format((float) $p->total, 0, ',', '.') . ' (tagihan). Pesanan sedang disiapkan.');
        }

        return $p;
    }

    public function terimaBukti(PesananOnlineBayar $bayar, User|MemberAkun $oleh): PesananOnline
    {
        $this->pastikanPenjual($bayar->pesanan, $oleh);
        $p = DB::transaction(function () use ($bayar, $oleh) {
            $p = $this->kunci($bayar->pesanan);
            $b = PesananOnlineBayar::lockForUpdate()->findOrFail($bayar->id);
            if ($b->status !== 'menunggu') {
                throw new PesananException('Bukti ini sudah dicek.');
            }
            $b->update(['status' => 'diterima', 'dicek_at' => now()] + ($oleh instanceof User ? ['dicek_by' => $oleh->id] : ['dicek_member_id' => $oleh->member_id]));
            $p->update(['status_bayar' => 'lunas', 'lunas_at' => now()] + ($p->status === 'menunggu_cek' ? ['status' => 'disiapkan', 'batas_bayar_at' => null] : []));
            $this->catat($p, $p->status, 'Pembayaran diterima (lunas).', ...$this->pelaku($oleh));

            return $p;
        });
        if ($oleh instanceof User) {
            $this->kabariMember($p, 'Pembayaran pesanan ' . $p->nomor . ' diterima', $p->status === 'disiapkan' ? 'Pesanan Anda sedang disiapkan gudang.' : 'Tagihan Anda sudah lunas.');
        }

        return $p;
    }

    public function tolakBukti(PesananOnlineBayar $bayar, string $alasan, User|MemberAkun $oleh): PesananOnline
    {
        $this->pastikanPenjual($bayar->pesanan, $oleh);
        $alasan = trim($alasan);
        if (mb_strlen($alasan) < 3) {
            throw new PesananException('Tulis alasan penolakan bukti.');
        }
        $p = DB::transaction(function () use ($bayar, $alasan, $oleh) {
            $p = $this->kunci($bayar->pesanan);
            $b = PesananOnlineBayar::lockForUpdate()->findOrFail($bayar->id);
            if ($b->status !== 'menunggu') {
                throw new PesananException('Bukti ini sudah dicek.');
            }
            $b->update(['status' => 'ditolak', 'alasan' => mb_substr($alasan, 0, 255), 'dicek_at' => now()] + ($oleh instanceof User ? ['dicek_by' => $oleh->id] : ['dicek_member_id' => $oleh->member_id]));
            $p->update(['status_bayar' => 'belum'] + ($p->status === 'menunggu_cek' ? ['status' => 'menunggu_bayar', 'batas_bayar_at' => $this->batasBayar()] : []));
            $this->catat($p, $p->status, 'Bukti transfer ditolak: ' . $alasan, ...$this->pelaku($oleh));

            return $p;
        });
        if ($oleh instanceof User) {
            $this->kabariMember($p, 'Bukti bayar pesanan ' . $p->nomor . ' ditolak', $alasan . '. Silakan unggah bukti yang benar.');
        }

        return $p;
    }

    /** Upline mencatat uang tunai sudah diterima (pesanan ke upline, cara bayar tunai). */
    public function terimaTunai(PesananOnline $pesanan, MemberAkun $oleh): PesananOnline
    {
        $this->pastikanPenjual($pesanan, $oleh);

        return DB::transaction(function () use ($pesanan, $oleh) {
            $p = $this->kunci($pesanan);
            if ($p->cara_bayar !== 'tunai' || $p->status_bayar === 'lunas' || in_array($p->status, ['batal', 'menunggu_ongkir'], true)) {
                throw new PesananException($p->status_bayar === 'lunas' ? 'Pesanan ini sudah lunas.' : 'Pesanan ini tidak sedang menunggu pembayaran tunai.');
            }
            $p->update(['status_bayar' => 'lunas', 'lunas_at' => now()]);
            $this->catat($p, $p->status, 'Uang tunai diterima (lunas).', ...$this->pelaku($oleh));

            return $p;
        });
    }

    /** Barang keluar dari penjual: dikirim (kurir + resi) atau siap diambil. Stok gudang FIFO / stok upline dikurangi, HPP dicatat. */
    public function keluarkan(PesananOnline $pesanan, User|MemberAkun $oleh, ?string $kurir = null, ?string $resi = null): PesananOnline
    {
        $this->pastikanPenjual($pesanan, $oleh);
        $p = DB::transaction(function () use ($pesanan, $oleh, $kurir, $resi) {
            $p = $this->kunci($pesanan);
            if ($p->status !== 'disiapkan' || $p->stok_keluar) {
                throw new PesananException('Hanya pesanan berstatus Disiapkan yang bisa dikirim / disiapkan untuk diambil.');
            }
            $kurir = trim((string) $kurir);
            if ($p->cara_ambil === 'kirim' && $kurir === '') {
                throw new PesananException('Isi nama kurir / ekspedisi.');
            }
            foreach ($p->item()->get() as $i) {
                if ($p->sumber === 'gudang') {
                    try {
                        $hpp = $this->stok->keluar($i->item_id, (int) $p->sumber_cabang_id, $i->qty, 'Pesanan online ' . $p->nomor, 'pesanan_online', $p->id);
                    } catch (PesananException $e) {
                        throw $e;
                    } catch (\Exception $e) {
                        throw new PesananException($e->getMessage());
                    }
                    $i->update(['hpp' => $i->qty > 0 ? round($hpp / $i->qty, 2) : 0]);
                } else {
                    $rata = $this->stokMember->keluar((int) $p->sumber_member_id, $i->item_id, $i->qty, 'jual', 'pesanan_online', $p->id, 'Pesanan ' . $p->nomor);
                    $i->update(['hpp' => $rata]);
                }
            }
            $status = $p->cara_ambil === 'kirim' ? 'dikirim' : 'siap_diambil';
            $p->update(['status' => $status, 'stok_keluar' => true, 'dikirim_at' => now(),
                'kurir' => $p->cara_ambil === 'kirim' ? mb_substr($kurir, 0, 50) : null,
                'nomor_resi' => $p->cara_ambil === 'kirim' ? (mb_substr(trim((string) $resi), 0, 60) ?: null) : null]);
            $this->catat($p, $status, $status === 'dikirim' ? "Dikirim lewat {$kurir}" . ($p->nomor_resi ? ", resi {$p->nomor_resi}" : '') . '.' : 'Barang siap diambil.', ...$this->pelaku($oleh));

            return $p;
        });
        if ($oleh instanceof User) {
            $hari = TokoOnlinePengaturan::ambil()->hari_terima_otomatis;
            $this->kabariMember($p, $p->status === 'dikirim' ? 'Pesanan ' . $p->nomor . ' dikirim' : 'Pesanan ' . $p->nomor . ' siap diambil',
                ($p->status === 'dikirim' ? 'Kurir ' . $p->kurir . ($p->nomor_resi ? ', resi ' . $p->nomor_resi : '') . '. ' : 'Silakan ambil di ' . ($p->cabang?->nama_cabang ?? 'gudang') . '. ')
                . "Tekan Diterima setelah barang sampai (otomatis diterima setelah {$hari} hari).");
        }

        return $p;
    }

    /** Penjual: barang siap diambil sudah diserahkan ke pembeli. */
    public function sudahDiambil(PesananOnline $pesanan, User|MemberAkun $oleh): PesananOnline
    {
        $this->pastikanPenjual($pesanan, $oleh);

        return DB::transaction(function () use ($pesanan, $oleh) {
            $p = $this->kunci($pesanan);
            if ($p->status !== 'siap_diambil') {
                throw new PesananException('Pesanan ini tidak sedang menunggu diambil.');
            }
            $this->selesaikan($p, 'Barang sudah diambil pembeli (dicatat penjual).', ...$this->pelaku($oleh));

            return $p;
        });
    }

    /** Batal oleh penjual (admin gudang / upline). Admin juga boleh membatalkan pesanan ke upline yang macet. */
    public function batalAdmin(PesananOnline $pesanan, string $alasan, User|MemberAkun $oleh): PesananOnline
    {
        $this->pastikanPenjual($pesanan, $oleh, true);
        $alasan = trim($alasan);
        if (mb_strlen($alasan) < 3) {
            throw new PesananException('Tulis alasan pembatalan.');
        }
        $p = DB::transaction(function () use ($pesanan, $alasan, $oleh) {
            $p = $this->kunci($pesanan);
            if ($p->stok_keluar || ! in_array($p->status, self::DIPESAN, true)) {
                throw new PesananException('Barang sudah keluar — pesanan tidak bisa dibatalkan (gunakan retur).');
            }
            $p->bayar()->where('status', 'menunggu')->update(['status' => 'ditolak', 'alasan' => 'Pesanan dibatalkan', 'dicek_at' => now()]
                + ($oleh instanceof User ? ['dicek_by' => $oleh->id] : ['dicek_member_id' => $oleh->member_id]));
            $sudahBayar = $p->status_bayar === 'lunas';
            $p->update(['status' => 'batal', 'batal_at' => now(), 'batal_alasan' => mb_substr($alasan, 0, 255), 'status_bayar' => $sudahBayar ? 'lunas' : 'belum']);
            $this->catat($p, 'batal', $alasan . ($sudahBayar ? ' — sudah dibayar: kembalikan dana ke pembeli.' : ''), ...$this->pelaku($oleh));

            return $p;
        });
        if ($oleh instanceof User) {
            $this->kabariMember($p, 'Pesanan ' . $p->nomor . ' dibatalkan ' . ($p->sumber === 'gudang' ? 'gudang' : 'admin'), $alasan);
        }

        return $p;
    }

    /** Pesanan selesai (diterima): stok pembeli bertambah dengan harga belinya. Dipanggil di dalam transaksi + baris terkunci. */
    private function selesaikan(PesananOnline $p, string $catatan, string $olehJenis, ?int $olehId, ?string $olehNama, bool $otomatis = false): void
    {
        $p->update(['status' => 'diterima', 'diterima_at' => now(), 'diterima_otomatis' => $otomatis]);
        foreach ($p->item()->get() as $i) {
            $this->stokMember->masuk($p->member_id, $i->item_id, $i->qty, (float) $i->harga, 'beli', 'pesanan_online', $p->id, 'Pesanan ' . $p->nomor);
        }
        $this->catat($p, 'diterima', $catatan, $olehJenis, $olehId, $olehNama);
    }

    // ===================== sistem (terjadwal) =====================

    /** @return array{diterima: int, batal: int} */
    public function jalankanOtomatis(): array
    {
        $set = TokoOnlinePengaturan::ambil();
        $hasil = ['diterima' => 0, 'batal' => 0];
        if ($set->hari_terima_otomatis > 0) {
            foreach (PesananOnline::whereIn('status', ['dikirim', 'siap_diambil'])->where('dikirim_at', '<=', now()->subDays($set->hari_terima_otomatis))->pluck('id') as $id) {
                $p = DB::transaction(function () use ($id, $set) {
                    $p = PesananOnline::lockForUpdate()->find($id);
                    if (! $p || ! in_array($p->status, ['dikirim', 'siap_diambil'], true)) {
                        return null;
                    }
                    $this->selesaikan($p, "Diterima otomatis ({$set->hari_terima_otomatis} hari setelah dikirim).", 'sistem', null, 'Sistem', true);

                    return $p;
                });
                if ($p) {
                    $hasil['diterima']++;
                    $this->kabariMember($p, 'Pesanan ' . $p->nomor . ' diterima otomatis', "Sudah {$set->hari_terima_otomatis} hari sejak dikirim.");
                    if ($p->sumber === 'upline') {
                        $this->kabariMember($p, 'Pesanan masuk ' . $p->nomor . ' diterima otomatis', null, (int) $p->sumber_member_id, '/admin/toko-saya?nomor=' . $p->nomor);
                    }
                }
            }
        }
        if ($set->jam_batas_bayar > 0) {
            foreach (PesananOnline::where('status', 'menunggu_bayar')->whereNotNull('batas_bayar_at')->where('batas_bayar_at', '<', now())->pluck('id') as $id) {
                $p = DB::transaction(function () use ($id) {
                    $p = PesananOnline::lockForUpdate()->find($id);
                    if (! $p || $p->status !== 'menunggu_bayar' || $p->batas_bayar_at === null || $p->batas_bayar_at->isFuture()) {
                        return null;
                    }
                    $p->update(['status' => 'batal', 'batal_at' => now(), 'batal_alasan' => 'Tidak dibayar sampai batas waktu']);
                    $this->catat($p, 'batal', 'Dibatalkan otomatis: tidak dibayar sampai batas waktu.', 'sistem', null, 'Sistem');

                    return $p;
                });
                if ($p) {
                    $hasil['batal']++;
                    $this->kabariMember($p, 'Pesanan ' . $p->nomor . ' dibatalkan', 'Tidak dibayar sampai batas waktu. Silakan pesan lagi bila masih perlu.');
                }
            }
        }

        return $hasil;
    }

    // ===================== tampilan untuk aplikasi member =====================

    public function perluBayar(PesananOnline $p): bool
    {
        return $p->status_bayar === 'belum' && $p->sisa_bayar > 0 && (($p->cara_bayar === 'transfer' && $p->status === 'menunggu_bayar')
            || ($p->cara_bayar === 'tagihan' && in_array($p->status, self::PERLU_BAYAR_SETELAH, true)));
    }

    private function namaSumber(PesananOnline $p): string
    {
        if ($p->sumber === 'upline') {
            $u = MemberAkun::where('member_id', $p->sumber_member_id)->first();

            return 'Upline ' . ($u ? $u->label : '#' . $p->sumber_member_id);
        }

        return $p->cabang?->nama_cabang ?? 'Gudang';
    }

    private function dasar(PesananOnline $p): array
    {
        $p->loadMissing(['item', 'cabang']);

        return [
            'nomor' => $p->nomor, 'tanggal' => $p->created_at?->toIso8601String(), 'status' => $p->status, 'status_label' => $p->status_label,
            'status_bayar' => $p->status_bayar, 'status_bayar_label' => $p->status_bayar_label, 'cara_ambil' => $p->cara_ambil,
            'cara_bayar' => $p->cara_bayar, 'sumber' => $p->sumber, 'sumber_nama' => $this->namaSumber($p),
            'penjual_id' => $p->sumber === 'upline' ? $p->sumber_member_id : null,
            'subtotal' => (float) $p->subtotal, 'ongkir' => $p->ongkir === null ? null : (float) $p->ongkir, 'total' => (float) $p->total,
            'nilai_retur' => (float) $p->nilai_retur, 'sisa_bayar' => $p->sisa_bayar,
            'jumlah_botol' => $p->jumlah_botol, 'ringkas' => $p->item->map(fn ($i) => $i->nama . ' ×' . $i->qty)->implode(', '),
            'batas_bayar' => $p->batas_bayar_at?->toIso8601String(), 'jatuh_tempo' => $p->jatuh_tempo?->toDateString(),
            'kurir' => $p->kurir, 'nomor_resi' => $p->nomor_resi, 'batal_alasan' => $p->batal_alasan,
        ];
    }

    private function rinciBersama(PesananOnline $p, string $sudutPandang): array
    {
        $p->loadMissing(['bayar', 'log']);

        return [
            'item' => $p->item->map(fn ($i) => ['id' => $i->id, 'nama' => $i->nama, 'qty' => $i->qty, 'satuan' => $i->satuan, 'harga' => (float) $i->harga,
                'dasar_harga' => $i->dasar_harga, 'subtotal' => (float) $i->subtotal, 'qty_retur' => $i->qty_retur, 'item_id' => $i->item_id])->values(),
            'kirim' => $p->cara_ambil === 'kirim' ? ['nama' => $p->kirim_nama, 'hp' => $p->kirim_hp, 'alamat' => $p->kirim_alamat] : null,
            'catatan' => $p->catatan, 'diterima_otomatis' => $p->diterima_otomatis,
            'bukti' => $p->bayar->map(fn ($b) => ['id' => $b->id, 'tanggal' => $b->created_at?->toIso8601String(), 'nominal' => (float) $b->nominal,
                'status' => $b->status, 'status_label' => PesananOnlineBayar::STATUS[$b->status] ?? $b->status, 'alasan' => $b->alasan])->values(),
            'riwayat' => $p->log->map(fn ($l) => ['tanggal' => $l->created_at?->toIso8601String(), 'status' => $l->status,
                'status_label' => PesananOnline::STATUS[$l->status] ?? $l->status, 'catatan' => $l->catatan,
                'oleh' => $this->labelOleh($l, $p, $sudutPandang)])->values(),
        ];
    }

    private function labelOleh(PesananOnlineLog $l, PesananOnline $p, string $sudutPandang): string
    {
        if ($l->oleh_jenis === 'sistem') {
            return 'Sistem';
        }
        if ($l->oleh_jenis === 'admin') {
            return $p->sumber === 'gudang' ? 'Gudang' : 'Admin';
        }
        $pembeli = (int) $l->oleh_id === $p->member_id;
        if ($sudutPandang === 'pembeli') {
            return $pembeli ? 'Anda' : 'Upline';
        }

        return $pembeli ? 'Pembeli' : 'Anda';
    }

    public function untukMember(PesananOnline $p, bool $rinci = false): array
    {
        $data = $this->dasar($p);
        if (! $rinci) {
            return $data;
        }
        $perlu = $this->perluBayar($p);
        $bisaRetur = app(ReturOnlineService::class)->bisaRetur($p);
        $rinci = $this->rinciBersama($p, 'pembeli');
        $rinci['item'] = collect($rinci['item'])->map(fn ($i) => $i + ['bisa_retur' => $bisaRetur[$i['id']] ?? 0])->values();
        $p->loadMissing('retur');

        return $data + $rinci + [
            'retur' => $p->retur->map(fn ($r) => ['nomor' => $r->nomor, 'status' => $r->status, 'status_label' => $r->status_label, 'nilai' => (float) $r->nilai])->values(),
            'alamat_ambil' => $p->cara_ambil === 'ambil' ? ($p->sumber === 'gudang'
                ? trim(($p->cabang?->nama_cabang ?? '') . ' — ' . ($p->cabang?->alamat ?? ''), ' —')
                : 'Di upline Anda (' . $this->namaSumber($p) . ')') : null,
            'rekening' => $perlu ? ($p->sumber === 'gudang' ? $this->rekening((int) $p->sumber_cabang_id) : $this->rekeningMember((int) $p->sumber_member_id)) : [],
            'boleh' => [
                'unggah_bukti' => $perlu,
                'batal' => in_array($p->status, ['menunggu_ongkir', 'menunggu_bayar'], true),
                'terima' => in_array($p->status, ['dikirim', 'siap_diambil'], true),
                'retur' => array_sum($bisaRetur) > 0,
            ],
        ];
    }

    /** Pesanan masuk dilihat upline penjual. */
    public function untukPenjual(PesananOnline $p, bool $rinci = true): array
    {
        $p->loadMissing('member');
        $b = $p->member;
        $data = $this->dasar($p) + [
            'pembeli' => ['id' => $p->member_id, 'nama' => $b?->nama, 'no_id' => $b?->no_id, 'no_member' => $b?->no_member, 'jabatan' => $b?->jabatan],
            'perlu_tindakan' => $this->perluTindakan($p),
        ];
        if (! $rinci) {
            return $data;
        }
        $menunggu = $p->bayar()->where('status', 'menunggu')->first();

        return $data + $this->rinciBersama($p, 'penjual') + [
            'stok' => $p->item->map(fn ($i) => ['item_id' => $i->item_id, 'punya' => $this->stokMember->qty((int) $p->sumber_member_id, $i->item_id)])->values(),
            'boleh' => [
                'ongkir' => $p->status === 'menunggu_ongkir',
                'cek_bukti' => $menunggu?->id,
                'keluarkan' => $p->status === 'disiapkan' && ! $p->stok_keluar,
                'sudah_diambil' => $p->status === 'siap_diambil',
                'tunai' => $p->cara_bayar === 'tunai' && $p->status_bayar !== 'lunas' && ! in_array($p->status, ['batal', 'menunggu_ongkir'], true),
                'batal' => ! $p->stok_keluar && in_array($p->status, self::DIPESAN, true),
            ],
        ];
    }

    private function perluTindakan(PesananOnline $p): bool
    {
        return in_array($p->status, ['menunggu_ongkir', 'menunggu_cek', 'disiapkan'], true)
            || ($p->status_bayar === 'menunggu_cek' && $p->status !== 'batal');
    }

    /** Saring query pesanan: perlu tindakan penjual (isi ongkir, cek bukti, siapkan barang). */
    public function saringPerluTindakan($q)
    {
        return $q->where(fn ($w) => $w->whereIn('status', ['menunggu_ongkir', 'menunggu_cek', 'disiapkan'])
            ->orWhere(fn ($t) => $t->where('status_bayar', 'menunggu_cek')->where('status', '!=', 'batal')));
    }

    /** @return array<int, array{bank:string, nomor:string, atas_nama:string}> */
    public function rekening(int $cabangId): array
    {
        return TokoOnlineRekening::where('cabang_id', $cabangId)->where('aktif', true)->orderBy('id')->get(['bank', 'nomor', 'atas_nama'])->toArray();
    }

    public function rekeningMember(int $memberId): array
    {
        return TokoOnlineRekening::where('member_id', $memberId)->where('aktif', true)->orderBy('id')->get(['bank', 'nomor', 'atas_nama'])->toArray();
    }

    // ===================== bantuan =====================

    public function pastikanBolehBelanja(MemberAkun $m): void
    {
        if (! $m->aktif) {
            throw new PesananException('Akun belum aktif.');
        }
        if (! $m->jabatan_id) {
            throw new PesananException('Akun superadmin tidak bisa belanja. Masuk dengan akun member.');
        }
    }

    private function pastikanPemilik(PesananOnline $p, MemberAkun $m): void
    {
        if ($p->member_id !== $m->member_id) {
            throw new PesananException('Pesanan tidak ditemukan.');
        }
    }

    private function kunci(PesananOnline $p): PesananOnline
    {
        return PesananOnline::lockForUpdate()->findOrFail($p->id);
    }

    private function batasBayar()
    {
        $jam = TokoOnlinePengaturan::ambil()->jam_batas_bayar;

        return $jam > 0 ? now()->addHours($jam) : null;
    }

    private function nomorBaru(): string
    {
        $awal = 'ONL-' . now()->format('Ym') . '-';
        $akhir = PesananOnline::where('nomor', 'like', $awal . '%')->lockForUpdate()->orderByDesc('nomor')->value('nomor');

        return $awal . str_pad((string) ($akhir ? (int) substr($akhir, -4) + 1 : 1), 4, '0', STR_PAD_LEFT);
    }

    public function catat(PesananOnline $p, string $status, ?string $catatan, string $olehJenis, ?int $olehId, ?string $olehNama): void
    {
        PesananOnlineLog::create(['pesanan_online_id' => $p->id, 'status' => $status, 'catatan' => $catatan ? mb_substr($catatan, 0, 500) : null,
            'oleh_jenis' => $olehJenis, 'oleh_id' => $olehId, 'oleh_nama' => $olehNama ? mb_substr($olehNama, 0, 150) : null]);
    }

    /** Lonceng aplikasi member (tidak boleh menggagalkan aksi). Bawaan: pembelinya. */
    public function kabariMember(PesananOnline $p, string $judul, ?string $isi, ?int $kepada = null, ?string $url = null): void
    {
        try {
            if (! $this->member->siap()) {
                return;
            }
            $this->member->notifikasi($kepada ?? $p->member_id, $judul, $isi, $url ?? '/admin/pesanan-saya?nomor=' . $p->nomor);
        } catch (\Throwable $e) {
            Log::warning(__METHOD__ . " {$p->nomor}: " . $e->getMessage());
        }
    }

    /** Lonceng ERP untuk staf yang memproses pesanan online (pesanan gudang saja). */
    public function kabariStaf(PesananOnline $p, string $kejadian): void
    {
        try {
            $staf = User::where('is_active', true)->get()->filter(fn (User $u) => $u->can('toko_online.pesanan'));
            NotificationService::send($staf, new PesananOnlineNotification($p, $kejadian));
        } catch (\Throwable $e) {
            Log::warning(__METHOD__ . " {$p->nomor}: " . $e->getMessage());
        }
    }
}
