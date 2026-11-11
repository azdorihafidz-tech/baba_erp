<?php

namespace App\Services;

use App\Enums\KategoriTransaksi as KategoriEnum;
use App\Enums\MetodePenyusutan;
use App\Enums\StatusAset;
use App\Enums\TipeTransaksiKeuangan;
use App\Models\Asset;
use App\Models\AssetDepreciation;
use App\Models\KategoriTransaksi;
use App\Models\TransaksiKeuangan;
use Illuminate\Support\Facades\DB;

class AssetDepreciationService
{
    /**
     * Snapshot untuk widget Dashboard: total nilai aset (harga perolehan,
     * akumulasi depresiasi, nilai buku) + total depresiasi bulan berjalan.
     * $lokasiId null = semua cabang (dashboard pusat).
     */
    public function getSnapshotDashboard(?int $lokasiId = null): array
    {
        $query = Asset::where('status', StatusAset::Aktif);
        if ($lokasiId) {
            $query->where('lokasi_id', $lokasiId);
        }

        $totalHargaPerolehan = (clone $query)->sum('harga_perolehan');
        $totalNilaiBuku      = (clone $query)->sum('nilai_buku');
        $totalAkumulasi      = $totalHargaPerolehan - $totalNilaiBuku;

        $assetIds = (clone $query)->pluck('id');
        $depresiasiBulanIni = AssetDepreciation::whereIn('asset_id', $assetIds)
            ->where('periode', now()->format('Y-m'))
            ->sum('jumlah_penyusutan');

        return [
            'total_harga_perolehan' => (float) $totalHargaPerolehan,
            'total_akumulasi_depresiasi' => (float) $totalAkumulasi,
            'total_nilai_buku' => (float) $totalNilaiBuku,
            'depresiasi_bulan_ini' => (float) $depresiasiBulanIni,
        ];
    }

    /**
     * Generate penyusutan bulan berjalan untuk semua aset aktif — idempotent
     * (aset yang sudah punya penyusutan untuk periode ini otomatis dilewati,
     * dideteksi lewat guard yang sama dengan generateAsetTertentu()).
     */
    public function generateBulanIni(): array
    {
        return $this->generateUntukPeriode(now()->format('Y-m'));
    }

    public function generateUntukPeriode(string $periode): array
    {
        $hasil = ['berhasil' => 0, 'dilewati' => 0, 'gagal' => 0, 'detail' => []];

        // CabangScope tidak pernah benar-benar terdaftar sebagai global scope
        // di codebase ini (lihat Rule #34 CLAUDE.md) — query polos sudah
        // otomatis mencakup semua cabang, TIDAK perlu withoutGlobalScopes()
        // (yang blanket dan berisiko ikut mematikan SoftDeletingScope Asset).
        $assets = Asset::where('status', StatusAset::Aktif)->get();

        foreach ($assets as $asset) {
            try {
                $dep = $this->generateAsetTertentu($asset, $periode);
                if ($dep === null) {
                    $hasil['dilewati']++;
                } else {
                    $hasil['berhasil']++;
                    $hasil['detail'][] = "{$asset->kode_aset} — {$asset->nama_aset}: Rp " . number_format($dep->jumlah_penyusutan, 0, ',', '.');
                }
            } catch (\Throwable $e) {
                $hasil['gagal']++;
                $hasil['detail'][] = "{$asset->kode_aset} — {$asset->nama_aset}: GAGAL ({$e->getMessage()})";
            }
        }

        return $hasil;
    }

    /**
     * Generate penyusutan untuk 1 aset pada 1 periode — idempotent (return
     * null tanpa aksi apapun kalau sudah pernah di-generate untuk periode ini
     * atau nilai buku sudah sama dengan nilai residu). Dipakai bareng oleh
     * trigger manual (form single-asset existing) dan bulk (scheduler/tombol
     * "Generate Bulan Ini").
     *
     * @throws \Exception kalau $periode lebih lama dari periode terakhir yang
     *         sudah ada — hitungPenyusutanBulanan() selalu pakai nilai_buku
     *         SAAT INI (state terkini) sebagai titik awal, jadi generate
     *         periode mundur setelah periode lebih baru sudah ada akan
     *         menghasilkan chain nilai_buku_awal/akhir yang salah urutan
     *         (bug nyata yang ditemukan & diperbaiki di aset AST-2026-0043).
     */
    public function generateAsetTertentu(Asset $asset, string $periode, float $produksiAktual = 0): ?AssetDepreciation
    {
        $existing = $asset->depreciations()->where('periode', $periode)->first();
        if ($existing) {
            return null;
        }

        $maxPeriodeExisting = $asset->depreciations()->max('periode');
        if ($maxPeriodeExisting !== null && $periode < $maxPeriodeExisting) {
            throw new \Exception(
                "Periode {$maxPeriodeExisting} sudah ada untuk aset {$asset->kode_aset}. " .
                "Untuk generate periode lampau ({$periode}), hapus dulu record depresiasi yang lebih baru."
            );
        }

        if ((float) $asset->nilai_buku <= (float) $asset->nilai_residu) {
            return null;
        }

        return DB::transaction(function () use ($asset, $periode, $produksiAktual) {
            $dep = $this->hitungPenyusutanBulanan($asset, $periode, $produksiAktual);
            $this->catatTransaksiKeuangan($asset, $dep);
            return $dep;
        });
    }

    /**
     * Catat beban depresiasi ke TransaksiKeuangan sebagai entry non-cash
     * (kas_id NULL) — mengikuti pola StokService::adjustment() untuk beban
     * susut/rusak/hilang, TIDAK menyentuh saldo Kas manapun (tidak ada
     * kejadian kas fisik). Kategori "Penyusutan Aset" (kode PNYS) SUDAH ADA
     * dari data lama, di-reuse bukan dibuat baru.
     */
    private function catatTransaksiKeuangan(Asset $asset, AssetDepreciation $dep): void
    {
        $kategori = KategoriTransaksi::where('kode', 'PNYS')->first();

        TransaksiKeuangan::create([
            'cabang_id'         => $asset->lokasi_id,
            'kas_id'            => null,
            'nomor_transaksi'   => $this->generateNomorDepresiasi(),
            'tanggal_transaksi' => now()->toDateString(),
            'tipe'              => TipeTransaksiKeuangan::Pengeluaran,
            'kategori'          => KategoriEnum::Penyusutan,
            'kategori_id'       => $kategori?->id,
            'keterangan'        => "Beban depresiasi {$asset->nama_aset} ({$asset->kode_aset}) periode {$dep->periode}",
            'jumlah'            => $dep->jumlah_penyusutan,
            'referensi_type'    => 'asset_depreciation',
            'referensi_id'      => $dep->id,
            'created_by'        => auth()->id(),
        ]);
    }

    private function generateNomorDepresiasi(): string
    {
        $prefix  = 'DEP-' . date('Ymd');
        $lastSeq = DB::table('transaksi_keuangans')
            ->where('nomor_transaksi', 'like', $prefix . '-%')
            ->max(DB::raw("CAST(SUBSTRING_INDEX(nomor_transaksi, '-', -1) AS UNSIGNED)"));

        return $prefix . '-' . str_pad(((int) $lastSeq) + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Hitung penyusutan bulanan untuk sebuah aset pada periode tertentu.
     */
    public function hitungPenyusutanBulanan(Asset $asset, string $periode, float $produksiAktual = 0): AssetDepreciation
    {
        $nilaiBukuAwal = $asset->nilai_buku ?? $asset->harga_perolehan;
        $akumulasiSebelumnya = $asset->depreciations()->sum('jumlah_penyusutan');

        $jumlahPenyusutan = match($asset->metode_penyusutan) {
            MetodePenyusutan::GarisLurus => ($asset->harga_perolehan - $asset->nilai_residu) / max(1, $asset->umur_ekonomis_bulan),
            MetodePenyusutan::SaldoMenurun => $nilaiBukuAwal * (($asset->tarif_penyusutan ?? 0) / 100),
            MetodePenyusutan::SatuanProduksi => $asset->estimasi_produksi_total > 0
                ? (($asset->harga_perolehan - $asset->nilai_residu) / $asset->estimasi_produksi_total) * $produksiAktual
                : 0,
            default => 0,
        };

        // Nilai buku tidak boleh turun di bawah nilai residu
        $nilaiBukuAkhir = max((float)$asset->nilai_residu, (float)$nilaiBukuAwal - $jumlahPenyusutan);
        $jumlahPenyusutan = (float)$nilaiBukuAwal - $nilaiBukuAkhir;

        $dep = AssetDepreciation::create([
            'asset_id'              => $asset->id,
            'periode'               => $periode,
            'nilai_buku_awal'       => $nilaiBukuAwal,
            'jumlah_penyusutan'     => $jumlahPenyusutan,
            'akumulasi_penyusutan'  => $akumulasiSebelumnya + $jumlahPenyusutan,
            'nilai_buku_akhir'      => $nilaiBukuAkhir,
            'produksi_aktual'       => $produksiAktual,
        ]);

        // Update nilai buku aset
        $asset->update(['nilai_buku' => $nilaiBukuAkhir]);

        return $dep;
    }

    /**
     * Generate kode aset otomatis
     */
    public function generateNomorAset(): string
    {
        $count = Asset::withoutGlobalScopes()->count() + 1;
        return 'AST-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
