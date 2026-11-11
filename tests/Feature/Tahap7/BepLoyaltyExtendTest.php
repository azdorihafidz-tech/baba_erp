<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\LoyaltyProgram;
use App\Models\Pelanggan;
use App\Models\Stock;
use App\Services\BepOtomatisService;
use App\Services\LoyaltyService;
use App\Services\PenjualanService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap 7 D'mentai — B1 (BepOtomatisService fallback tipe_order='penjualan')
 * + B2 (LoyaltyService::auto_track basis Rp/transaksi). Backward compat
 * (jasa_giling/kg) WAJIB tetap jalan apa adanya (data lama, kalau ada).
 */
class BepLoyaltyExtendTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function cabangOperasional(): Cabang
    {
        return Cabang::where('tipe', 'cabang')->orderBy('id')->firstOrFail();
    }

    private function buatOrderPenjualan(Cabang $cabang, int $kasirId, ?int $pelangganId, float $hargaTotal, int $qty = 1): void
    {
        $item = Item::where('kode_item', 'PJ-MNM-001')->firstOrFail();
        Stock::updateOrCreate(['item_id' => $item->id, 'lokasi_id' => $cabang->id], ['qty' => 1000, 'qty_minimum' => 0]);
        \App\Models\Kas::firstOrCreate(
            ['cabang_id' => $cabang->id, 'default_untuk' => 'tunai'],
            ['nama_kas' => 'Kas Tunai Test', 'tipe_kas' => 'tunai', 'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true]
        );
        $kas = \App\Models\Kas::where('cabang_id', $cabang->id)->where('default_untuk', 'tunai')->first();
        // 4.47 — harga di luar harga produk hanya dihormati utk pemegang izin Ubah Harga (Owner)
        $this->actingAs($this->buatUser('owner'));

        app(PenjualanService::class)->buatOrder([
            'kasir_id' => $kasirId, 'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway',
            'nama_pelanggan' => 'Test QA', 'pelanggan_id' => $pelangganId,
            'items' => [['item_id' => $item->id, 'qty' => $qty, 'harga_satuan' => $hargaTotal / $qty, 'satuan' => $item->satuan, 'nama_item' => $item->nama_item]],
            'payments' => [['metode' => 'tunai', 'jumlah' => $hargaTotal, 'kas_id' => $kas->id]],
            'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => $hargaTotal,
        ], $cabang->id);
    }

    // ===== B1: BepOtomatisService =====

    public function test_bep_otomatis_tidak_nol_untuk_order_penjualan(): void
    {
        $cabang = $this->cabangOperasional();
        $kasir = $this->buatUser('kasir', $cabang->id);
        $this->buatOrderPenjualan($cabang, $kasir->id, null, 20000, 2);

        $hasil = app(BepOtomatisService::class)->hitungBepOtomatis(now()->startOfMonth(), now()->endOfMonth(), $cabang->id);

        $this->assertGreaterThan(0, $hasil['volume_aktual'], 'Volume aktual harus > 0 untuk order penjualan D\'mentai (bug lama: selalu 0).');
        $this->assertGreaterThan(0, $hasil['total_omzet_jasa_giling']);
    }

    public function test_bep_otomatis_backward_compat_jasa_giling(): void
    {
        // Simulasikan order jasa_giling lama (raw insert, karena PenjualanService
        // D'mentai tidak lagi bisa buat order tipe ini) -- verifikasi fallback
        // LAMA tetap terpakai apa adanya kalau data historis ini ada.
        $cabang = $this->cabangOperasional();
        $orderId = \Illuminate\Support\Facades\DB::table('orders')->insertGetId([
            'cabang_id' => $cabang->id, 'nomor_order' => 'TEST-JG-001', 'tanggal_order' => now()->toDateString(),
            'tipe_order' => 'jasa_giling', 'status' => 'selesai', 'total_bayar' => 50000, 'jumlah_bayar' => 50000,
            'kembalian' => 0, 'tipe_pembayaran' => 'tunai', 'created_at' => now(), 'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('order_items')->insert([
            'order_id' => $orderId, 'nama_item' => 'Giling Daging', 'qty' => 1, 'harga_satuan' => 50000,
            'total_harga' => 50000, 'berat_daging' => 10, 'hpp' => 20000, 'satuan' => 'kg',
        ]);

        $hasil = app(BepOtomatisService::class)->hitungBepOtomatis(now()->startOfMonth(), now()->endOfMonth(), $cabang->id);

        $this->assertEquals(10, $hasil['volume_aktual'], 'Order jasa_giling ada -> harus pakai basis KG (backward compat), bukan pcs.');
        $this->assertEquals(2000, $hasil['biaya_variabel_per_unit']); // 20000/10kg
    }

    public function test_laporan_bep_otomatis_http_render_ok(): void
    {
        // laporan.bep_otomatis.view Owner-only default (tidak di-assign role
        // manapun, konsisten pola fitur laporan lain).
        $admin = $this->buatUser('owner');
        $cabang = $this->cabangOperasional();
        $kasir = $this->buatUser('kasir', $cabang->id);
        $this->buatOrderPenjualan($cabang, $kasir->id, null, 15000);

        $response = $this->actingAs($admin)->get('/laporan/bep-otomatis?cabang_id=' . $cabang->id);

        $response->assertOk();
    }

    // ===== B2: LoyaltyService basis Rp / transaksi =====

    public function test_loyalty_basis_total_bayar_bertambah(): void
    {
        $cabang = $this->cabangOperasional();
        $kasir = $this->buatUser('kasir', $cabang->id);
        $pelanggan = Pelanggan::create(['nama_pelanggan' => 'Test Loyalty', 'kode_pelanggan' => 'PEL-TEST-01', 'is_active' => true]);
        $program = LoyaltyProgram::create([
            'nama' => 'Loyalti Belanja', 'tipe_program' => 'auto_track', 'target_qty_kg' => 50000,
            'satuan_qty' => 'Rp', 'sumber_data' => 'orders.total_bayar', 'tipe_item' => 'penjualan',
            'hadiah' => 'Voucher', 'berulang' => false, 'status' => 'aktif',
        ]);

        $this->buatOrderPenjualan($cabang, $kasir->id, $pelanggan->id, 30000);

        $service = app(LoyaltyService::class);
        $progress = $service->hitungProgressPelanggan($pelanggan->id, $program->id);

        $this->assertEquals(30000, $progress['total_kg']);
        $this->assertEquals(60.0, $progress['persen_progress']);
    }

    public function test_loyalty_basis_jumlah_transaksi_bertambah(): void
    {
        $cabang = $this->cabangOperasional();
        $kasir = $this->buatUser('kasir', $cabang->id);
        $pelanggan = Pelanggan::create(['nama_pelanggan' => 'Test Loyalty 2', 'kode_pelanggan' => 'PEL-TEST-02', 'is_active' => true]);
        $program = LoyaltyProgram::create([
            'nama' => 'Loyalti Frekuensi', 'tipe_program' => 'auto_track', 'target_qty_kg' => 3,
            'satuan_qty' => 'transaksi', 'sumber_data' => 'orders.count', 'tipe_item' => 'penjualan',
            'hadiah' => 'Diskon', 'berulang' => false, 'status' => 'aktif',
        ]);

        $this->buatOrderPenjualan($cabang, $kasir->id, $pelanggan->id, 10000);
        $this->buatOrderPenjualan($cabang, $kasir->id, $pelanggan->id, 12000);

        $service = app(LoyaltyService::class);
        $progress = $service->hitungProgressPelanggan($pelanggan->id, $program->id);

        $this->assertEquals(2, $progress['total_kg']);
    }

    public function test_loyalty_basis_kg_backward_compat(): void
    {
        $pelanggan = Pelanggan::create(['nama_pelanggan' => 'Test Loyalty Kg', 'kode_pelanggan' => 'PEL-TEST-03', 'is_active' => true]);
        $program = LoyaltyProgram::create([
            'nama' => 'Loyalti Giling Lama', 'tipe_program' => 'auto_track', 'target_qty_kg' => 100,
            'satuan_qty' => 'kg', 'sumber_data' => 'orders.berat_daging_kg', 'tipe_item' => 'jasa_giling',
            'hadiah' => 'Beras', 'berulang' => false, 'status' => 'aktif',
        ]);

        \Illuminate\Support\Facades\DB::table('orders')->insert([
            'cabang_id' => $this->cabangOperasional()->id, 'nomor_order' => 'TEST-JG-LOY-001',
            'tanggal_order' => now()->toDateString(), 'tipe_order' => 'jasa_giling', 'status' => 'selesai',
            'pelanggan_id' => $pelanggan->id, 'total_bayar' => 50000, 'jumlah_bayar' => 50000, 'kembalian' => 0,
            'tipe_pembayaran' => 'tunai', 'berat_daging_kg' => 25, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $service = app(LoyaltyService::class);
        $progress = $service->hitungProgressPelanggan($pelanggan->id, $program->id);

        $this->assertEquals(25, $progress['total_kg'], 'Basis kg lama harus tetap jalan apa adanya (backward compat).');
    }

    public function test_loyalty_program_form_terima_tipe_item_penjualan(): void
    {
        $owner = $this->buatUser('owner');

        $response = $this->actingAs($owner)->post('/loyalty-program', [
            'nama' => 'Program Baru D\'mentai', 'tipe_program' => 'auto_track', 'target_qty_kg' => 100000,
            'tipe_item' => 'penjualan', 'sumber_data' => 'orders.total_bayar', 'hadiah' => 'Voucher Rp20.000',
            'status' => 'aktif',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('loyalty_programs', ['nama' => 'Program Baru D\'mentai', 'tipe_item' => 'penjualan', 'sumber_data' => 'orders.total_bayar', 'satuan_qty' => 'Rp']);
    }
}
