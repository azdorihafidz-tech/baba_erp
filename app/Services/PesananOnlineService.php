<?php

namespace App\Services;

use App\Exceptions\PesananException;
use App\Models\AturanTagihan;
use App\Models\Cabang;
use App\Models\MemberAkun;
use App\Models\PesananOnline;
use App\Models\PesananOnlineBayar;
use App\Models\PesananOnlineItem;
use App\Models\PesananOnlineLog;
use App\Models\ProdukOnline;
use App\Models\Stock;
use App\Models\TokoOnlinePengaturan;
use App\Models\TokoOnlineRekening;
use App\Models\User;
use App\Notifications\PesananOnlineNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ERP BABA Tahap 2b — pesanan online member ke gudang (rancangan §6.1–6.2). Satu-satunya tempat aturan pesanan.
 *
 * Alur status:
 *   dikirim            → menunggu_ongkir ─(admin isi ongkir)→ …
 *   transfer           → menunggu_bayar ─(member unggah bukti)→ menunggu_cek ─(admin terima)→ disiapkan  (tolak → menunggu_bayar)
 *   tagihan            → disiapkan (bayar menyusul; batas nominal dari AturanTagihan)
 *   disiapkan          ─(admin kirim / siap diambil: STOK KELUAR)→ dikirim / siap_diambil ─(member Diterima / otomatis N hari)→ diterima
 *   batal: member saat menunggu ongkir/bayar; admin selama stok belum keluar; sistem bila lewat batas waktu bayar.
 * Stok yang dipesan tetapi belum keluar dihitung "dipesan" sehingga katalog & checkout tidak menjual lebih dari stok.
 * Notifikasi ke member hanya untuk tindakan admin/sistem (tindakan member sendiri tidak dikabarkan ke dirinya).
 */
class PesananOnlineService
{
    public const FOLDER_BUKTI = 'bukti-pesanan';
    public const MAKS_BUKTI = 4 * 1024 * 1024;
    public const MIME_BUKTI = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    private const DIPESAN = ['menunggu_ongkir', 'menunggu_bayar', 'menunggu_cek', 'disiapkan'];

    public function __construct(private HargaOnlineService $harga, private StokService $stok, private AplikasiMemberClient $member)
    {
    }

    // ===================== stok =====================

    /** Jumlah yang sudah dipesan tetapi stoknya belum keluar. */
    public function dipesan(int $itemId, int $cabangId): float
    {
        return (float) PesananOnlineItem::query()
            ->join('pesanan_online as p', 'p.id', '=', 'pesanan_online_item.pesanan_online_id')
            ->where('pesanan_online_item.item_id', $itemId)->where('p.sumber_cabang_id', $cabangId)
            ->whereIn('p.status', self::DIPESAN)->where('p.stok_keluar', false)
            ->sum('pesanan_online_item.qty');
    }

    public function tersedia(int $itemId, int $cabangId): float
    {
        return max(0, floor($this->stok->getStok($itemId, $cabangId) - $this->dipesan($itemId, $cabangId)));
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
            ->where('status', '!=', 'batal')->where('status_bayar', '!=', 'lunas')->sum('total');
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

    // ===================== member =====================

    /**
     * @param array{cara_ambil:string, cara_bayar:string, kirim_nama?:string, kirim_hp?:string, kirim_alamat?:string, catatan?:string,
     *              item: array<int, array{produk_online_id:int, qty:int}>} $d
     */
    public function buat(MemberAkun $m, array $d): PesananOnline
    {
        $this->pastikanBolehBelanja($m);
        $caraAmbil = $d['cara_ambil'] ?? null;
        $caraBayar = $d['cara_bayar'] ?? null;
        if (! in_array($caraAmbil, ['ambil', 'kirim'], true)) {
            throw new PesananException('Pilih cara terima barang: ambil sendiri atau dikirim.');
        }
        if (! in_array($caraBayar, ['transfer', 'tagihan'], true)) {
            throw new PesananException('Pilih cara bayar: transfer atau tagihan.');
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

        return DB::transaction(function () use ($m, $baris, $caraAmbil, $caraBayar, $kirim, $catatan) {
            $produk = ProdukOnline::with(['item', 'harga'])->whereIn('id', array_keys($baris))->get()->keyBy('id');
            $cabangId = null;
            $isi = [];
            $subtotal = 0.0;
            foreach ($baris as $id => $qty) {
                $p = $produk->get($id);
                if (! $p || ! $p->aktif || ! $p->item || ! $p->item->is_active) {
                    throw new PesananException('Ada produk yang sudah tidak dijual. Muat ulang katalog lalu coba lagi.');
                }
                $cab = (int) $p->item->cabang_id;
                if ($cabangId !== null && $cabangId !== $cab) {
                    throw new PesananException('Satu pesanan hanya bisa dari satu gudang. Pesan terpisah untuk gudang lain.');
                }
                $cabangId = $cab;
                $h = $this->harga->untuk($p, $m, HargaOnlineService::SUMBER_GUDANG);
                if (! $h) {
                    throw new PesananException("{$p->nama_tampil} belum punya harga untuk akun Anda.");
                }
                // kunci baris stok supaya dua checkout bersamaan tidak menjual stok yang sama
                Stock::where('item_id', $p->item_id)->where('lokasi_id', $cab)->lockForUpdate()->first();
                $ada = $this->tersedia($p->item_id, $cab);
                if ($ada < $qty) {
                    throw new PesananException($ada > 0 ? "Stok {$p->nama_tampil} tinggal {$ada}." : "Stok {$p->nama_tampil} sedang habis.");
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
            } elseif (! TokoOnlineRekening::where('cabang_id', $cabangId)->where('aktif', true)->exists()) {
                throw new PesananException('Gudang belum mencantumkan rekening tujuan transfer. Hubungi admin.');
            }

            $status = $caraAmbil === 'kirim' ? 'menunggu_ongkir' : ($caraBayar === 'transfer' ? 'menunggu_bayar' : 'disiapkan');
            $p = PesananOnline::create([
                'nomor' => $this->nomorBaru(), 'member_id' => $m->member_id, 'sumber' => 'gudang', 'sumber_cabang_id' => $cabangId,
                'cara_ambil' => $caraAmbil, 'kirim_nama' => $caraAmbil === 'kirim' ? $kirim['nama'] : null,
                'kirim_hp' => $caraAmbil === 'kirim' ? $kirim['hp'] : null, 'kirim_alamat' => $caraAmbil === 'kirim' ? $kirim['alamat'] : null,
                'catatan' => $catatan ?: null, 'cara_bayar' => $caraBayar, 'subtotal' => $subtotal,
                'ongkir' => $caraAmbil === 'ambil' ? 0 : null, 'total' => $subtotal, 'status' => $status, 'status_bayar' => 'belum',
                'batas_bayar_at' => $status === 'menunggu_bayar' ? $this->batasBayar() : null, 'jatuh_tempo' => $jatuhTempo,
            ]);
            foreach ($isi as $row) {
                $p->item()->create($row);
            }
            $this->catat($p, $status, 'Pesanan dibuat (' . ($caraBayar === 'tagihan' ? 'tagihan' : 'transfer') . ', ' . ($caraAmbil === 'kirim' ? 'dikirim' : 'ambil sendiri') . ').', 'member', $m->member_id, $m->nama);
            DB::afterCommit(fn () => $this->kabariStaf($p, 'baru'));

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
                $bolehTransfer = $p->cara_bayar === 'transfer' && $p->status === 'menunggu_bayar';
                $bolehTagihan = $p->cara_bayar === 'tagihan' && in_array($p->status, ['disiapkan', 'dikirim', 'siap_diambil', 'diterima'], true);
                if ((! $bolehTransfer && ! $bolehTagihan) || $p->status_bayar !== 'belum') {
                    throw new PesananException($p->status_bayar === 'menunggu_cek' ? 'Bukti sebelumnya masih dicek admin.' : 'Pesanan ini tidak sedang menunggu pembayaran.');
                }
                $b = $p->bayar()->create(['file' => $path, 'hash' => $hash, 'nominal' => $p->total, 'status' => 'menunggu']);
                $p->update(['status_bayar' => 'menunggu_cek'] + ($bolehTransfer ? ['status' => 'menunggu_cek'] : []));
                $this->catat($p, $p->status, 'Bukti transfer diunggah (Rp ' . number_format((float) $p->total, 0, ',', '.') . ').', 'member', $m->member_id, $m->nama);
                DB::afterCommit(fn () => $this->kabariStaf($p, 'bukti'));

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
                    ? 'Bukti bayar sedang dicek admin, pesanan tidak bisa dibatalkan sendiri. Hubungi admin.'
                    : 'Pesanan yang sudah diproses tidak bisa dibatalkan sendiri. Hubungi admin.');
            }
            $alasan = mb_substr(trim((string) $alasan), 0, 200) ?: 'Dibatalkan pembeli';
            $p->update(['status' => 'batal', 'batal_at' => now(), 'batal_alasan' => $alasan]);
            $this->catat($p, 'batal', $alasan, 'member', $m->member_id, $m->nama);
            DB::afterCommit(fn () => $this->kabariStaf($p, 'batal'));

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
            $p->update(['status' => 'diterima', 'diterima_at' => now()]);
            $this->catat($p, 'diterima', 'Barang diterima pembeli.', 'member', $m->member_id, $m->nama);

            return $p;
        });
    }

    // ===================== admin gudang =====================

    public function aturOngkir(PesananOnline $pesanan, float $ongkir, User $u): PesananOnline
    {
        if ($ongkir < 0 || $ongkir > 100000000) {
            throw new PesananException('Ongkir tidak valid.');
        }
        $p = DB::transaction(function () use ($pesanan, $ongkir, $u) {
            $p = $this->kunci($pesanan);
            if ($p->status !== 'menunggu_ongkir') {
                throw new PesananException('Ongkir hanya bisa diisi saat pesanan menunggu ongkir.');
            }
            $status = $p->cara_bayar === 'transfer' ? 'menunggu_bayar' : 'disiapkan';
            $p->update(['ongkir' => $ongkir, 'total' => (float) $p->subtotal + $ongkir, 'status' => $status,
                'batas_bayar_at' => $status === 'menunggu_bayar' ? $this->batasBayar() : null]);
            $this->catat($p, $status, 'Ongkir Rp ' . number_format($ongkir, 0, ',', '.') . ' diisi.', 'admin', $u->id, $u->name);

            return $p;
        });
        $this->kabariMember($p, 'Ongkir pesanan ' . $p->nomor . ' sudah diisi', $p->cara_bayar === 'transfer'
            ? 'Total Rp ' . number_format((float) $p->total, 0, ',', '.') . '. Silakan transfer lalu unggah bukti.'
            : 'Total Rp ' . number_format((float) $p->total, 0, ',', '.') . ' (tagihan). Pesanan sedang disiapkan.');

        return $p;
    }

    public function terimaBukti(PesananOnlineBayar $bayar, User $u): PesananOnline
    {
        $p = DB::transaction(function () use ($bayar, $u) {
            $p = $this->kunci($bayar->pesanan);
            $b = PesananOnlineBayar::lockForUpdate()->findOrFail($bayar->id);
            if ($b->status !== 'menunggu') {
                throw new PesananException('Bukti ini sudah dicek.');
            }
            $b->update(['status' => 'diterima', 'dicek_by' => $u->id, 'dicek_at' => now()]);
            $p->update(['status_bayar' => 'lunas', 'lunas_at' => now()] + ($p->status === 'menunggu_cek' ? ['status' => 'disiapkan', 'batas_bayar_at' => null] : []));
            $this->catat($p, $p->status, 'Pembayaran diterima (lunas).', 'admin', $u->id, $u->name);

            return $p;
        });
        $this->kabariMember($p, 'Pembayaran pesanan ' . $p->nomor . ' diterima', $p->status === 'disiapkan' ? 'Pesanan Anda sedang disiapkan gudang.' : 'Tagihan Anda sudah lunas.');

        return $p;
    }

    public function tolakBukti(PesananOnlineBayar $bayar, string $alasan, User $u): PesananOnline
    {
        $alasan = trim($alasan);
        if (mb_strlen($alasan) < 3) {
            throw new PesananException('Tulis alasan penolakan bukti.');
        }
        $p = DB::transaction(function () use ($bayar, $alasan, $u) {
            $p = $this->kunci($bayar->pesanan);
            $b = PesananOnlineBayar::lockForUpdate()->findOrFail($bayar->id);
            if ($b->status !== 'menunggu') {
                throw new PesananException('Bukti ini sudah dicek.');
            }
            $b->update(['status' => 'ditolak', 'alasan' => mb_substr($alasan, 0, 255), 'dicek_by' => $u->id, 'dicek_at' => now()]);
            $p->update(['status_bayar' => 'belum'] + ($p->status === 'menunggu_cek' ? ['status' => 'menunggu_bayar', 'batas_bayar_at' => $this->batasBayar()] : []));
            $this->catat($p, $p->status, 'Bukti transfer ditolak: ' . $alasan, 'admin', $u->id, $u->name);

            return $p;
        });
        $this->kabariMember($p, 'Bukti bayar pesanan ' . $p->nomor . ' ditolak', $alasan . '. Silakan unggah bukti yang benar.');

        return $p;
    }

    /** Barang keluar dari gudang: dikirim (kurir + resi) atau siap diambil. Stok dikurangi FIFO, HPP dicatat. */
    public function keluarkan(PesananOnline $pesanan, User $u, ?string $kurir = null, ?string $resi = null): PesananOnline
    {
        $p = DB::transaction(function () use ($pesanan, $u, $kurir, $resi) {
            $p = $this->kunci($pesanan);
            if ($p->status !== 'disiapkan' || $p->stok_keluar) {
                throw new PesananException('Hanya pesanan berstatus Disiapkan yang bisa dikirim / disiapkan untuk diambil.');
            }
            $kurir = trim((string) $kurir);
            if ($p->cara_ambil === 'kirim' && $kurir === '') {
                throw new PesananException('Isi nama kurir / ekspedisi.');
            }
            foreach ($p->item()->get() as $i) {
                try {
                    $hpp = $this->stok->keluar($i->item_id, (int) $p->sumber_cabang_id, $i->qty, 'Pesanan online ' . $p->nomor, 'pesanan_online', $p->id);
                } catch (\Exception $e) {
                    throw new PesananException($e->getMessage());
                }
                $i->update(['hpp' => $i->qty > 0 ? round($hpp / $i->qty, 2) : 0]);
            }
            $status = $p->cara_ambil === 'kirim' ? 'dikirim' : 'siap_diambil';
            $p->update(['status' => $status, 'stok_keluar' => true, 'dikirim_at' => now(),
                'kurir' => $p->cara_ambil === 'kirim' ? mb_substr($kurir, 0, 50) : null,
                'nomor_resi' => $p->cara_ambil === 'kirim' ? (mb_substr(trim((string) $resi), 0, 60) ?: null) : null]);
            $this->catat($p, $status, $status === 'dikirim' ? "Dikirim lewat {$kurir}" . ($p->nomor_resi ? ", resi {$p->nomor_resi}" : '') . '.' : 'Barang siap diambil di gudang.', 'admin', $u->id, $u->name);

            return $p;
        });
        $hari = TokoOnlinePengaturan::ambil()->hari_terima_otomatis;
        $this->kabariMember($p, $p->status === 'dikirim' ? 'Pesanan ' . $p->nomor . ' dikirim' : 'Pesanan ' . $p->nomor . ' siap diambil',
            ($p->status === 'dikirim' ? 'Kurir ' . $p->kurir . ($p->nomor_resi ? ', resi ' . $p->nomor_resi : '') . '. ' : 'Silakan ambil di ' . ($p->cabang?->nama_cabang ?? 'gudang') . '. ')
            . "Tekan Diterima setelah barang sampai (otomatis diterima setelah {$hari} hari).");

        return $p;
    }

    /** Admin: barang siap diambil sudah diserahkan ke pembeli. */
    public function sudahDiambil(PesananOnline $pesanan, User $u): PesananOnline
    {
        return DB::transaction(function () use ($pesanan, $u) {
            $p = $this->kunci($pesanan);
            if ($p->status !== 'siap_diambil') {
                throw new PesananException('Pesanan ini tidak sedang menunggu diambil.');
            }
            $p->update(['status' => 'diterima', 'diterima_at' => now()]);
            $this->catat($p, 'diterima', 'Barang sudah diambil pembeli (dicatat gudang).', 'admin', $u->id, $u->name);

            return $p;
        });
    }

    public function batalAdmin(PesananOnline $pesanan, string $alasan, User $u): PesananOnline
    {
        $alasan = trim($alasan);
        if (mb_strlen($alasan) < 3) {
            throw new PesananException('Tulis alasan pembatalan.');
        }
        $p = DB::transaction(function () use ($pesanan, $alasan, $u) {
            $p = $this->kunci($pesanan);
            if ($p->stok_keluar || ! in_array($p->status, self::DIPESAN, true)) {
                throw new PesananException('Barang sudah keluar dari gudang — pesanan tidak bisa dibatalkan (gunakan retur).');
            }
            $p->bayar()->where('status', 'menunggu')->update(['status' => 'ditolak', 'alasan' => 'Pesanan dibatalkan', 'dicek_by' => $u->id, 'dicek_at' => now()]);
            $sudahBayar = $p->status_bayar === 'lunas';
            $p->update(['status' => 'batal', 'batal_at' => now(), 'batal_alasan' => mb_substr($alasan, 0, 255), 'status_bayar' => $sudahBayar ? 'lunas' : 'belum']);
            $this->catat($p, 'batal', $alasan . ($sudahBayar ? ' — sudah dibayar: kembalikan dana ke pembeli.' : ''), 'admin', $u->id, $u->name);

            return $p;
        });
        $this->kabariMember($p, 'Pesanan ' . $p->nomor . ' dibatalkan gudang', $alasan);

        return $p;
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
                    $p->update(['status' => 'diterima', 'diterima_at' => now(), 'diterima_otomatis' => true]);
                    $this->catat($p, 'diterima', "Diterima otomatis ({$set->hari_terima_otomatis} hari setelah dikirim).", 'sistem', null, 'Sistem');

                    return $p;
                });
                if ($p) {
                    $hasil['diterima']++;
                    $this->kabariMember($p, 'Pesanan ' . $p->nomor . ' diterima otomatis', "Sudah {$set->hari_terima_otomatis} hari sejak dikirim.");
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

    public function untukMember(PesananOnline $p, bool $rinci = false): array
    {
        $p->loadMissing(['item', 'cabang']);
        $data = [
            'nomor' => $p->nomor, 'tanggal' => $p->created_at?->toIso8601String(), 'status' => $p->status, 'status_label' => $p->status_label,
            'status_bayar' => $p->status_bayar, 'status_bayar_label' => $p->status_bayar_label, 'cara_ambil' => $p->cara_ambil,
            'cara_bayar' => $p->cara_bayar, 'sumber' => $p->sumber, 'sumber_nama' => $p->cabang?->nama_cabang ?? 'Gudang',
            'subtotal' => (float) $p->subtotal, 'ongkir' => $p->ongkir === null ? null : (float) $p->ongkir, 'total' => (float) $p->total,
            'jumlah_botol' => $p->jumlah_botol, 'ringkas' => $p->item->map(fn ($i) => $i->nama . ' ×' . $i->qty)->implode(', '),
            'batas_bayar' => $p->batas_bayar_at?->toIso8601String(), 'jatuh_tempo' => $p->jatuh_tempo?->toDateString(),
        ];
        if (! $rinci) {
            return $data;
        }
        $p->loadMissing(['bayar', 'log']);
        $perluBayar = $p->status_bayar === 'belum' && (($p->cara_bayar === 'transfer' && $p->status === 'menunggu_bayar')
            || ($p->cara_bayar === 'tagihan' && in_array($p->status, ['disiapkan', 'dikirim', 'siap_diambil', 'diterima'], true)));

        return $data + [
            'item' => $p->item->map(fn ($i) => ['nama' => $i->nama, 'qty' => $i->qty, 'satuan' => $i->satuan, 'harga' => (float) $i->harga,
                'dasar_harga' => $i->dasar_harga, 'subtotal' => (float) $i->subtotal, 'qty_retur' => $i->qty_retur])->values(),
            'kirim' => $p->cara_ambil === 'kirim' ? ['nama' => $p->kirim_nama, 'hp' => $p->kirim_hp, 'alamat' => $p->kirim_alamat] : null,
            'alamat_ambil' => $p->cara_ambil === 'ambil' ? trim(($p->cabang?->nama_cabang ?? '') . ' — ' . ($p->cabang?->alamat ?? ''), ' —') : null,
            'catatan' => $p->catatan, 'kurir' => $p->kurir, 'nomor_resi' => $p->nomor_resi, 'batal_alasan' => $p->batal_alasan,
            'diterima_otomatis' => $p->diterima_otomatis,
            'rekening' => $perluBayar ? $this->rekening((int) $p->sumber_cabang_id) : [],
            'bukti' => $p->bayar->map(fn ($b) => ['tanggal' => $b->created_at?->toIso8601String(), 'nominal' => (float) $b->nominal,
                'status' => $b->status, 'status_label' => PesananOnlineBayar::STATUS[$b->status] ?? $b->status, 'alasan' => $b->alasan])->values(),
            'riwayat' => $p->log->map(fn ($l) => ['tanggal' => $l->created_at?->toIso8601String(), 'status' => $l->status,
                'status_label' => PesananOnline::STATUS[$l->status] ?? $l->status, 'catatan' => $l->catatan,
                'oleh' => $l->oleh_jenis === 'member' ? 'Anda' : ($l->oleh_jenis === 'sistem' ? 'Sistem' : 'Gudang')])->values(),
            'boleh' => [
                'unggah_bukti' => $perluBayar,
                'batal' => in_array($p->status, ['menunggu_ongkir', 'menunggu_bayar'], true),
                'terima' => in_array($p->status, ['dikirim', 'siap_diambil'], true),
            ],
        ];
    }

    /** @return array<int, array{bank:string, nomor:string, atas_nama:string}> */
    public function rekening(int $cabangId): array
    {
        return TokoOnlineRekening::where('cabang_id', $cabangId)->where('aktif', true)->orderBy('id')->get(['bank', 'nomor', 'atas_nama'])->toArray();
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

    /** Lonceng aplikasi member (tidak boleh menggagalkan aksi). */
    public function kabariMember(PesananOnline $p, string $judul, ?string $isi): void
    {
        try {
            if (! $this->member->siap()) {
                return;
            }
            $this->member->notifikasi($p->member_id, $judul, $isi, '/admin/pesanan-saya?nomor=' . $p->nomor);
        } catch (\Throwable $e) {
            Log::warning(__METHOD__ . " {$p->nomor}: " . $e->getMessage());
        }
    }

    /** Lonceng ERP untuk staf yang memproses pesanan online. */
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
