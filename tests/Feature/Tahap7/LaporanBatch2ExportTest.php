<?php

namespace Tests\Feature\Tahap7;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\ChartOfAccount;
use App\Models\TransaksiKeuangan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Sprint 3 Batch 2 D'mentai (2026-09-22) — rapikan Export Excel + tambah
 * Export PDF untuk 9 menu Laporan sisa (dari 17 menu, 3 di-skip sebagai
 * blocker: Komisi Sales/belum ada fiturnya, Eksekutif/laporan komposit
 * narasi bukan tabular, Simulator BEP/murni client-side JS tanpa data
 * server). Menu: Aset (2 sub), BEP manual (2 sub), BEP Otomatis, Neraca,
 * Laba Rugi Formal, Buku Besar, Audit Bukti, Analisa Jam Ramai, Pemakaian
 * Perlengkapan.
 */
class LaporanBatch2ExportTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function assertExcel($response): void
    {
        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type') ?? '');
    }

    private function assertPdf($response): void
    {
        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    // ===== Laporan Aset =====

    public function test_aset_index_export_excel_dan_pdf(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $cabang = $this->cabangPertama();
        $kategori = AssetCategory::create(['nama_kategori' => 'Elektronik', 'kode_kategori' => 'ELK-B2']);
        Asset::create([
            'kode_aset' => 'AST-B2-001', 'nama_aset' => 'Kulkas', 'kategori_aset_id' => $kategori->id,
            'lokasi_id' => $cabang->id, 'tanggal_perolehan' => now()->subMonths(6),
            'harga_perolehan' => 5000000, 'nilai_buku' => 4500000, 'status' => 'aktif', 'kondisi' => 'baik',
            'umur_ekonomis_bulan' => 60,
        ]);

        $this->assertExcel($this->actingAs($admin)->get(route('laporan.aset', ['export' => 'excel'])));
        $this->assertPdf($this->actingAs($admin)->get(route('laporan.aset', ['export' => 'pdf'])));
    }

    public function test_aset_penyusutan_export_excel_dan_pdf_kosong(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $this->assertExcel($this->actingAs($admin)->get(route('laporan.aset.penyusutan', ['export' => 'excel'])));
        $this->assertPdf($this->actingAs($admin)->get(route('laporan.aset.penyusutan', ['export' => 'pdf'])));
    }

    // ===== Laporan BEP (manual) =====

    public function test_bep_index_export_excel_dan_pdf_kosong(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $this->assertExcel($this->actingAs($admin)->get(route('laporan.bep', ['export' => 'excel'])));
        $this->assertPdf($this->actingAs($admin)->get(route('laporan.bep', ['export' => 'pdf'])));
    }

    public function test_bep_per_cabang_export_excel_dan_pdf(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $this->assertExcel($this->actingAs($admin)->get(route('laporan.bep.per-cabang', ['export' => 'excel'])));
        $this->assertPdf($this->actingAs($admin)->get(route('laporan.bep.per-cabang', ['export' => 'pdf'])));
    }

    // ===== Laporan BEP Otomatis =====

    public function test_bep_otomatis_export_excel(): void
    {
        $owner = $this->buatUser('owner');

        $this->assertExcel($this->actingAs($owner)->get(route('laporan.bep-otomatis.export-excel')));
    }

    // ===== Laporan Neraca =====

    public function test_neraca_export_excel(): void
    {
        $owner = $this->buatUser('owner');

        $this->assertExcel($this->actingAs($owner)->get(route('laporan.neraca.export-excel')));
    }

    // ===== Laporan Laba Rugi Formal =====

    public function test_laba_rugi_formal_export_excel(): void
    {
        $owner = $this->buatUser('owner');

        $this->assertExcel($this->actingAs($owner)->get(route('laporan.laba-rugi-formal.export-excel')));
    }

    // ===== Laporan Buku Besar =====

    public function test_buku_besar_export_excel_dengan_akun(): void
    {
        $owner = $this->buatUser('owner');
        $akun = ChartOfAccount::create([
            'kode' => '6100', 'nama' => 'Beban Test Batch2', 'tipe' => 'beban_operasional',
            'saldo_normal' => 'debet', 'is_leaf' => true,
        ]);

        $response = $this->actingAs($owner)->get(route('laporan.buku-besar.export-excel', ['kode_akun' => $akun->kode]));

        $this->assertExcel($response);
    }

    public function test_buku_besar_export_excel_tanpa_kode_akun_ditolak_validasi(): void
    {
        $owner = $this->buatUser('owner');

        $response = $this->actingAs($owner)->get(route('laporan.buku-besar.export-excel'));

        $response->assertSessionHasErrors('kode_akun');
    }

    // ===== Laporan Audit Bukti =====

    public function test_audit_bukti_export_excel_dan_pdf(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $cabang = $this->cabangPertama();
        TransaksiKeuangan::withoutGlobalScopes()->create([
            'cabang_id' => $cabang->id, 'tipe' => 'pengeluaran', 'jumlah' => 1000000,
            'tanggal_transaksi' => now()->toDateString(), 'keterangan' => 'Beli Aset Test',
            'nomor_transaksi' => 'TRX-B2-AUDIT-001',
        ]);

        $this->assertExcel($this->actingAs($admin)->get(route('laporan.audit-bukti', ['export' => 'excel'])));
        $this->assertPdf($this->actingAs($admin)->get(route('laporan.audit-bukti', ['export' => 'pdf'])));
    }

    // ===== Laporan Analisa Jam Ramai =====

    public function test_jam_ramai_export_excel_dan_pdf(): void
    {
        $owner = $this->buatUser('owner');

        $this->assertExcel($this->actingAs($owner)->get(route('laporan.jam-ramai.export-excel')));
        $this->assertPdf($this->actingAs($owner)->get(route('laporan.jam-ramai.export-pdf')));
    }

    public function test_jam_ramai_export_ditolak_untuk_role_tanpa_permission(): void
    {
        $kasir = $this->buatUser('kasir', $this->cabangPertama()->id);

        $response = $this->actingAs($kasir)->get(route('laporan.jam-ramai.export-excel'));

        $response->assertForbidden();
    }

    // ===== Laporan Pemakaian Perlengkapan =====

    public function test_perlengkapan_export_excel_dan_pdf(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $this->assertExcel($this->actingAs($admin)->get(route('laporan.perlengkapan.export')));
        $this->assertPdf($this->actingAs($admin)->get(route('laporan.perlengkapan.export-pdf')));
    }
}
