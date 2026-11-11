<?php

namespace Database\Seeders;

use App\Models\KategoriTransaksi;
use App\Models\TransaksiKeuangan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Koreksi kategori 1 transaksi (id=216, "SEMUA JENIS PLASTIK" Rp460.000,
 * pembayaran PO-20260724-002) yang salah tercatat sebagai "Biaya Operasional"
 * (OPS, kode_akun_coa=6-1301, tipe=beban_operasional) — seharusnya
 * "Pembelian Bahan Baku" (PBB, kode_akun_coa=5-1101, tipe=hpp).
 *
 * Item di PO ini semuanya bertipe kemasan (plastik), stock_batches sudah
 * benar tercipta dari PurchaseOrderService::terima() — jadi barangnya tetap
 * tercatat sebagai Persediaan. Yang salah HANYA kategori transaksi kasnya,
 * yang menyebabkan nilainya ikut ke-expense sebagai Beban Operasional Umum
 * padahal seharusnya dikecualikan dari Laba Rugi Formal (sama seperti PBB
 * lain — biaya bahan baru diakui saat terjual via order_items.hpp, bukan
 * saat dibeli). Ditemukan & diverifikasi lewat audit Neraca (gap Rp442.488,71
 * turun jadi -Rp17.511,29 setelah reklas ini disimulasikan).
 *
 * Trx ini tidak bisa diedit lewat UI biasa (form Kas & Transaksi tidak ada
 * tombol edit untuk transaksi auto dari PO, form PO juga tidak ada tombol
 * edit untuk PO berstatus diterima/final) — jalur admin lewat seeder ini
 * satu-satunya cara koreksi tanpa membuka lubang UI baru untuk edit
 * transaksi ber-referensi (yang sengaja dikunci demi integritas data).
 *
 * SENGAJA TIDAK menyentuh: struktur/status PurchaseOrder, stock_batches,
 * StockMovement — murni update 1 kolom (kategori_id) di 1 baris
 * transaksi_keuangans. Idempotent — aman dijalankan berkali-kali, di lokal
 * maupun produksi.
 */
class FixTrx216KategoriSeeder extends Seeder
{
    private const TRX_ID = 216;

    public function run(): void
    {
        $trx = TransaksiKeuangan::find(self::TRX_ID);

        if (!$trx) {
            $this->command?->warn("  [Fix Trx #216] Transaksi id=" . self::TRX_ID . " tidak ditemukan — dilewati.");
            Log::warning('FixTrx216KategoriSeeder: transaksi id=' . self::TRX_ID . ' tidak ditemukan.');
            return;
        }

        $kategoriOps = KategoriTransaksi::where('kode', 'OPS')->first();
        $kategoriPbb = KategoriTransaksi::where('kode', 'PBB')->first();

        if (!$kategoriOps || !$kategoriPbb) {
            $this->command?->warn('  [Fix Trx #216] Kategori OPS/PBB tidak ditemukan — dilewati (cek data kategori_transaksis).');
            Log::warning('FixTrx216KategoriSeeder: kategori OPS atau PBB tidak ditemukan di kategori_transaksis.');
            return;
        }

        // Idempotency guard: sudah PBB → skip aman (bisa dijalankan berkali-kali)
        if ((int) $trx->kategori_id === (int) $kategoriPbb->id) {
            $this->command?->info('  [Fix Trx #216] Sudah kategori PBB — tidak ada aksi (idempotent).');
            return;
        }

        // Guard defensif: kalau kategori_id-nya BUKAN OPS (mis. sudah pernah
        // diubah manual ke kategori lain oleh Owner via cara lain), jangan
        // timpa diam-diam — laporkan & berhenti supaya tidak mengganggu
        // koreksi manual yang mungkin sudah dilakukan.
        if ((int) $trx->kategori_id !== (int) $kategoriOps->id) {
            $this->command?->warn(
                '  [Fix Trx #216] kategori_id saat ini (' . $trx->kategori_id . ') BUKAN OPS maupun PBB — ' .
                'dilewati demi keamanan (kemungkinan sudah dikoreksi manual). Cek manual kalau perlu.'
            );
            Log::warning('FixTrx216KategoriSeeder: kategori_id tidak sesuai ekspektasi (bukan OPS/PBB): ' . $trx->kategori_id);
            return;
        }

        DB::transaction(function () use ($trx, $kategoriPbb) {
            $kategoriLama = $trx->kategori_id;

            // Eloquent update() (bukan raw DB::table) — supaya trait
            // HasAuditLog (logOnlyDirty) di model TransaksiKeuangan otomatis
            // mencatat perubahan ini ke activity log (KeuanganLog).
            $trx->update(['kategori_id' => $kategoriPbb->id]);

            // Activity log eksplisit TAMBAHAN di atas auto-log HasAuditLog —
            // konteks lebih jelas kenapa koreksi ini dilakukan (pola sama
            // seperti StokService::resetStok()).
            activity('Keuangan')
                ->performedOn($trx)
                ->causedBy(auth()->user())
                ->withProperties([
                    'kategori_id_lama' => $kategoriLama,
                    'kategori_id_baru' => $kategoriPbb->id,
                    'alasan' => 'Koreksi kategori: PO-20260724-002 (pembelian kemasan plastik) salah ' .
                        'tercatat sebagai Biaya Operasional, seharusnya Pembelian Bahan Baku — ' .
                        'ditemukan via audit gap Neraca.',
                ])
                ->log(
                    'Koreksi kategori transaksi #' . $trx->id . ' (' . $trx->keterangan . '): ' .
                    'Biaya Operasional -> Pembelian Bahan Baku (via FixTrx216KategoriSeeder)'
                );
        });

        $pesan = '  [Fix Trx #216] Kategori dikoreksi: OPS -> PBB (Rp ' . number_format($trx->jumlah, 2, ',', '.') . ').';
        $this->command?->info($pesan);
        Log::info('FixTrx216KategoriSeeder: ' . $pesan);
    }
}
