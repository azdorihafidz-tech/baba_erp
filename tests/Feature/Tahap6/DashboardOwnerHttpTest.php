<?php

namespace Tests\Feature\Tahap6;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use App\Models\Setoran;
use App\Models\Stock;
use App\Services\PenjualanService;
use App\Services\SetoranKasirService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

class DashboardOwnerHttpTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function cabangOperasional(): Cabang
    {
        return Cabang::where('tipe', 'cabang')->orderBy('id')->firstOrFail();
    }

    private function kasCabang(Cabang $cabang, float $saldo = 0): Kas
    {
        return Kas::create([
            'cabang_id' => $cabang->id, 'nama_kas' => 'Kas Tunai ' . $cabang->nama_cabang,
            'tipe_kas' => 'tunai', 'default_untuk' => 'tunai',
            'saldo_awal' => $saldo, 'saldo_sekarang' => $saldo, 'is_active' => true,
        ]);
    }

    private function buatOrderTunai(Cabang $cabang, int $kasirId, float $hargaTotal = 12000): void
    {
        $item = Item::where('kode_item', 'PJ-MNM-001')->firstOrFail();
        Stock::updateOrCreate(['item_id' => $item->id, 'lokasi_id' => $cabang->id], ['qty' => 100, 'qty_minimum' => 0]);
        $kas = Kas::where('cabang_id', $cabang->id)->where('default_untuk', 'tunai')->first();
        $qty = $hargaTotal / $item->harga_jual;

        app(PenjualanService::class)->buatOrder([
            'kasir_id' => $kasirId, 'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway',
            'nama_pelanggan' => 'Test QA',
            'items' => [['item_id' => $item->id, 'qty' => $qty, 'harga_satuan' => $item->harga_jual, 'satuan' => $item->satuan, 'nama_item' => $item->nama_item]],
            'payments' => [['metode' => 'tunai', 'jumlah' => $hargaTotal, 'kas_id' => $kas->id]],
            'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => $hargaTotal,
        ], $cabang->id);
    }

    public function test_dashboard_pusat_render_tanpa_error_dengan_data(): void
    {
        $cabang = $this->cabangOperasional();
        $this->kasCabang($cabang, 50000);
        $kasir = $this->buatUser('kasir', $cabang->id);
        $this->buatOrderTunai($cabang, $kasir->id);

        $admin = $this->buatUser('admin_pusat');
        $response = $this->actingAs($admin)->get('/dashboard/pusat');

        $response->assertOk();
        $response->assertViewIs('dashboard.pusat');
        $response->assertSee('Total Penjualan Hari Ini');
        $response->assertSee('Uang Belum Disetor');
        $response->assertSee('Kas HO Saat Ini');
    }

    public function test_widget_total_penjualan_hari_ini_akurat(): void
    {
        $cabang = $this->cabangOperasional();
        $this->kasCabang($cabang, 0);
        $kasir = $this->buatUser('kasir', $cabang->id);
        $this->buatOrderTunai($cabang, $kasir->id, 12000);
        $this->buatOrderTunai($cabang, $kasir->id, 6000);

        $admin = $this->buatUser('admin_pusat');
        $response = $this->actingAs($admin)->get('/dashboard/pusat');

        $response->assertOk();
        $data = $response->viewData('dashboardOwner');
        $this->assertEquals(18000, $data['totalOmzetHariIni']);
    }

    public function test_widget_uang_belum_disetor_dan_kas_ho_akurat(): void
    {
        $cabang = $this->cabangOperasional();
        $this->kasCabang($cabang, 0);
        $ho = Cabang::where('tipe', 'gudang_pusat')->firstOrFail();
        $this->kasCabang($ho, 200000);
        $kasir = $this->buatUser('kasir', $cabang->id);
        $this->buatOrderTunai($cabang, $kasir->id, 12000);

        app(SetoranKasirService::class)->submitSetoran([
            'cabang_id' => $cabang->id, 'tanggal' => now()->format('Y-m-d'), 'user_id' => $kasir->id, 'total_disetor' => 12000,
        ]);

        $admin = $this->buatUser('admin_pusat');
        $response = $this->actingAs($admin)->get('/dashboard/pusat');
        $data = $response->viewData('dashboardOwner');

        $this->assertEquals(12000, $data['uangBelumDisetor']);
        $this->assertEquals(200000, $data['kasHoSaldo']);
        $this->assertCount(1, $data['setoranPending']);
    }

    public function test_grafik_7_hari_json_compatible_chartjs(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $response = $this->actingAs($admin)->get('/dashboard/pusat');
        $data = $response->viewData('dashboardOwner');

        $this->assertCount(7, $data['grafik7HariLabels']);
        $this->assertCount(7, $data['grafik7HariData']);
        $this->assertIsFloat($data['grafik7HariData'][0]);
    }

    public function test_filter_status_mempengaruhi_laporan_setoran_kasir(): void
    {
        $cabang = $this->cabangOperasional();
        $this->kasCabang($cabang, 0);
        $ho = Cabang::where('tipe', 'gudang_pusat')->firstOrFail();
        $this->kasCabang($ho, 0);
        $kasir = $this->buatUser('kasir', $cabang->id);
        $this->buatOrderTunai($cabang, $kasir->id, 12000);
        $setoran = app(SetoranKasirService::class)->submitSetoran([
            'cabang_id' => $cabang->id, 'tanggal' => now()->format('Y-m-d'), 'user_id' => $kasir->id, 'total_disetor' => 12000,
        ]);

        $admin = $this->buatUser('admin_pusat');
        app(SetoranKasirService::class)->approveSetoran($setoran, $admin->id);

        $responseMenunggu = $this->actingAs($admin)->get('/laporan/setoran-kasir?status=menunggu');
        $responseMenunggu->assertOk();
        $this->assertSame(0, $responseMenunggu->viewData('setorans')->total());

        $responseApproved = $this->actingAs($admin)->get('/laporan/setoran-kasir?status=approved');
        $responseApproved->assertOk();
        $this->assertSame(1, $responseApproved->viewData('setorans')->total());
    }

    public function test_export_laporan_setoran_kasir_berhasil(): void
    {
        // Bug fix Sprint 3 Batch 1a (2026-09-21): export ganti dari CSV
        // manual (fputcsv, .xls palsu) jadi xlsx sungguhan via
        // maatwebsite/excel -- Content-Type ikut berubah sesuai spesifikasi
        // xlsx asli (lihat CLAUDE.md, perubahan disengaja bukan regresi).
        $admin = $this->buatUser('admin_pusat');
        $response = $this->actingAs($admin)->get('/laporan/setoran-kasir/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_manajer_cabang_tidak_lihat_widget_dashboard_owner(): void
    {
        $cabang = $this->cabangOperasional();
        $manajer = $this->buatUser('manajer_cabang', $cabang->id);

        $response = $this->actingAs($manajer)->withSession(['active_cabang_id' => $cabang->id])->get('/dashboard');

        $response->assertRedirect(route('dashboard.cabang'));
        $followed = $this->actingAs($manajer)->withSession(['active_cabang_id' => $cabang->id])->get('/dashboard/cabang');
        $followed->assertOk();
        $followed->assertDontSee('Uang Belum Disetor');
    }

    // ===== Regresi =====

    public function test_regresi_dashboard_cabang_tetap_render_dengan_widget_tipe_transaksi(): void
    {
        $cabang = $this->cabangOperasional();
        $kasir = $this->buatUser('kasir', $cabang->id);

        $response = $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])->get('/dashboard/cabang');

        $response->assertOk();
        $response->assertSee('Dine-in', false);
        $response->assertSee('Takeaway', false);
        $response->assertDontSee('jasa giling');
    }

    public function test_regresi_dashboard_gudang_tetap_normal(): void
    {
        $ho = Cabang::where('tipe', 'gudang_pusat')->firstOrFail();
        $admin = $this->buatUser('admin_gudang', $ho->id);

        $response = $this->actingAs($admin)->withSession(['active_cabang_id' => $ho->id])->get('/dashboard/gudang');

        $response->assertOk();
    }

    public function test_regresi_pos_tetap_normal(): void
    {
        $cabang = $this->cabangOperasional();
        $this->kasCabang($cabang);
        $kasir = $this->buatUser('kasir', $cabang->id);

        $response = $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])->get('/penjualan/pos');

        $response->assertOk();
    }
}
