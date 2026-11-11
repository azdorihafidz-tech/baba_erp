<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\KategoriTransaksi;
use Illuminate\Database\Seeder;

/**
 * Fase 5 — Modul Perlengkapan (Rule #66). Pattern IDENTIK
 * KategoriCoaBackfillSeeder existing (firstOrCreate idempotent).
 *
 * TIDAK insert ke chart_of_accounts — kode '6-1106' "Beban Perlengkapan
 * Kantor" SUDAH ADA sejak ChartOfAccountsSeeder awal (Fase 1 Akuntansi),
 * tapi belum pernah dipetakan ke kategori_transaksis manapun (dicek via
 * tinker sebelum apply: KategoriTransaksi::where('kode_akun_coa','6-1106')
 * kosong). Seeder ini murni menambah 1 KategoriTransaksi baru yang
 * menunjuk ke kode COA yang sudah ada — zero sentuh chart_of_accounts,
 * zero sentuh resolveKodeAkun()/LabaRugiFormalService.
 */
class PerlengkapanKategoriCoaSeeder extends Seeder
{
    public function run(): void
    {
        $kodeAkun = '6-1106';

        $coa = ChartOfAccount::where('kode', $kodeAkun)->first();
        if (!$coa) {
            $this->command->error("  PerlengkapanKategoriCoaSeeder: kode akun {$kodeAkun} tidak ditemukan di chart_of_accounts — dibatalkan, cek ChartOfAccountsSeeder dulu.");
            return;
        }

        $sudahDipakai = KategoriTransaksi::withTrashed()->where('kode_akun_coa', $kodeAkun)->exists();
        if ($sudahDipakai) {
            $this->command->warn("  PerlengkapanKategoriCoaSeeder: kode akun {$kodeAkun} sudah dipakai kategori lain — skip (tidak bikin duplikat).");
            return;
        }

        $urutanMax = (int) KategoriTransaksi::withTrashed()->max('urutan');

        $kategori = KategoriTransaksi::firstOrCreate(
            ['kode' => 'PERLENGKAPAN'],
            [
                'nama'          => 'Beban Perlengkapan Kantor',
                'tipe'          => 'pengeluaran',
                'is_system'     => false,
                'is_active'     => true,
                'urutan'        => $urutanMax + 1,
                'kode_akun_coa' => $kodeAkun,
                'tipe_biaya'    => 'tetap',
            ]
        );

        $this->command->info("  PerlengkapanKategoriCoaSeeder: kategori 'PERLENGKAPAN' (Beban Perlengkapan Kantor) -> COA {$kodeAkun} (id={$kategori->id}).");
    }
}
