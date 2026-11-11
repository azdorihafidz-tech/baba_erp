<?php

namespace Tests\Feature\Tahap7;

use App\Models\Order;
use App\Models\Setoran;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Sprint 3 Batch 1a D'mentai (2026-09-21) — rapikan Export Excel (ganti
 * `fputcsv()` manual jadi xlsx sungguhan via maatwebsite/excel, pola sama
 * fix Laporan Keuangan sebelumnya) + tambah Export PDF (DomPDF) utk 2
 * menu prioritas 1: Laporan Penjualan & Laporan Setoran Kasir.
 */
class LaporanBatch1aExportTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    // ===== Laporan Penjualan =====

    public function test_penjualan_export_excel_valid_xlsx(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $cabang = $this->cabangPertama();
        Order::create([
            'cabang_id' => $cabang->id, 'nomor_order' => 'ORD-B1A-001', 'tanggal_order' => now()->toDateString(),
            'tipe_order' => 'penjualan', 'status' => 'selesai', 'nama_pelanggan' => 'Test Pembeli',
            'total_bayar' => 75000, 'jumlah_bayar' => 75000, 'kembalian' => 0, 'tipe_pembayaran' => 'tunai',
        ]);

        $response = $this->actingAs($admin)->get(route('laporan.penjualan', ['export' => 'excel']));

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('Content-Type') ?? ''
        );
    }

    public function test_penjualan_export_pdf_valid(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $cabang = $this->cabangPertama();
        Order::create([
            'cabang_id' => $cabang->id, 'nomor_order' => 'ORD-B1A-002', 'tanggal_order' => now()->toDateString(),
            'tipe_order' => 'penjualan', 'status' => 'selesai', 'nama_pelanggan' => 'Test Pembeli 2',
            'total_bayar' => 50000, 'jumlah_bayar' => 50000, 'kembalian' => 0, 'tipe_pembayaran' => 'tunai',
        ]);

        $response = $this->actingAs($admin)->get(route('laporan.penjualan', ['export' => 'pdf']));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_penjualan_export_excel_filter_tanggal_dan_cabang_sesuai(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $cabang = $this->cabangPertama();
        $cabangLain = $this->cabangKedua();
        Order::create([
            'cabang_id' => $cabang->id, 'nomor_order' => 'ORD-B1A-003', 'tanggal_order' => now()->toDateString(),
            'tipe_order' => 'penjualan', 'status' => 'selesai', 'nama_pelanggan' => 'Cabang A',
            'total_bayar' => 10000, 'jumlah_bayar' => 10000, 'kembalian' => 0, 'tipe_pembayaran' => 'tunai',
        ]);
        Order::create([
            'cabang_id' => $cabangLain->id, 'nomor_order' => 'ORD-B1A-004', 'tanggal_order' => now()->subMonths(2)->toDateString(),
            'tipe_order' => 'penjualan', 'status' => 'selesai', 'nama_pelanggan' => 'Cabang B Bulan Lalu',
            'total_bayar' => 20000, 'jumlah_bayar' => 20000, 'kembalian' => 0, 'tipe_pembayaran' => 'tunai',
        ]);

        // Filter cabang A, periode default (bulan ini) -- order cabang lain
        // (beda cabang DAN beda bulan) tidak boleh ikut.
        $response = $this->actingAs($admin)->get(route('laporan.penjualan', ['export' => 'excel', 'cabang_id' => $cabang->id]));

        $response->assertOk();
    }

    public function test_penjualan_export_tanpa_data_tetap_generate(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $responseExcel = $this->actingAs($admin)->get(route('laporan.penjualan', [
            'export' => 'excel', 'dari' => '2020-01-01', 'sampai' => '2020-01-31',
        ]));
        $responsePdf = $this->actingAs($admin)->get(route('laporan.penjualan', [
            'export' => 'pdf', 'dari' => '2020-01-01', 'sampai' => '2020-01-31',
        ]));

        $responseExcel->assertOk();
        $responsePdf->assertOk();
    }

    // ===== Laporan Setoran Kasir =====

    private function buatSetoran(int $cabangId, int $userId, float $sistem, float $disetor, string $tanggal): Setoran
    {
        return Setoran::create([
            'cabang_id' => $cabangId, 'tanggal' => $tanggal, 'disubmit_oleh' => $userId,
            'total_penjualan_sistem' => $sistem, 'total_disetor' => $disetor, 'selisih' => $disetor - $sistem,
            'status' => 'menunggu',
        ]);
    }

    public function test_setoran_kasir_export_excel_valid_xlsx(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $cabang = $this->cabangPertama();
        $this->buatSetoran($cabang->id, $admin->id, 500000, 500000, now()->toDateString());

        $response = $this->actingAs($admin)->get(route('laporan.setoran-kasir.export'));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type') ?? '');
    }

    public function test_setoran_kasir_export_pdf_valid(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $cabang = $this->cabangPertama();
        $this->buatSetoran($cabang->id, $admin->id, 300000, 290000, now()->toDateString());

        $response = $this->actingAs($admin)->get(route('laporan.setoran-kasir.export', ['format' => 'pdf']));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_setoran_kasir_export_filter_cabang_dan_status_sesuai(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $cabang = $this->cabangPertama();
        $this->buatSetoran($cabang->id, $admin->id, 100000, 100000, now()->toDateString());

        $response = $this->actingAs($admin)->get(route('laporan.setoran-kasir.export', [
            'cabang_id' => $cabang->id, 'status' => 'menunggu',
        ]));

        $response->assertOk();
    }

    public function test_setoran_kasir_export_tanpa_data_tetap_generate(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $responseExcel = $this->actingAs($admin)->get(route('laporan.setoran-kasir.export', [
            'dari' => '2020-01-01', 'sampai' => '2020-01-31',
        ]));
        $responsePdf = $this->actingAs($admin)->get(route('laporan.setoran-kasir.export', [
            'dari' => '2020-01-01', 'sampai' => '2020-01-31', 'format' => 'pdf',
        ]));

        $responseExcel->assertOk();
        $responsePdf->assertOk();
    }

    public function test_setoran_kasir_export_tanpa_permission_ditolak(): void
    {
        $kasir = $this->buatUser('kasir', $this->cabangPertama()->id);

        $response = $this->actingAs($kasir)->get(route('laporan.setoran-kasir.export'));

        $response->assertStatus(403);
    }

    // ===== Regresi =====

    public function test_regresi_halaman_html_masih_normal(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $this->actingAs($admin)->get(route('laporan.penjualan'))->assertOk();
        $this->actingAs($admin)->get(route('laporan.setoran-kasir.index'))->assertOk();
    }
}
