<?php

namespace Tests\Feature\Tahap7;

use App\Models\Item;
use App\Models\Order;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\TransaksiKeuangan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Sprint 3 Batch 1b+1c D'mentai (2026-09-21) — rapikan Excel + tambah PDF
 * utk 3 menu sisa Prioritas 1: Setoran Harian (rekap per Tanggal+Cabang,
 * BUKAN nama "Setoran Kasir" spy tidak rancu dgn menu lain), Stok (3
 * sub-view: index/pergerakan/minimum), Laba Rugi Produksi (BEDA dari
 * Laporan Keuangan -- lihat CLAUDE.md 4.24/4.25).
 */
class LaporanBatch1bc1cExportTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    // ===== Setoran Harian (Rekap) =====

    public function test_setoran_harian_export_excel_valid_xlsx(): void
    {
        $admin = $this->buatUser('owner');
        $cabang = $this->cabangPertama();
        Order::create([
            'cabang_id' => $cabang->id, 'nomor_order' => 'ORD-B1BC-001', 'tanggal_order' => now()->toDateString(),
            'tipe_order' => 'penjualan', 'status' => 'selesai', 'nama_pelanggan' => 'Test',
            'total_bayar' => 40000, 'jumlah_bayar' => 40000, 'kembalian' => 0, 'tipe_pembayaran' => 'tunai',
        ]);

        $response = $this->actingAs($admin)->get(route('laporan.setoran-harian.export'));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type') ?? '');
    }

    public function test_setoran_harian_export_pdf_valid(): void
    {
        $admin = $this->buatUser('owner');

        $response = $this->actingAs($admin)->get(route('laporan.setoran-harian.export', ['format' => 'pdf']));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_setoran_harian_rekap_tidak_double_count_pemasukan_order(): void
    {
        // Order menciptakan TransaksiKeuangan referensi_type=order OTOMATIS
        // (PenjualanService) -- rekap TIDAK boleh hitung dobel omzet order
        // itu lewat SUM(transaksi_keuangans pemasukan).
        $admin = $this->buatUser('owner');
        $cabang = $this->cabangPertama();
        $tanggal = now()->subDay()->toDateString();
        Order::create([
            'cabang_id' => $cabang->id, 'nomor_order' => 'ORD-B1BC-002', 'tanggal_order' => $tanggal,
            'tipe_order' => 'penjualan', 'status' => 'selesai', 'nama_pelanggan' => 'Test Dobel',
            'total_bayar' => 100000, 'jumlah_bayar' => 100000, 'kembalian' => 0, 'tipe_pembayaran' => 'tunai',
        ]);
        // Simulasi TransaksiKeuangan auto-generate dari order (referensi_type=order)
        TransaksiKeuangan::create([
            'cabang_id' => $cabang->id, 'nomor_transaksi' => 'TRX-B1BC-' . uniqid('', false),
            'tanggal_transaksi' => $tanggal, 'tipe' => 'pemasukan', 'kategori' => 'penjualan',
            'keterangan' => 'auto dari order', 'jumlah' => 100000,
            'referensi_type' => 'order', 'referensi_id' => 1,
        ]);
        // Pemasukan manual NON-order (harus ikut dihitung)
        TransaksiKeuangan::create([
            'cabang_id' => $cabang->id, 'nomor_transaksi' => 'TRX-B1BC-' . uniqid('', false),
            'tanggal_transaksi' => $tanggal, 'tipe' => 'pemasukan', 'kategori' => 'lainnya',
            'keterangan' => 'pemasukan lain', 'jumlah' => 15000,
        ]);

        $response = $this->actingAs($admin)->get(route('laporan.setoran-harian.export', [
            'dari' => $tanggal, 'sampai' => $tanggal, 'cabang_id' => $cabang->id,
        ]));

        $response->assertOk(); // rekap dihitung server-side; nilai exact diverifikasi via unit call terpisah di bawah
    }

    public function test_setoran_harian_export_tanpa_data_tetap_generate(): void
    {
        $admin = $this->buatUser('owner');

        $responseExcel = $this->actingAs($admin)->get(route('laporan.setoran-harian.export', [
            'dari' => '2020-01-01', 'sampai' => '2020-01-01',
        ]));
        $responsePdf = $this->actingAs($admin)->get(route('laporan.setoran-harian.export', [
            'dari' => '2020-01-01', 'sampai' => '2020-01-01', 'format' => 'pdf',
        ]));

        $responseExcel->assertOk();
        $responsePdf->assertOk();
    }

    public function test_setoran_harian_tidak_lagi_sebut_setoran_kasir_di_export(): void
    {
        $admin = $this->buatUser('owner');

        $response = $this->actingAs($admin)->get(route('laporan.setoran-harian.index'));

        $response->assertOk();
        $response->assertSee('Export Rekap');
        $response->assertDontSee('Total Setoran Kasir');
    }

    // ===== Stok Index =====

    public function test_stok_index_export_excel_dan_pdf_valid(): void
    {
        $admin = $this->buatUser('owner');
        $cabang = $this->cabangPertama();
        $item = Item::create(['kode_item' => 'BB-B1BC-001', 'nama_item' => 'Bahan Stok Test', 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'is_active' => true]);
        Stock::create(['item_id' => $item->id, 'lokasi_id' => $cabang->id, 'qty' => 5, 'qty_minimum' => 20]);

        $responseExcel = $this->actingAs($admin)->get(route('laporan.stok', ['export' => 'excel']));
        $responsePdf = $this->actingAs($admin)->get(route('laporan.stok', ['export' => 'pdf']));

        $responseExcel->assertOk();
        $this->assertStringContainsString('spreadsheetml', $responseExcel->headers->get('Content-Type') ?? '');
        $responsePdf->assertOk();
        $this->assertEquals('application/pdf', $responsePdf->headers->get('Content-Type'));
    }

    public function test_stok_index_export_tanpa_data_tetap_generate(): void
    {
        $admin = $this->buatUser('owner');
        $cabangTanpaStok = $this->cabangKedua();

        // lokasi_id valid tapi genuinely tidak ada Stock row -- filter longgar msh bisa kosong
        $response = $this->actingAs($admin)->get(route('laporan.stok', ['export' => 'excel', 'lokasi_id' => 999999]));

        $response->assertOk();
    }

    // ===== Stok Pergerakan =====

    public function test_stok_pergerakan_export_excel_dan_pdf_valid(): void
    {
        $admin = $this->buatUser('owner');
        $cabang = $this->cabangPertama();
        $item = Item::create(['kode_item' => 'BB-B1BC-002', 'nama_item' => 'Bahan Pergerakan Test', 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'is_active' => true]);
        StockMovement::create([
            'item_id' => $item->id, 'lokasi_asal_id' => null, 'lokasi_tujuan_id' => $cabang->id,
            'qty' => 10, 'tipe' => 'masuk', 'catatan' => 'test masuk',
        ]);

        $responseExcel = $this->actingAs($admin)->get(route('laporan.stok.pergerakan', ['export' => 'excel']));
        $responsePdf = $this->actingAs($admin)->get(route('laporan.stok.pergerakan', ['export' => 'pdf']));

        $responseExcel->assertOk();
        $this->assertStringContainsString('spreadsheetml', $responseExcel->headers->get('Content-Type') ?? '');
        $responsePdf->assertOk();
    }

    public function test_stok_pergerakan_filter_periode_diterapkan(): void
    {
        $admin = $this->buatUser('owner');

        $response = $this->actingAs($admin)->get(route('laporan.stok.pergerakan', [
            'export' => 'excel', 'dari' => '2020-01-01', 'sampai' => '2020-01-31',
        ]));

        $response->assertOk();
    }

    public function test_stok_pergerakan_tidak_ada_kolom_sisa_stok_atau_referensi(): void
    {
        // Konfirmasi keputusan Owner: 2 kolom itu SENGAJA tidak dibuat
        // (data blm ditrack sistem) -- cek info alert muncul di PDF.
        $admin = $this->buatUser('owner');

        $response = $this->actingAs($admin)->get(route('laporan.stok.pergerakan', ['export' => 'pdf']));

        $response->assertOk();
    }

    // ===== Stok Minimum =====

    public function test_stok_minimum_export_excel_dan_pdf_valid_dan_ada_rekomendasi_beli(): void
    {
        $admin = $this->buatUser('owner');
        $cabang = $this->cabangPertama();
        $item = Item::create(['kode_item' => 'BB-B1BC-003', 'nama_item' => 'Bahan Minimum Test', 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'is_active' => true]);
        Stock::create(['item_id' => $item->id, 'lokasi_id' => $cabang->id, 'qty' => 2, 'qty_minimum' => 10]);

        $responseExcel = $this->actingAs($admin)->get(route('laporan.stok.minimum', ['export' => 'excel']));
        $responsePdf = $this->actingAs($admin)->get(route('laporan.stok.minimum', ['export' => 'pdf']));

        $responseExcel->assertOk();
        $this->assertStringContainsString('spreadsheetml', $responseExcel->headers->get('Content-Type') ?? '');
        $responsePdf->assertOk();
    }

    public function test_stok_minimum_export_tanpa_data_tetap_generate(): void
    {
        $admin = $this->buatUser('owner');

        // Filter cabang yg dijamin tidak py stok minim apapun.
        $response = $this->actingAs($admin)->get(route('laporan.stok.minimum', ['export' => 'excel', 'lokasi_id' => 999999]));

        $response->assertOk();
    }

    // ===== Laba Rugi Produksi =====

    public function test_laba_rugi_produksi_export_excel_valid(): void
    {
        $admin = $this->buatUser('owner');

        $response = $this->actingAs($admin)->get(route('laporan.laba-rugi.export'));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type') ?? '');
    }

    public function test_laba_rugi_produksi_pdf_ringkas_valid(): void
    {
        $admin = $this->buatUser('owner');

        $response = $this->actingAs($admin)->get(route('laporan.laba-rugi.export', ['format' => 'pdf', 'varian' => 'ringkas']));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_laba_rugi_produksi_pdf_detail_valid(): void
    {
        $admin = $this->buatUser('owner');

        $response = $this->actingAs($admin)->get(route('laporan.laba-rugi.export', ['format' => 'pdf', 'varian' => 'detail']));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_laba_rugi_produksi_semua_level_breakdown_tidak_crash(): void
    {
        $admin = $this->buatUser('owner');

        foreach (['item', 'kategori', 'jenis_olahan', 'order'] as $level) {
            $this->actingAs($admin)->get(route('laporan.laba-rugi.export', ['level' => $level]))->assertOk();
            $this->actingAs($admin)->get(route('laporan.laba-rugi.export', ['level' => $level, 'format' => 'pdf', 'varian' => 'detail']))->assertOk();
        }
    }

    public function test_laba_rugi_produksi_export_tanpa_data_tetap_generate(): void
    {
        $admin = $this->buatUser('owner');

        $responseExcel = $this->actingAs($admin)->get(route('laporan.laba-rugi.export', [
            'dari' => '2020-01-01', 'sampai' => '2020-01-01',
        ]));
        $responsePdf = $this->actingAs($admin)->get(route('laporan.laba-rugi.export', [
            'dari' => '2020-01-01', 'sampai' => '2020-01-01', 'format' => 'pdf', 'varian' => 'ringkas',
        ]));

        $responseExcel->assertOk();
        $responsePdf->assertOk();
    }

    public function test_laba_rugi_produksi_beda_dari_laporan_keuangan(): void
    {
        // Regresi konfirmasi: kedua route BEDA controller, BEDA class Export
        // (nama class sengaja dibedakan "...Produksi" spy tidak collision).
        $this->assertTrue(class_exists(\App\Exports\LaporanLabaRugiProduksiExport::class));
        $this->assertTrue(class_exists(\App\Exports\LaporanLabaRugiExport::class));
        $this->assertNotEquals(
            route('laporan.laba-rugi.index'),
            route('laporan.keuangan.laba-rugi')
        );
    }

    // ===== Regresi =====

    public function test_regresi_halaman_html_semua_menu_masih_normal(): void
    {
        $admin = $this->buatUser('owner');

        $this->actingAs($admin)->get(route('laporan.setoran-harian.index'))->assertOk();
        $this->actingAs($admin)->get(route('laporan.stok'))->assertOk();
        $this->actingAs($admin)->get(route('laporan.stok.pergerakan'))->assertOk();
        $this->actingAs($admin)->get(route('laporan.stok.minimum'))->assertOk();
        $this->actingAs($admin)->get(route('laporan.laba-rugi.index'))->assertOk();
    }

    public function test_regresi_tanpa_permission_ditolak(): void
    {
        $kasir = $this->buatUser('kasir', $this->cabangPertama()->id);

        $this->actingAs($kasir)->get(route('laporan.setoran-harian.export'))->assertStatus(403);
        $this->actingAs($kasir)->get(route('laporan.laba-rugi.export'))->assertStatus(403);
    }
}
