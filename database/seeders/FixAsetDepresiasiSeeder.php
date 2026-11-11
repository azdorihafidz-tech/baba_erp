<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetDepreciation;
use App\Models\TransaksiKeuangan;
use App\Services\AssetDepreciationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Data fix generic untuk aset yang kena bug sistemik lama (sebelum
 * AssetController::update() sync nilai_buku + guard periode-mundur di
 * AssetDepreciationService::generateAsetTertentu() ada) — seeder ini HANYA
 * membereskan data yang SUDAH terlanjur salah, bukan mengubah logic
 * kalkulasi. Auto-detect SEMUA aset aktif (tidak hardcode per kode aset)
 * lewat 2 pengecekan generic:
 *   1. Self-consistency: nilai_buku vs (harga_perolehan - SUM depresiasi)
 *   2. Chain-order: nilai_buku_akhir periode N harus = nilai_buku_awal N+1
 * Idempotent — aman dijalankan berkali-kali, di lokal maupun produksi,
 * setiap aset di-skip otomatis kalau sudah benar.
 */
class FixAsetDepresiasiSeeder extends Seeder
{
    public function run(): void
    {
        $this->fixChainOrder();
        $this->fixSelfConsistency();
    }

    /**
     * Deteksi & perbaiki aset dengan record depresiasi yang chain-nya
     * rusak (digenerate tidak berurutan — nilai_buku_akhir periode N tidak
     * match nilai_buku_awal periode N+1). Diperbaiki dengan hapus SEMUA
     * record depresiasi aset itu + soft-delete TransaksiKeuangan pasangannya,
     * reset nilai_buku ke harga_perolehan, lalu generate ulang BERURUTAN
     * sesuai periode aslinya — guard periode-mundur (fix sistemik
     * sebelumnya) otomatis melindungi urutan generate ulang ini.
     *
     * AssetDepreciation tidak punya kolom deleted_at (hard-delete only,
     * tidak ada trait SoftDeletes) — record lama akan hilang permanen,
     * makanya isi lengkapnya di-log dulu sebelum dihapus (pengganti backup,
     * lihat storage/logs/laravel.log setelah seeder ini jalan).
     */
    private function fixChainOrder(): void
    {
        $assets = Asset::where('status', \App\Enums\StatusAset::Aktif)->get();
        $service = app(AssetDepreciationService::class);
        $jumlahDiperbaiki = 0;

        foreach ($assets as $asset) {
            $deps = AssetDepreciation::where('asset_id', $asset->id)->orderBy('periode')->get();
            if ($deps->count() < 2) {
                continue;
            }

            $chainRusak = false;
            $prev = null;
            foreach ($deps as $d) {
                if ($prev && abs((float) $d->nilai_buku_awal - (float) $prev->nilai_buku_akhir) > 1) {
                    $chainRusak = true;
                }
                $prev = $d;
            }

            if (!$chainRusak) {
                continue;
            }

            $snapshot = $deps->map(fn ($d) => [
                'id' => $d->id, 'periode' => $d->periode,
                'nilai_buku_awal' => (float) $d->nilai_buku_awal,
                'jumlah_penyusutan' => (float) $d->jumlah_penyusutan,
                'nilai_buku_akhir' => (float) $d->nilai_buku_akhir,
            ])->toArray();
            Log::info("FixAsetDepresiasiSeeder: chain rusak {$asset->kode_aset}, snapshot sebelum dihapus: " . json_encode($snapshot));

            DB::transaction(function () use ($asset, $deps) {
                $depIds = $deps->pluck('id');
                $periodes = $deps->pluck('periode')->sort()->values();

                TransaksiKeuangan::where('referensi_type', 'asset_depreciation')
                    ->whereIn('referensi_id', $depIds)
                    ->get()
                    ->each(fn ($trx) => $trx->delete());

                AssetDepreciation::whereIn('id', $depIds)->delete();

                $asset->update(['nilai_buku' => $asset->harga_perolehan]);

                foreach ($periodes as $periode) {
                    $asset->refresh();
                    app(AssetDepreciationService::class)->generateAsetTertentu($asset, $periode);
                }
            });

            $asset->refresh();
            $jumlahDiperbaiki++;
            $pesan = "  [Fix Aset] {$asset->kode_aset}: chain diperbaiki, " . $deps->count() .
                ' record lama dihapus & digenerate ulang berurutan. nilai_buku akhir = Rp ' .
                number_format($asset->nilai_buku, 2);
            $this->command?->info($pesan);
            Log::info('FixAsetDepresiasiSeeder: ' . $pesan);
        }

        if ($jumlahDiperbaiki === 0) {
            $this->command?->info('  [Fix Aset] Chain-order check: semua aset sudah benar, tidak ada aksi.');
        }
    }

    /**
     * Deteksi & perbaiki aset dengan nilai_buku yang tidak konsisten
     * terhadap (harga_perolehan - SUM depresiasi yang sudah tercatat) —
     * akibat AssetController::update() versi lama tidak sync nilai_buku
     * saat harga_perolehan/nilai_residu diedit. Koreksi LANGSUNG nilai_buku
     * saja, record depresiasi & TransaksiKeuangan yang sudah ada TIDAK
     * disentuh (jumlah_penyusutan-nya sudah benar, cuma titik-awal
     * nilai_buku yang orphan).
     */
    private function fixSelfConsistency(): void
    {
        $assets = Asset::where('status', \App\Enums\StatusAset::Aktif)->get();
        $jumlahDiperbaiki = 0;

        foreach ($assets as $asset) {
            $totalDepresiasi = (float) AssetDepreciation::where('asset_id', $asset->id)->sum('jumlah_penyusutan');
            $nilaiBukuSeharusnya = max(
                (float) $asset->nilai_residu,
                (float) $asset->harga_perolehan - $totalDepresiasi
            );

            $selisih = round((float) $asset->nilai_buku - $nilaiBukuSeharusnya, 2);
            if (abs($selisih) <= 1) {
                continue;
            }

            $nilaiBukuLama = (float) $asset->nilai_buku;
            $asset->update(['nilai_buku' => $nilaiBukuSeharusnya]);

            $jumlahDiperbaiki++;
            $pesan = "  [Fix Aset] {$asset->kode_aset}: nilai_buku dikoreksi Rp " . number_format($nilaiBukuLama, 2) .
                ' -> Rp ' . number_format($nilaiBukuSeharusnya, 2) . ' (selisih Rp ' . number_format($selisih, 2) . ')';
            $this->command?->info($pesan);
            Log::info('FixAsetDepresiasiSeeder: ' . $pesan);
        }

        if ($jumlahDiperbaiki === 0) {
            $this->command?->info('  [Fix Aset] Self-consistency check: semua aset sudah benar, tidak ada aksi.');
        }
    }
}
