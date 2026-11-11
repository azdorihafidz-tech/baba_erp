<?php

namespace Database\Seeders;

use App\Models\KategoriTransaksi;
use App\Models\TransaksiKeuangan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Koreksi kategori 1 transaksi (id=391, "pengembalian dana miwon 35 kg yang
 * di ganti" Rp875.000) yang salah tercatat sebagai "Lainnya" (LNY, tipe=
 * keduanya) — seharusnya "Retur Pembelian" (RETUR-BELI, tipe=pemasukan,
 * kode_akun_coa=NULL).
 *
 * Root cause: kategori LNY bertipe 'keduanya' punya override paksa di
 * LabaRugiFormalService::resolveKodeAkun() — transaksi sisi pemasukan di
 * kategori 'keduanya' otomatis di-reklas ke akun 4-1199 (Pendapatan Usaha
 * Lainnya), walau kode_akun_coa aslinya (6-1999) beban. Ini benar untuk
 * kasus umum (reimbursement/cashback), tapi SALAH untuk retur pembelian —
 * uang kembali ini bukan pendapatan baru, murni pembatalan sebagian dari
 * pembelian Miwon 35kg sebelumnya (yang sisi belinya sendiri sudah
 * dikecualikan dari Laba Rugi lewat kategori PBB). Akibatnya Laba Ditahan
 * naik palsu Rp875.000, bikin Neraca timpang (ditemukan & diverifikasi
 * lewat audit gap Neraca: -Rp892.511,29 -> -Rp17.511,29 setelah reklas).
 *
 * Kategori "Retur Pembelian" (RETUR-BELI) dibuat BARU khusus untuk kasus
 * ini (bukan reuse PBB) supaya lebih akurat secara pelaporan -- satu arah
 * (tipe=pemasukan, BUKAN 'keduanya') dengan kode_akun_coa=NULL, pola sama
 * persis dengan SETOR-IN/MUTASI-IN (lihat KategoriTransaksiSeeder) supaya
 * TIDAK kena override 'keduanya' yang sama seperti bug ini.
 *
 * Dibuat idempotent & self-sufficient (kategori RETUR-BELI di-firstOrCreate
 * di sini juga, tidak cuma mengandalkan KategoriTransaksiSeeder sudah
 * di-re-run) -- aman dijalankan berkali-kali, di lokal maupun produksi.
 */
class FixTrx391KategoriSeeder extends Seeder
{
    private const TRX_ID = 391;

    public function run(): void
    {
        $kategoriRetur = KategoriTransaksi::withTrashed()->firstOrCreate(
            ['kode' => 'RETUR-BELI'],
            [
                'nama' => 'Retur Pembelian',
                'tipe' => 'pemasukan',
                'is_system' => true,
                'is_active' => true,
                'urutan' => 8,
                'parent_id' => null,
                // kode_akun_coa sengaja TIDAK diisi (tetap NULL) -- transfer
                // internal/pembatalan pembelian, bukan event akuntansi riil.
            ]
        );

        $trx = TransaksiKeuangan::find(self::TRX_ID);

        if (!$trx) {
            $this->command?->warn("  [Fix Trx #391] Transaksi id=" . self::TRX_ID . " tidak ditemukan — dilewati.");
            Log::warning('FixTrx391KategoriSeeder: transaksi id=' . self::TRX_ID . ' tidak ditemukan.');
            return;
        }

        $kategoriLny = KategoriTransaksi::where('kode', 'LNY')->first();

        // Idempotency guard: sudah RETUR-BELI → skip aman.
        if ((int) $trx->kategori_id === (int) $kategoriRetur->id) {
            $this->command?->info('  [Fix Trx #391] Sudah kategori Retur Pembelian — tidak ada aksi (idempotent).');
            return;
        }

        // Guard defensif: kalau kategori_id-nya BUKAN LNY (mis. sudah pernah
        // dikoreksi manual ke kategori lain), jangan timpa diam-diam.
        if (!$kategoriLny || (int) $trx->kategori_id !== (int) $kategoriLny->id) {
            $this->command?->warn(
                '  [Fix Trx #391] kategori_id saat ini (' . $trx->kategori_id . ') BUKAN LNY maupun RETUR-BELI — ' .
                'dilewati demi keamanan (kemungkinan sudah dikoreksi manual). Cek manual kalau perlu.'
            );
            Log::warning('FixTrx391KategoriSeeder: kategori_id tidak sesuai ekspektasi (bukan LNY/RETUR-BELI): ' . $trx->kategori_id);
            return;
        }

        DB::transaction(function () use ($trx, $kategoriRetur) {
            $kategoriLama = $trx->kategori_id;

            // Eloquent update() (bukan raw DB::table) — supaya trait
            // HasAuditLog (logOnlyDirty) di model TransaksiKeuangan otomatis
            // mencatat perubahan ini ke activity log (KeuanganLog).
            $trx->update(['kategori_id' => $kategoriRetur->id]);

            activity('Keuangan')
                ->performedOn($trx)
                ->causedBy(auth()->user())
                ->withProperties([
                    'kategori_id_lama' => $kategoriLama,
                    'kategori_id_baru' => $kategoriRetur->id,
                    'alasan' => 'Koreksi kategori: pengembalian dana retur Miwon 35kg salah tercatat ' .
                        'sebagai Lainnya (kena override otomatis jadi Pendapatan Usaha Lainnya), ' .
                        'seharusnya Retur Pembelian (dikecualikan dari Laba Rugi) — ditemukan via audit gap Neraca.',
                ])
                ->log(
                    'Koreksi kategori transaksi #' . $trx->id . ' (' . $trx->keterangan . '): ' .
                    'Lainnya -> Retur Pembelian (via FixTrx391KategoriSeeder)'
                );
        });

        $pesan = '  [Fix Trx #391] Kategori dikoreksi: Lainnya -> Retur Pembelian (Rp ' . number_format($trx->jumlah, 2, ',', '.') . ').';
        $this->command?->info($pesan);
        Log::info('FixTrx391KategoriSeeder: ' . $pesan);
    }
}
