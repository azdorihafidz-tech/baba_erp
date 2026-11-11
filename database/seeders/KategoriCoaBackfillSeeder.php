<?php

namespace Database\Seeders;

use App\Models\KategoriTransaksi;
use Illuminate\Database\Seeder;

/**
 * Mapping kategori_transaksis -> chart_of_accounts (kode_akun_coa + tipe_biaya).
 * Idempotent: setiap update digated `whereNull('kode_akun_coa')` supaya tidak
 * pernah menimpa reklas manual yang sudah dilakukan Owner lewat UI.
 */
class KategoriCoaBackfillSeeder extends Seeder
{
    public function run(): void
    {
        // Tambah kategori baru "Listrik & Air" — sebelumnya tidak ada kategori
        // dinamis untuk beban ini sama sekali (ditemukan saat audit Fase 1,
        // disetujui Owner untuk ditambah eksplisit alih-alih numpang ke OPS).
        KategoriTransaksi::firstOrCreate(
            ['kode' => 'LISTRIK'],
            [
                'nama'      => 'Listrik & Air',
                'tipe'      => 'pengeluaran',
                'is_system' => true,
                'is_active' => true,
                'urutan'    => 18,
            ]
        );

        // Rename kategori terkait fitur Transfer/Perpindahan Dana (I4) — kode
        // (SETOR-IN/SETOR-OUT) & enum status_setoran TIDAK berubah, cuma nama
        // tampilan. Idempotent murni (update by kode, aman diulang).
        KategoriTransaksi::withTrashed()->where('kode', 'SETOR-IN')
            ->update(['nama' => 'Perpindahan Dana Masuk']);
        KategoriTransaksi::withTrashed()->where('kode', 'SETOR-OUT')
            ->update(['nama' => 'Perpindahan Dana Keluar']);

        // kode => [kode_akun_coa, tipe_biaya]
        // NULL kode_akun_coa = transfer internal / bukan event akuntansi riil,
        // sengaja tidak dipetakan (setoran/transfer, saldo awal).
        $mapping = [
            'PENJ'       => ['4-1102', null],
            'JASA'       => ['4-1101', null],
            'SALDO'      => [null, null],
            'SETOR-IN'   => [null, null],
            'LNY-IN'     => ['4-1199', null],
            'KOR-STOK'   => ['4-1199', null],
            'PBB'        => ['5-1101', 'variabel'],
            'GAJI'       => ['6-1101', 'tetap'],
            'SEWA'       => ['6-1102', 'tetap'],
            'PNYS'       => ['6-1104', 'tetap'],
            'OPS'        => ['6-1301', 'variabel'],
            'ASET'       => ['1-2199', null],
            'SETOR-OUT'  => [null, null],
            'BEBAN-STOK' => ['6-1401', 'variabel'],
            'LNY'        => ['6-1999', null],
            'MRT'        => ['6-1999', 'variabel'],
            'PEIN'       => ['4-1199', null],
            'LISTRIK'    => ['6-1103', 'tetap'],
        ];

        // Pakai kolom penanda sementara (bukan whereNull kode_akun_coa) untuk
        // membedakan "sengaja NULL" (transfer/saldo) vs "belum pernah disentuh
        // sama sekali" (kategori custom di luar daftar) — supaya catch-all di
        // bawah tidak menimpa NULL yang memang disengaja.
        $sudahDiproses = [];
        $updated = 0;
        foreach ($mapping as $kode => [$kodeAkun, $tipeBiaya]) {
            $exists = KategoriTransaksi::withTrashed()->where('kode', $kode)->exists();
            if (!$exists) {
                continue;
            }
            $sudahDiproses[] = $kode;

            // whereNull tetap dipakai supaya idempotent (tidak menimpa reklas
            // manual Owner), tapi HANYA untuk kode yang benar2 belum pernah
            // di-set — kode dengan target NULL (transfer/saldo) tetap
            // "dianggap sudah diproses" via daftar $sudahDiproses di atas.
            if ($kodeAkun !== null) {
                $affected = KategoriTransaksi::withTrashed()
                    ->where('kode', $kode)
                    ->whereNull('kode_akun_coa')
                    ->update(['kode_akun_coa' => $kodeAkun, 'tipe_biaya' => $tipeBiaya]);
                $updated += $affected;
            }
        }

        // Sisa kategori dinamis yang SAMA SEKALI TIDAK ADA di daftar mapping
        // di atas (custom buatan user) -> default ke 6-1999 Beban Lain-lain
        // kalau pengeluaran, 4-1199 Pendapatan Usaha Lainnya kalau pemasukan.
        // Kode yang sengaja NULL (SALDO/SETOR-IN/SETOR-OUT) DIKECUALIKAN via
        // whereNotIn supaya tidak ikut ke-default.
        $sisaPengeluaran = KategoriTransaksi::withTrashed()
            ->where('tipe', 'pengeluaran')
            ->whereNotIn('kode', $sudahDiproses)
            ->whereNull('kode_akun_coa')
            ->update(['kode_akun_coa' => '6-1999']);

        $sisaPemasukan = KategoriTransaksi::withTrashed()
            ->where('tipe', 'pemasukan')
            ->whereNotIn('kode', $sudahDiproses)
            ->whereNull('kode_akun_coa')
            ->update(['kode_akun_coa' => '4-1199']);

        $this->command->info("  KategoriCoaBackfillSeeder: {$updated} kategori dipetakan eksplisit, "
            . ($sisaPengeluaran + $sisaPemasukan) . " kategori sisa (custom) dipetakan ke default.");
    }
}
