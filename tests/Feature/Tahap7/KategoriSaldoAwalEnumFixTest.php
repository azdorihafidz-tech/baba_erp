<?php

namespace Tests\Feature\Tahap7;

use App\Models\Kas;
use App\Models\KategoriTransaksi as KategoriTransaksiModel;
use App\Models\TransaksiKeuangan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Bug fix 2026-09-19 (laporan Owner: 500 error saat submit "Tambah
 * Transaksi" pemasukan kas dgn kategori "Saldo Awal") — root cause:
 * `transaksi_keuangans.kategori` masih raw MySQL ENUM warisan Berkah Mulyo
 * (migration 2024_01_01_000015) dengan 9 nilai TETAP, TIDAK PERNAH di-widen
 * sejak `App\Enums\KategoriTransaksi::SaldoAwal` ('saldo_awal') + seeder
 * kategori_transaksis row 'SALDO' (is_system, tipe pemasukan) ditambahkan --
 * kolom DB dan level PHP jadi tidak sinkron. Insert manapun dgn
 * kategori='saldo_awal' gagal (SQLSTATE 01000: Data truncated).
 *
 * Fix: migration `2026_09_19_800001_widen_kategori_enum_in_transaksi_keuangans_table`
 * -- ALTER ENUM tambah 'saldo_awal' (satu-satunya value yang kurang,
 * dikonfirmasi dari KategoriTransaksi::toEnumValue() di app/Models).
 */
class KategoriSaldoAwalEnumFixTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_kategori_column_sekarang_terima_saldo_awal(): void
    {
        $columnType = \DB::selectOne("SHOW COLUMNS FROM transaksi_keuangans WHERE Field='kategori'")->Type;

        $this->assertStringContainsString("'saldo_awal'", $columnType);
    }

    public function test_submit_transaksi_pemasukan_kategori_saldo_awal_berhasil(): void
    {
        // Skenario PERSIS laporan Owner: form "Tambah Transaksi" (bukan alur
        // create-Kas otomatis) dgn kategori "Saldo Awal" dipilih manual.
        $admin = $this->buatUser('owner');
        $cabang = $this->cabangPertama();
        $kas = Kas::create([
            'cabang_id' => $cabang->id, 'nama_kas' => 'Kas Test Saldo Awal', 'tipe_kas' => 'tunai',
            'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true,
        ]);
        $kategoriSaldo = KategoriTransaksiModel::where('kode', 'SALDO')->firstOrFail();

        $response = $this->actingAs($admin)
            ->withSession(['active_cabang_id' => $cabang->id])
            ->post(route('keuangan.store'), [
                'tanggal_transaksi' => now()->format('Y-m-d'),
                'tipe' => 'pemasukan',
                'kategori_id' => $kategoriSaldo->id,
                'keterangan' => 'test saldo awal manual',
                'jumlah' => '1000000',
                'kas_id' => $kas->id,
            ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('transaksi_keuangans', [
            'kas_id' => $kas->id, 'kategori' => 'saldo_awal', 'kategori_id' => $kategoriSaldo->id, 'jumlah' => 1000000,
        ]);
        $kas->refresh();
        $this->assertEquals(1000000, (float) $kas->saldo_sekarang);
    }

    public function test_kas_baru_dgn_saldo_awal_tetap_normal_regresi(): void
    {
        // Jalur LAIN yang juga pakai kategori 'saldo_awal' (auto, bukan
        // manual) -- KeuanganController::storeKas() ~line 678.
        $admin = $this->buatUser('owner');
        $cabang = $this->cabangPertama();

        $response = $this->actingAs($admin)
            ->withSession(['active_cabang_id' => $cabang->id])
            ->post(route('keuangan.kas.store'), [
                'cabang_id' => $cabang->id,
                'nama_kas' => 'Kas Baru Regresi',
                'tipe_kas' => 'tunai',
                'saldo_awal' => '500000',
            ]);

        $response->assertSessionDoesntHaveErrors();
        $kas = Kas::where('nama_kas', 'Kas Baru Regresi')->firstOrFail();
        $this->assertDatabaseHas('transaksi_keuangans', ['kas_id' => $kas->id, 'kategori' => 'saldo_awal']);
    }

    public function test_regresi_kategori_lama_masih_bisa_disimpan(): void
    {
        $admin = $this->buatUser('owner');
        $cabang = $this->cabangPertama();
        $kas = Kas::create([
            'cabang_id' => $cabang->id, 'nama_kas' => 'Kas Test Kategori Lama', 'tipe_kas' => 'tunai',
            'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true,
        ]);
        $kategoriOperasional = KategoriTransaksiModel::where('kode', 'OPS')->firstOrFail();

        $response = $this->actingAs($admin)
            ->withSession(['active_cabang_id' => $cabang->id])
            ->post(route('keuangan.store'), [
                'tanggal_transaksi' => now()->format('Y-m-d'),
                'tipe' => 'pengeluaran',
                'kategori_id' => $kategoriOperasional->id,
                'kategori_pengeluaran' => 'lain_lain',
                'keterangan' => 'test regresi kategori lama',
                'jumlah' => '50000',
                'kas_id' => $kas->id,
            ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('transaksi_keuangans', ['kas_id' => $kas->id, 'kategori' => 'operasional']);
    }
}
