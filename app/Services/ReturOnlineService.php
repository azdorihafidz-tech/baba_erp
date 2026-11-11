<?php

namespace App\Services;

use App\Exceptions\PesananException;
use App\Models\MemberAkun;
use App\Models\PesananOnline;
use App\Models\PesananOnlineItem;
use App\Models\ReturOnline;
use App\Models\ReturOnlineItem;
use App\Models\TokoOnlinePengaturan;
use App\Models\User;
use App\Notifications\ReturOnlineNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ERP BABA Tahap 2d — retur pesanan online (rancangan §6.3). Satu-satunya tempat aturan retur.
 *
 * Pembeli mengajukan retur dari pesanan yang sudah DITERIMA (dalam batas hari Pengaturan) → penjual memutuskan:
 *   setujui (barang sudah kembali): stok pembeli − (retur_keluar), stok penjual + (gudang: StokService masuk dengan HPP asal;
 *     upline: stok_member retur_masuk), qty_retur item +, nilai_retur pesanan + (yang harus dibayar berkurang:
 *     belum lunas → "memotong tagihan", sudah lunas → dana dikembalikan transfer/tunai dicatat), RO − (Tahap 2e).
 *   tolak (alasan wajib). Pembeli boleh membatalkan selama masih diajukan.
 * Penjual = admin gudang (User, pesanan gudang) atau upline (MemberAkun, pesanan ke dirinya) — sama dengan PesananOnlineService.
 */
class ReturOnlineService
{
    public const FOLDER_FOTO = 'retur-online';
    public const MIME_FOTO = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(private StokService $stok, private StokMemberService $stokMember)
    {
    }

    private function pesananSvc(): PesananOnlineService
    {
        return app(PesananOnlineService::class);
    }

    /** Jumlah per item pesanan yang masih bisa diretur. @return array<int, int> [pesanan_online_item_id => qty] */
    public function bisaRetur(PesananOnline $p): array
    {
        $p->loadMissing('item');
        $hasil = $p->item->mapWithKeys(fn ($i) => [$i->id => 0])->all();
        if ($p->status !== 'diterima' || $this->lewatBatas($p)) {
            return $hasil;
        }
        $tertunda = $this->tertunda($p->item->pluck('id')->all());
        foreach ($p->item as $i) {
            $hasil[$i->id] = max(0, $i->qty - $i->qty_retur - ($tertunda[$i->id] ?? 0));
        }

        return $hasil;
    }

    /** qty retur yang masih diajukan per item pesanan. */
    private function tertunda(array $itemIds): array
    {
        return ReturOnlineItem::query()->join('retur_online as r', 'r.id', '=', 'retur_online_item.retur_online_id')
            ->where('r.status', 'diajukan')->whereIn('retur_online_item.pesanan_online_item_id', $itemIds)
            ->groupBy('retur_online_item.pesanan_online_item_id')
            ->selectRaw('retur_online_item.pesanan_online_item_id AS id, SUM(retur_online_item.qty) AS qty')
            ->pluck('qty', 'id')->map(fn ($v) => (int) $v)->all();
    }

    private function lewatBatas(PesananOnline $p): bool
    {
        $hari = TokoOnlinePengaturan::ambil()->hari_batas_retur;

        return $hari > 0 && $p->diterima_at && $p->diterima_at->lt(now()->subDays($hari));
    }

    // ===================== pembeli =====================

    /** @param array{alasan:string, item: array<int, array{id:int, qty:int}>} $d  $foto = isi file (biner) atau null */
    public function ajukan(PesananOnline $pesanan, MemberAkun $m, array $d, ?string $foto = null): ReturOnline
    {
        if ($pesanan->member_id !== $m->member_id) {
            throw new PesananException('Pesanan tidak ditemukan.');
        }
        if ($pesanan->status !== 'diterima') {
            throw new PesananException('Retur hanya bisa untuk pesanan yang sudah diterima.');
        }
        if ($this->lewatBatas($pesanan)) {
            throw new PesananException('Batas waktu retur (' . TokoOnlinePengaturan::ambil()->hari_batas_retur . ' hari setelah diterima) sudah lewat.');
        }
        $alasan = trim((string) ($d['alasan'] ?? ''));
        if (mb_strlen($alasan) < 5 || mb_strlen($alasan) > 500) {
            throw new PesananException('Tulis alasan retur (5–500 huruf), mis. botol bocor atau aroma tidak sesuai.');
        }
        $minta = [];
        foreach ((array) ($d['item'] ?? []) as $i) {
            $id = (int) ($i['id'] ?? 0);
            $qty = $i['qty'] ?? 0;
            if (! is_numeric($qty) || (int) $qty != $qty || (int) $qty < 0) {
                throw new PesananException('Jumlah retur harus angka bulat.');
            }
            if ((int) $qty > 0) {
                $minta[$id] = ($minta[$id] ?? 0) + (int) $qty;
            }
        }
        if (! $minta) {
            throw new PesananException('Pilih barang dan jumlah yang diretur.');
        }
        $path = null;
        if ($foto !== null && $foto !== '') {
            if (strlen($foto) > PesananOnlineService::MAKS_BUKTI) {
                throw new PesananException('Foto retur maksimal 4 MB.');
            }
            $ext = self::MIME_FOTO[(new \finfo(FILEINFO_MIME_TYPE))->buffer($foto)] ?? null;
            if (! $ext) {
                throw new PesananException('Foto retur harus gambar JPG/PNG/WebP.');
            }
            $path = self::FOLDER_FOTO . '/' . now()->format('Ym') . '/' . $pesanan->nomor . '-' . Str::random(10) . '.' . $ext;
            Storage::disk('local')->put($path, $foto);
        }

        try {
            return DB::transaction(function () use ($pesanan, $m, $minta, $alasan, $path) {
                $p = PesananOnline::lockForUpdate()->findOrFail($pesanan->id);
                $bisa = $this->bisaRetur($p);
                $items = $p->item->keyBy('id');
                $isi = [];
                $nilai = 0.0;
                foreach ($minta as $id => $qty) {
                    $i = $items->get($id);
                    if (! $i) {
                        throw new PesananException('Barang retur tidak ada di pesanan ini.');
                    }
                    if ($qty > ($bisa[$id] ?? 0)) {
                        throw new PesananException("Jumlah retur {$i->nama} maksimal " . ($bisa[$id] ?? 0) . '.');
                    }
                    // barang harus masih ada di stok pembeli (belum dijual ke downline)
                    $punya = $this->stokMember->qty($m->member_id, $i->item_id) - $this->tertundaMember($m->member_id, $i->item_id);
                    if ($punya < $qty) {
                        throw new PesananException("Stok {$i->nama} Anda tinggal " . max(0, $punya) . ' (sebagian sudah dijual / sedang diretur).');
                    }
                    $isi[] = ['pesanan_online_item_id' => $i->id, 'item_id' => $i->item_id, 'nama' => $i->nama, 'qty' => $qty, 'harga' => $i->harga, 'subtotal' => $qty * (float) $i->harga];
                    $nilai += $qty * (float) $i->harga;
                }
                $r = ReturOnline::create([
                    'nomor' => $this->nomorBaru(), 'pesanan_online_id' => $p->id, 'member_id' => $m->member_id, 'sumber' => $p->sumber,
                    'sumber_cabang_id' => $p->sumber_cabang_id, 'sumber_member_id' => $p->sumber_member_id, 'status' => 'diajukan',
                    'alasan' => $alasan, 'foto' => $path, 'nilai' => $nilai,
                ]);
                foreach ($isi as $row) {
                    $r->item()->create($row);
                }
                $this->pesananSvc()->catat($p, $p->status, "Retur {$r->nomor} diajukan: " . collect($isi)->map(fn ($x) => $x['nama'] . ' ×' . $x['qty'])->implode(', ') . ". Alasan: {$alasan}", 'member', $m->member_id, $m->nama);
                if ($p->sumber === 'gudang') {
                    DB::afterCommit(fn () => $this->kabariStaf($r));
                }

                return $r->load('item');
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
    }

    /** qty barang (item_id) milik member yang sedang diajukan retur (dari pesanan mana pun). */
    private function tertundaMember(int $memberId, int $itemId): int
    {
        return (int) ReturOnlineItem::query()->join('retur_online as r', 'r.id', '=', 'retur_online_item.retur_online_id')
            ->where('r.status', 'diajukan')->where('r.member_id', $memberId)->where('retur_online_item.item_id', $itemId)
            ->sum('retur_online_item.qty');
    }

    public function batalPembeli(ReturOnline $retur, MemberAkun $m): ReturOnline
    {
        if ($retur->member_id !== $m->member_id) {
            throw new PesananException('Retur tidak ditemukan.');
        }

        return DB::transaction(function () use ($retur, $m) {
            $r = ReturOnline::lockForUpdate()->findOrFail($retur->id);
            if ($r->status !== 'diajukan') {
                throw new PesananException('Retur ini sudah diputuskan penjual.');
            }
            $r->update(['status' => 'batal', 'diputus_at' => now(), 'diputus_oleh_jenis' => 'member', 'diputus_oleh_id' => $m->member_id, 'diputus_oleh_nama' => $m->nama]);
            $this->pesananSvc()->catat($r->pesanan, $r->pesanan->status, "Retur {$r->nomor} dibatalkan pembeli.", 'member', $m->member_id, $m->nama);

            return $r;
        });
    }

    // ===================== penjual =====================

    private function pastikanPenjual(ReturOnline $r, User|MemberAkun $oleh): void
    {
        $ok = $oleh instanceof User ? $r->sumber === 'gudang' : ($r->sumber === 'upline' && $r->sumber_member_id === $oleh->member_id);
        if (! $ok) {
            throw new PesananException($oleh instanceof User ? 'Retur pesanan ke upline diputuskan oleh upline di aplikasi member.' : 'Retur tidak ditemukan.');
        }
    }

    private function pelaku(User|MemberAkun $oleh): array
    {
        return $oleh instanceof User ? ['admin', $oleh->id, $oleh->name] : ['member', $oleh->member_id, $oleh->nama];
    }

    /** Barang sudah kembali → stok pindah, nilai memotong tagihan / dikembalikan. $pengembalian: transfer|tunai (hanya bila pesanan sudah lunas). */
    public function setujui(ReturOnline $retur, User|MemberAkun $oleh, ?string $pengembalian = null, ?string $catatan = null): ReturOnline
    {
        $this->pastikanPenjual($retur, $oleh);
        $r = DB::transaction(function () use ($retur, $oleh, $pengembalian, $catatan) {
            $p = PesananOnline::lockForUpdate()->findOrFail($retur->pesanan_online_id);
            $r = ReturOnline::lockForUpdate()->findOrFail($retur->id);
            if ($r->status !== 'diajukan') {
                throw new PesananException('Retur ini sudah diputuskan.');
            }
            $lunas = $p->status_bayar === 'lunas';
            if ($lunas && ! in_array($pengembalian, ['transfer', 'tunai'], true)) {
                throw new PesananException('Pesanan sudah lunas: pilih cara mengembalikan dana (transfer / tunai).');
            }
            foreach ($r->item()->with('pesananItem')->get() as $ri) {
                try {
                    $this->stokMember->keluar($r->member_id, $ri->item_id, $ri->qty, 'retur_keluar', 'retur_online', $r->id, "Retur {$r->nomor}");
                } catch (PesananException $e) {
                    throw new PesananException("Stok {$ri->nama} pembeli sudah tidak cukup untuk retur ini (sudah dijual). Tolak retur atau minta pembeli mengajukan ulang.");
                }
                $modal = (float) ($ri->pesananItem?->hpp ?? $ri->harga);
                if ($r->sumber === 'gudang') {
                    $this->stok->masuk($ri->item_id, (int) $r->sumber_cabang_id, $ri->qty, "Retur online {$r->nomor}", 'retur_online', $r->id, $modal);
                } else {
                    $this->stokMember->masuk((int) $r->sumber_member_id, $ri->item_id, $ri->qty, $modal, 'retur_masuk', 'retur_online', $r->id, "Retur {$r->nomor}");
                }
                PesananOnlineItem::whereKey($ri->pesanan_online_item_id)->increment('qty_retur', $ri->qty);
            }
            [$jenis, $id, $nama] = $this->pelaku($oleh);
            $r->update(['status' => 'disetujui', 'pengembalian' => $lunas ? $pengembalian : 'potong_tagihan',
                'catatan_penjual' => $catatan ? mb_substr(trim($catatan), 0, 255) : null,
                'diputus_at' => now(), 'diputus_oleh_jenis' => $jenis, 'diputus_oleh_id' => $id, 'diputus_oleh_nama' => $nama]);
            $p->nilai_retur = (float) $p->nilai_retur + (float) $r->nilai;
            $teks = "Retur {$r->nomor} disetujui (Rp " . number_format((float) $r->nilai, 0, ',', '.') . ', ' . ReturOnline::PENGEMBALIAN[$r->pengembalian] . ').';
            // belum lunas & seluruh sisa terpotong retur → otomatis lunas
            if (! $lunas && $p->sisa_bayar <= 0 && $p->status_bayar === 'belum') {
                $p->status_bayar = 'lunas';
                $p->lunas_at = now();
                $teks .= ' Sisa pembayaran 0 — lunas.';
            }
            $p->save();
            $this->pesananSvc()->catat($p, $p->status, $teks, $jenis, $id, $nama);
            // Tahap 2e: −RO (bila +RO pesanan sudah tercatat); pesanan jadi lunas karena retur → +RO bersih sesuai mode
            app(RoService::class)->retur($r);
            if ($p->status_bayar === 'lunas' && ! $lunas) {
                app(RoService::class)->pemicu($p, 'lunas');
            }

            return $r;
        });
        if ($oleh instanceof User) {
            $this->pesananSvc()->kabariMember($r->pesanan, "Retur {$r->nomor} disetujui", 'Nilai Rp ' . number_format((float) $r->nilai, 0, ',', '.') . ' — ' . ReturOnline::PENGEMBALIAN[$r->pengembalian] . '.');
        }

        return $r;
    }

    public function tolak(ReturOnline $retur, User|MemberAkun $oleh, string $alasan): ReturOnline
    {
        $this->pastikanPenjual($retur, $oleh);
        $alasan = trim($alasan);
        if (mb_strlen($alasan) < 3) {
            throw new PesananException('Tulis alasan penolakan retur.');
        }
        $r = DB::transaction(function () use ($retur, $oleh, $alasan) {
            $r = ReturOnline::lockForUpdate()->findOrFail($retur->id);
            if ($r->status !== 'diajukan') {
                throw new PesananException('Retur ini sudah diputuskan.');
            }
            [$jenis, $id, $nama] = $this->pelaku($oleh);
            $r->update(['status' => 'ditolak', 'catatan_penjual' => mb_substr($alasan, 0, 255), 'diputus_at' => now(),
                'diputus_oleh_jenis' => $jenis, 'diputus_oleh_id' => $id, 'diputus_oleh_nama' => $nama]);
            $this->pesananSvc()->catat($r->pesanan, $r->pesanan->status, "Retur {$r->nomor} ditolak: {$alasan}", $jenis, $id, $nama);

            return $r;
        });
        if ($oleh instanceof User) {
            $this->pesananSvc()->kabariMember($r->pesanan, "Retur {$r->nomor} ditolak", $alasan);
        }

        return $r;
    }

    // ===================== tampilan =====================

    public function untukMember(ReturOnline $r, string $sudut = 'pembeli'): array
    {
        $r->loadMissing(['item', 'pesanan', 'member']);

        return [
            'nomor' => $r->nomor, 'tanggal' => $r->created_at?->toIso8601String(), 'pesanan' => $r->pesanan?->nomor,
            'status' => $r->status, 'status_label' => $r->status_label, 'sumber' => $r->sumber,
            'penjual_id' => $r->sumber === 'upline' ? $r->sumber_member_id : null,
            'alasan' => $r->alasan, 'ada_foto' => (bool) $r->foto, 'nilai' => (float) $r->nilai,
            'pengembalian' => $r->pengembalian, 'pengembalian_label' => $r->pengembalian ? ReturOnline::PENGEMBALIAN[$r->pengembalian] : null,
            'catatan_penjual' => $r->catatan_penjual, 'diputus_at' => $r->diputus_at?->toIso8601String(),
            'item' => $r->item->map(fn ($i) => ['nama' => $i->nama, 'qty' => $i->qty, 'harga' => (float) $i->harga, 'subtotal' => (float) $i->subtotal])->values(),
            'pembeli' => $sudut === 'penjual' ? ['id' => $r->member_id, 'nama' => $r->member?->nama, 'no_id' => $r->member?->no_id] : null,
            'pesanan_lunas' => $r->pesanan?->status_bayar === 'lunas',
            'boleh' => $sudut === 'penjual' ? ['putuskan' => $r->status === 'diajukan'] : ['batal' => $r->status === 'diajukan'],
        ];
    }

    private function nomorBaru(): string
    {
        $awal = 'RTR-' . now()->format('Ym') . '-';
        $akhir = ReturOnline::where('nomor', 'like', $awal . '%')->lockForUpdate()->orderByDesc('nomor')->value('nomor');

        return $awal . str_pad((string) ($akhir ? (int) substr($akhir, -4) + 1 : 1), 4, '0', STR_PAD_LEFT);
    }

    private function kabariStaf(ReturOnline $r): void
    {
        try {
            $staf = User::where('is_active', true)->get()->filter(fn (User $u) => $u->can('toko_online.pesanan'));
            NotificationService::send($staf, new ReturOnlineNotification($r));
        } catch (\Throwable $e) {
            Log::warning(__METHOD__ . " {$r->nomor}: " . $e->getMessage());
        }
    }
}
