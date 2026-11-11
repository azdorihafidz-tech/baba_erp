<?php

namespace App\Services;

use App\Models\PengaturanUmum;
use App\Models\PesananOnline;
use App\Models\ReturOnline;
use App\Models\RoCatatan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ERP BABA Tahap 2e — RO otomatis dari belanja online (rancangan §6.4). RO = jumlah BOTOL (barang satuan pcs).
 *
 * Mode (Pengaturan Umum, mode_ro):
 *   lunas            → +RO saat pesanan LUNAS, langsung dihitung (dokumen RO approved).
 *   lunas_disetujui  → +RO saat pesanan LUNAS, menunggu approve dokumen di aplikasi member (Regional → MVO → superadmin).
 *   disetujui        → +RO saat barang DITERIMA (tanpa menunggu lunas), menunggu approve.
 * −RO saat retur disetujui (masuk tanggal retur) dan saat pesanan dibatalkan setelah RO tercatat — hanya bila +RO pesanan itu sudah ada.
 * +RO memakai jumlah bersih (qty − qty_retur), sehingga retur sebelum RO tercatat tidak perlu −RO.
 * Pesanan ke gudang maupun ke upline sama-sama menambah RO pembeli. Penjualan kasir & refill per ml tidak.
 *
 * Catatan dibuat di dalam transaksi kejadiannya (atomik); pengiriman ke aplikasi member setelah commit,
 * gagal → dicoba ulang oleh perintah terjadwal ro:kirim. Ref unik mencegah RO tercatat dua kali.
 */
class RoService
{
    public const MODE = [
        'lunas'           => 'Saat lunas (langsung dihitung)',
        'lunas_disetujui' => 'Saat lunas + disetujui (menunggu approve dokumen)',
        'disetujui'       => 'Hanya disetujui (saat barang diterima, menunggu approve dokumen)',
    ];
    public const MAKS_PERCOBAAN = 20;

    public function __construct(private AplikasiMemberClient $member)
    {
    }

    public function mode(): string
    {
        $m = (string) (PengaturanUmum::query()->orderBy('id')->value('mode_ro') ?? 'lunas');

        return array_key_exists($m, self::MODE) ? $m : 'lunas';
    }

    private function botol(PesananOnline $p): int
    {
        return (int) $p->item()->get()->filter(fn ($i) => in_array(strtolower((string) $i->satuan), ['pcs', 'botol'], true))
            ->sum(fn ($i) => max(0, $i->qty - $i->qty_retur));
    }

    /** Kejadian pesanan: 'lunas' atau 'diterima'. Membuat +RO bila sesuai mode & belum ada. Panggil di dalam transaksi. */
    public function pemicu(PesananOnline $p, string $kejadian): ?RoCatatan
    {
        $mode = $this->mode();
        $cocok = ($kejadian === 'lunas' && in_array($mode, ['lunas', 'lunas_disetujui'], true)) || ($kejadian === 'diterima' && $mode === 'disetujui');
        if (! $cocok || $p->status === 'batal') {
            return null;
        }
        $ref = 'ERP-RO-' . $p->nomor;
        if (RoCatatan::where('ref', $ref)->exists()) {
            return null;
        }
        $qty = $this->botol($p);
        if ($qty <= 0) {
            return null;
        }

        return $this->buat($ref, $p->member_id, $qty, 'pesanan', $p->id, null, $mode);
    }

    /** Retur disetujui → −RO (hanya bila +RO pesanannya sudah tercatat). Panggil di dalam transaksi. */
    public function retur(ReturOnline $r): ?RoCatatan
    {
        $plus = RoCatatan::where('ref', 'ERP-RO-' . $r->pesanan?->nomor)->first();
        if (! $plus) {
            return null;
        }
        $qty = (int) $r->item()->get()->sum('qty');

        return $qty > 0 ? $this->buat('ERP-RO-' . $r->nomor, $r->member_id, -$qty, 'retur', $r->pesanan_online_id, $r->id, $plus->mode) : null;
    }

    /** Pesanan dibatalkan setelah +RO tercatat → −RO sebesar RO bersih yang tersisa. Panggil di dalam transaksi. */
    public function batal(PesananOnline $p): ?RoCatatan
    {
        $plus = RoCatatan::where('ref', 'ERP-RO-' . $p->nomor)->first();
        if (! $plus || RoCatatan::where('ref', 'ERP-RO-BATAL-' . $p->nomor)->exists()) {
            return null;
        }
        $sisa = (int) RoCatatan::where('pesanan_online_id', $p->id)->sum('qty');

        return $sisa > 0 ? $this->buat('ERP-RO-BATAL-' . $p->nomor, $p->member_id, -$sisa, 'batal', $p->id, null, $plus->mode) : null;
    }

    private function buat(string $ref, int $memberId, int $qty, string $jenis, ?int $pesananId, ?int $returId, string $mode): RoCatatan
    {
        $c = RoCatatan::create(['ref' => $ref, 'member_id' => $memberId, 'qty' => $qty, 'jenis' => $jenis, 'pesanan_online_id' => $pesananId,
            'retur_online_id' => $returId, 'tanggal' => now()->toDateString(), 'mode' => $mode, 'perlu_approve' => $mode !== 'lunas', 'status' => 'menunggu']);
        // Dipicu permintaan DARI aplikasi member (/api/member/*): jangan menelepon balik aplikasi member yang sedang menunggu
        // jawaban kita (server satu proses = macet sampai batas waktu, aksi member tampak gagal). Dikirim ro:kirim (tiap menit).
        if (! request()->is('api/member/*')) {
            DB::afterCommit(fn () => $this->kirim($c->fresh()));
        }

        return $c;
    }

    /** Kirim satu catatan ke aplikasi member (idempotent: ref sama = dokumen yang sama). Tidak melempar galat. */
    public function kirim(?RoCatatan $c): bool
    {
        if (! $c || $c->status === 'terkirim') {
            return (bool) $c;
        }
        try {
            if (! $this->member->siap()) {
                throw new \RuntimeException('Sambungan aplikasi member belum diatur.');
            }
            $ket = match ($c->jenis) {
                'retur' => 'Retur ' . ($c->retur?->nomor ?? '') . ' (belanja online)',
                'batal' => 'Batal pesanan ' . ($c->pesanan?->nomor ?? '') . ' (belanja online)',
                default => 'Belanja online ' . ($c->pesanan?->nomor ?? ''),
            };
            $id = $this->member->catatRo($c->member_id, $c->qty, $c->tanggal->toDateString(), $c->ref, $c->perlu_approve, $ket);
            $c->update(['status' => 'terkirim', 'member_doc_id' => $id, 'terkirim_at' => now(), 'pesan_galat' => null, 'percobaan' => $c->percobaan + 1]);

            return true;
        } catch (\Throwable $e) {
            Log::warning(__METHOD__ . " {$c->ref}: " . $e->getMessage());
            $c->update(['status' => 'gagal', 'percobaan' => $c->percobaan + 1, 'pesan_galat' => mb_substr($e->getMessage(), 0, 255)]);

            return false;
        }
    }

    /** Kirim ulang yang belum terkirim (perintah terjadwal ro:kirim). @return array{terkirim:int, gagal:int} */
    public function kirimTertunda(int $batas = 100): array
    {
        $hasil = ['terkirim' => 0, 'gagal' => 0];
        foreach (RoCatatan::whereIn('status', ['menunggu', 'gagal'])->where('percobaan', '<', self::MAKS_PERCOBAAN)->orderBy('id')->limit($batas)->get() as $c) {
            $this->kirim($c) ? $hasil['terkirim']++ : $hasil['gagal']++;
        }

        return $hasil;
    }
}
