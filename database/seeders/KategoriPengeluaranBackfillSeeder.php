<?php

namespace Database\Seeders;

use App\Enums\KategoriPengeluaran;
use App\Enums\TipeTransaksiKeuangan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data migration SEKALI JALAN: backfill kategori_pengeluaran untuk transaksi
 * pengeluaran LAMA berdasarkan kategori yang SUDAH diisi user sebelum kolom
 * ini ada (kategori_id → kategori_transaksis.nama, atau kolom `kategori`
 * enum lama kalau kategori_id kosong). Mapping dikonfirmasi langsung oleh
 * Owner (lihat CLAUDE.md Update Log sesi ini untuk daftar lengkap).
 *
 * IDEMPOTENT: setiap UPDATE digated `whereNull('kategori_pengeluaran')` —
 * baris yang sudah punya nilai (dari backfill sebelumnya ATAU dari koreksi
 * manual user lewat form) TIDAK PERNAH ditimpa. Jalan berkali-kali hasilnya
 * sama, aman.
 *
 * TIDAK menyentuh: tipe=pemasukan, kolom kategori/kategori_id asli, kas,
 * atau laporan lain manapun — murni UPDATE 1 kolom (kategori_pengeluaran)
 * pada baris tipe=pengeluaran yang kolom itu masih NULL.
 */
class KategoriPengeluaranBackfillSeeder extends Seeder
{
    /**
     * Mapping nama kategori dinamis (kategori_transaksis.nama, dibandingkan
     * lowercase+trim) → value enum KategoriPengeluaran. Baris atas = mapping
     * literal yang dikonfirmasi Owner; baris bawah = nama default dari
     * KategoriTransaksiSeeder (jaga-jaga kalau user masih pakai kategori
     * bawaan sistem, bukan cuma kategori custom).
     */
    private array $mapNama = [
        // --- Mapping literal dari Owner ---
        'beli bahan baku'  => 'bahan_baku',
        'perlengkapan'     => 'operasional',
        'gaji harian'      => 'gaji_upah',
        'gaji bulanan'     => 'gaji_upah',
        'listrik'          => 'operasional',
        'air'              => 'operasional',
        'sewa tempat'      => 'operasional',
        'wifi/internet'    => 'operasional',
        'wifi'             => 'operasional',
        'internet'         => 'operasional',
        'transportasi'     => 'transportasi',
        'bensin'           => 'transportasi',
        'konsumsi'         => 'operasional',
        'thr'              => 'gaji_upah',
        'denda'            => 'lain_lain',
        'perbaikan'        => 'operasional',
        'lain-lain'        => 'lain_lain',

        // --- Nama default KategoriTransaksiSeeder (kategori bawaan sistem) ---
        'pembelian bahan baku'        => 'bahan_baku',
        'gaji karyawan'               => 'gaji_upah',
        'sewa gedung'                 => 'operasional',
        'biaya operasional'           => 'operasional',
        'penyusutan aset'             => 'lain_lain',
        'pembelian aset'              => 'lain_lain',
        'setor ke pusat / cabang lain' => 'lain_lain',
        'beban kerugian stok'         => 'lain_lain',
        'lainnya'                     => 'lain_lain',
    ];

    /**
     * Fallback keyword kalau nama kategori TIDAK persis ada di $mapNama
     * (kategori custom lain yang belum kepikir, mis. Bonus/Sedekah/Zakat/
     * Pajak — sesuai instruksi Owner).
     */
    private array $keywordFallback = [
        'gaji_upah'    => ['gaji', 'insentif', 'bonus', 'thr', 'upah'],
        'lain_lain'    => ['sedekah', 'zakat', 'csr', 'sumbangan', 'denda'],
        'operasional'  => ['pajak', 'listrik', 'air', 'sewa', 'wifi', 'internet', 'konsumsi', 'perlengkapan', 'perbaikan'],
        'transportasi' => ['bensin', 'transport', 'bbm', 'ongkos'],
        'bahan_baku'   => ['bahan baku'],
    ];

    /** Fallback terakhir kalau kategori_id NULL — pakai kolom `kategori` enum lama */
    private array $mapEnum = [
        'pembelian_bahan' => 'bahan_baku',
        'gaji'            => 'gaji_upah',
        'sewa_gedung'     => 'operasional',
        'operasional'     => 'operasional',
        'pembelian_aset'  => 'lain_lain',
        'penyusutan'      => 'lain_lain',
        'lainnya'         => 'lain_lain',
    ];

    public function run(): void
    {
        $tipePengeluaran = TipeTransaksiKeuangan::Pengeluaran->value;
        $validValues = array_column(KategoriPengeluaran::cases(), 'value');

        // === SNAPSHOT SEBELUM (log sebagai jejak audit / pengganti backup) ===
        $totalPengeluaran = DB::table('transaksi_keuangans')->where('tipe', $tipePengeluaran)->count();
        $nullBefore = DB::table('transaksi_keuangans')->where('tipe', $tipePengeluaran)->whereNull('kategori_pengeluaran')->count();

        $this->command->info('=== Backfill kategori_pengeluaran ===');
        $this->command->info("Total transaksi pengeluaran : {$totalPengeluaran}");
        $this->command->info("NULL sebelum update         : {$nullBefore}");

        $snapshotSebelum = DB::table('transaksi_keuangans')
            ->where('tipe', $tipePengeluaran)
            ->whereNull('kategori_pengeluaran')
            ->get(['id', 'nomor_transaksi', 'kategori', 'kategori_id', 'jumlah']);
        foreach ($snapshotSebelum as $row) {
            $this->command->line("  [before] id={$row->id} {$row->nomor_transaksi} kategori={$row->kategori} kategori_id=" . ($row->kategori_id ?? '-') . " jumlah={$row->jumlah}");
        }

        $updatedByName = 0;
        $updatedByEnum = 0;
        $updatedByDefault = 0;
        $defaultBucketNames = [];

        // === TAHAP 1: mapping via kategori_id -> kategori_transaksis.nama ===
        // Cuma pertimbangkan kategori bertipe pengeluaran/keduanya — kategori
        // pemasukan-only (mis. "Penjualan Produk") dilewati karena tidak
        // mungkin punya baris transaksi tipe=pengeluaran yang valid.
        $kategoriRows = DB::table('kategori_transaksis')
            ->whereIn('tipe', ['pengeluaran', 'keduanya'])
            ->get(['id', 'nama']);
        foreach ($kategoriRows as $kat) {
            $namaNormal = strtolower(trim(preg_replace('/\s+/', ' ', $kat->nama)));

            $target = $this->mapNama[$namaNormal] ?? $this->resolveByKeyword($namaNormal);
            $isDefaultBucket = false;

            if (!$target) {
                // "Yang tidak jelas -> lain_lain (default aman)"
                $target = KategoriPengeluaran::LainLain->value;
                $isDefaultBucket = true;
            }

            if (!in_array($target, $validValues, true)) {
                continue;
            }

            $n = DB::table('transaksi_keuangans')
                ->where('tipe', $tipePengeluaran)
                ->where('kategori_id', $kat->id)
                ->whereNull('kategori_pengeluaran')
                ->update(['kategori_pengeluaran' => $target]);

            $updatedByName += $n;

            // Cuma catat sebagai "kena default" kalau memang ada baris yang
            // benar-benar terdampak — supaya laporan tidak riuh oleh nama
            // kategori yang kebetulan 0 transaksi.
            if ($isDefaultBucket && $n > 0) {
                $defaultBucketNames[] = $kat->nama . " ({$n} transaksi)";
            }
        }

        // === TAHAP 2: fallback via kolom kategori (enum lama) utk baris tanpa kategori_id ===
        foreach ($this->mapEnum as $enumValue => $target) {
            $n = DB::table('transaksi_keuangans')
                ->where('tipe', $tipePengeluaran)
                ->where('kategori', $enumValue)
                ->whereNull('kategori_id')
                ->whereNull('kategori_pengeluaran')
                ->update(['kategori_pengeluaran' => $target]);

            $updatedByEnum += $n;
        }

        // === TAHAP 3: default terakhir — sisa yang masih NULL (tanpa kategori_id
        // ATAU enum-nya tidak ada di $mapEnum) -> lain_lain, sesuai instruksi
        // "yang tidak jelas -> lain_lain (default aman)" ===
        $updatedByDefault = DB::table('transaksi_keuangans')
            ->where('tipe', $tipePengeluaran)
            ->whereNull('kategori_pengeluaran')
            ->update(['kategori_pengeluaran' => KategoriPengeluaran::LainLain->value]);

        // === VERIFIKASI SESUDAH ===
        $nullAfter = DB::table('transaksi_keuangans')->where('tipe', $tipePengeluaran)->whereNull('kategori_pengeluaran')->count();
        $totalUpdated = $updatedByName + $updatedByEnum + $updatedByDefault;

        $this->command->info("Ter-update via nama kategori dinamis : {$updatedByName}");
        $this->command->info("Ter-update via enum kategori lama    : {$updatedByEnum}");
        $this->command->info("Ter-update via default 'lain_lain'   : {$updatedByDefault}");
        $this->command->info("TOTAL ter-update sesi ini            : {$totalUpdated}");
        $this->command->info("NULL sesudah update (harus 0)        : {$nullAfter}");

        if (!empty($defaultBucketNames)) {
            $this->command->warn('Kategori dinamis yang TIDAK match mapping/keyword eksplisit (di-default ke Lain-lain):');
            foreach (array_unique($defaultBucketNames) as $nama) {
                $this->command->warn("  - {$nama}");
            }
        }
    }

    private function resolveByKeyword(string $namaNormal): ?string
    {
        foreach ($this->keywordFallback as $target => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($namaNormal, $kw)) {
                    return $target;
                }
            }
        }
        return null;
    }
}
