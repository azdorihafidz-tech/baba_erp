<?php

namespace Tests\Feature\Tahap7;

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

/**
 * Tahap 7 D'mentai — Final E2E sebelum go-live. 4 alur bisnis riil, full
 * HTTP Feature Test (bukan Tinker), DB disposable erp_dimsum_test.
 */
class EndToEndFlowTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;
/** Tahap B 4.38: saklar wajib bukti default aktif — test ini menguji hal lain (bukan bukti), jadi dimatikan. */    protected function setUp(): void    {        parent::setUp();        Cabang::query()->update(['wajib_bukti_transfer' => false, 'wajib_bukti_qris' => false]);    }

    private function cabangOperasional(): Cabang
    {
        return Cabang::where('tipe', 'cabang')->orderBy('id')->firstOrFail();
    }

    private function hoCabang(): Cabang
    {
        return Cabang::where('tipe', 'gudang_pusat')->firstOrFail();
    }

    /** Stok item + (kalau ada resep) semua bahan bakunya, biar aman diproses dalam qty besar. */
    private function stokAmanUntukItem(Item $item, int $cabangId, float $qty = 1000): void
    {
        Stock::updateOrCreate(['item_id' => $item->id, 'lokasi_id' => $cabangId], ['qty' => $qty, 'qty_minimum' => 0]);

        if ($item->resep) {
            foreach ($item->resep->items as $resepItem) {
                if (! $resepItem->item_id) continue;
                Stock::updateOrCreate(['item_id' => $resepItem->item_id, 'lokasi_id' => $cabangId], ['qty' => $qty, 'qty_minimum' => 0]);
            }
        }
    }

    private function kasCabang(Cabang $cabang, float $saldo = 0): Kas
    {
        return Kas::firstOrCreate(
            ['cabang_id' => $cabang->id, 'default_untuk' => 'tunai'],
            ['nama_kas' => 'Kas Tunai ' . $cabang->nama_cabang, 'tipe_kas' => 'tunai', 'saldo_awal' => $saldo, 'saldo_sekarang' => $saldo, 'is_active' => true]
        );
    }

    // ===== ALUR 1: Kasir Jualan Sampai Setor =====

    public function test_alur1_kasir_jualan_sampai_setor(): void
    {
        $cabang = $this->cabangOperasional();
        $this->kasCabang($cabang, 0);
        Kas::firstOrCreate(
            ['cabang_id' => $cabang->id, 'default_untuk' => 'qris'],
            ['nama_kas' => 'Kas QRIS ' . $cabang->nama_cabang, 'tipe_kas' => 'bank', 'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true]
        );
        $kasir = $this->buatUser('kasir', $cabang->id);

        $itemDineIn = Item::where('kode_item', 'PJ-DIM-001')->firstOrFail(); // varian
        $itemTakeaway = Item::where('kode_item', 'PJ-MNM-001')->firstOrFail();
        $itemFrozen = Item::where('kode_item', 'PJ-FRZ-001')->firstOrFail();
        $itemTambahan = Item::where('kode_item', 'TB-TMB-001')->firstOrFail(); // garpu gratis
        foreach ([$itemDineIn, $itemTakeaway, $itemFrozen] as $it) {
            $this->stokAmanUntukItem($it, $cabang->id);
        }
        $kas = Kas::where('cabang_id', $cabang->id)->where('default_untuk', 'tunai')->first();
        $service = app(PenjualanService::class);

        // Order 1: Dine-in + item tambahan, tunai penuh
        $total1 = $itemDineIn->harga_jual + 0; // garpu gratis
        $order1 = $service->buatOrder([
            'kasir_id' => $kasir->id, 'tipe_order' => 'penjualan', 'tipe_transaksi' => 'dine_in', 'nomor_meja' => '5',
            'nama_pelanggan' => 'Pelanggan Dine-in',
            'items' => [
                ['item_id' => $itemDineIn->id, 'qty' => 1, 'harga_satuan' => $itemDineIn->harga_jual, 'satuan' => $itemDineIn->satuan, 'nama_item' => $itemDineIn->nama_item],
                ['item_id' => $itemTambahan->id, 'qty' => 1, 'harga_satuan' => 0, 'satuan' => $itemTambahan->satuan, 'nama_item' => $itemTambahan->nama_item],
            ],
            'payments' => [['metode' => 'tunai', 'jumlah' => $total1, 'kas_id' => $kas->id]],
            'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => $total1,
        ], $cabang->id);
        $this->assertNotNull($order1);

        // Order 2: Takeaway, SPLIT PAYMENT tunai + qris
        $total2 = $itemTakeaway->harga_jual * 2;
        $tunaiBagian = $total2 / 2;
        $qrisBagian = $total2 - $tunaiBagian;
        $order2 = $service->buatOrder([
            'kasir_id' => $kasir->id, 'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway',
            'nama_pelanggan' => 'Pelanggan Takeaway',
            'items' => [['item_id' => $itemTakeaway->id, 'qty' => 2, 'harga_satuan' => $itemTakeaway->harga_jual, 'satuan' => $itemTakeaway->satuan, 'nama_item' => $itemTakeaway->nama_item]],
            'payments' => [
                ['metode' => 'tunai', 'jumlah' => $tunaiBagian, 'kas_id' => $kas->id],
                ['metode' => 'qris', 'jumlah' => $qrisBagian, 'kas_id' => $kas->id],
            ],
            'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => $total2,
        ], $cabang->id);
        $this->assertNotNull($order2);

        // Order 3: Frozen, tunai
        $total3 = $itemFrozen->harga_jual;
        $order3 = $service->buatOrder([
            'kasir_id' => $kasir->id, 'tipe_order' => 'penjualan', 'tipe_transaksi' => 'frozen',
            'nama_pelanggan' => 'Pelanggan Frozen',
            'items' => [['item_id' => $itemFrozen->id, 'qty' => 1, 'harga_satuan' => $itemFrozen->harga_jual, 'satuan' => $itemFrozen->satuan, 'nama_item' => $itemFrozen->nama_item]],
            'payments' => [['metode' => 'tunai', 'jumlah' => $total3, 'kas_id' => $kas->id]],
            'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => $total3,
        ], $cabang->id);
        $this->assertNotNull($order3);

        $totalTunaiSistem = $total1 + $tunaiBagian + $total3;

        // Submit setoran via HTTP asli
        $response = $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])
            ->post('/setoran-kasir', ['tanggal' => now()->format('Y-m-d'), 'total_disetor' => $totalTunaiSistem]);
        $response->assertRedirect();

        $setoran = Setoran::where('cabang_id', $cabang->id)->firstOrFail();
        $this->assertEquals($totalTunaiSistem, $setoran->total_disetor);
        $detailTunai = $setoran->details()->where('metode', 'tunai')->first();
        $this->assertEquals($totalTunaiSistem, $detailTunai->jumlah_sistem, 'Total setoran tunai HARUS = SUM tunai dari 3 order (termasuk porsi tunai split payment).');
    }

    // ===== ALUR 2: HO Approval + Dashboard Update =====

    public function test_alur2_ho_approval_dan_dashboard_update(): void
    {
        $cabang = $this->cabangOperasional();
        $kasCabang = $this->kasCabang($cabang, 100000);
        $ho = $this->hoCabang();
        $kasHo = $this->kasCabang($ho, 500000);
        $kasir = $this->buatUser('kasir', $cabang->id);

        $item = Item::where('kode_item', 'PJ-MNM-001')->firstOrFail();
        $this->stokAmanUntukItem($item, $cabang->id);
        app(PenjualanService::class)->buatOrder([
            'kasir_id' => $kasir->id, 'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway',
            'nama_pelanggan' => 'QA', 'items' => [['item_id' => $item->id, 'qty' => 3, 'harga_satuan' => $item->harga_jual, 'satuan' => $item->satuan, 'nama_item' => $item->nama_item]],
            'payments' => [['metode' => 'tunai', 'jumlah' => $item->harga_jual * 3, 'kas_id' => $kasCabang->id]],
            'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => $item->harga_jual * 3,
        ], $cabang->id);

        $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])
            ->post('/setoran-kasir', ['tanggal' => now()->format('Y-m-d'), 'total_disetor' => $item->harga_jual * 3]);
        $setoran = Setoran::where('cabang_id', $cabang->id)->firstOrFail();

        $admin = $this->buatUser('admin_pusat');

        // 1. Login sebagai admin_pusat, cek setoran pending
        $listResponse = $this->actingAs($admin)->get('/setoran-kasir?status=menunggu');
        $listResponse->assertOk();
        $listResponse->assertSee($cabang->nama_cabang);

        // Dashboard SEBELUM approve
        $dashSebelum = $this->actingAs($admin)->get('/dashboard/pusat');
        $dataSebelum = $dashSebelum->viewData('dashboardOwner');
        $uangBelumSetorSebelum = $dataSebelum['uangBelumDisetor'];
        $kasHoSebelum = $dataSebelum['kasHoSaldo'];

        // 2. Approve
        $approveResponse = $this->actingAs($admin)->post("/setoran-kasir/{$setoran->id}/approve", []);
        $approveResponse->assertRedirect();

        $setoran->refresh();
        $this->assertSame('approved', $setoran->status->value);
        $this->assertNotNull($setoran->transaksi_out_id);
        $this->assertNotNull($setoran->transaksi_in_id);

        $kasCabang->refresh();
        $kasHo->refresh();
        $this->assertDatabaseHas('transaksi_keuangans', ['id' => $setoran->transaksi_out_id, 'referensi_type' => 'setoran_kasir']);
        $this->assertDatabaseHas('transaksi_keuangans', ['id' => $setoran->transaksi_in_id, 'referensi_type' => 'setoran_kasir']);

        // 3. Dashboard SESUDAH approve — widget berubah
        $dashSesudah = $this->actingAs($admin)->get('/dashboard/pusat');
        $dataSesudah = $dashSesudah->viewData('dashboardOwner');

        $this->assertLessThan($uangBelumSetorSebelum, $dataSesudah['uangBelumDisetor'], 'Uang Belum Disetor harus BERKURANG setelah approve.');
        $this->assertGreaterThan($kasHoSebelum, $dataSesudah['kasHoSaldo'], 'Kas HO harus BERTAMBAH setelah approve.');
    }

    // ===== ALUR 3: Permission Per Role =====

    public static function roleMenuProvider(): array
    {
        return [
            'admin_pusat bisa akses dashboard pusat' => ['admin_pusat', '/dashboard/pusat', 200],
            'admin_gudang bisa akses dashboard gudang' => ['admin_gudang', '/dashboard/gudang', 200],
            'kasir TIDAK bisa akses setoran-kasir approve list HO-only aksi' => ['kasir', '/master/produk-jual', 403],
            'operator_produksi TIDAK bisa akses master produk jual create' => ['operator_produksi', '/master/produk-jual/create', 403],
            'helper TIDAK bisa akses setoran-kasir' => ['helper', '/setoran-kasir', 403],
        ];
    }

    /** @dataProvider roleMenuProvider */
    public function test_alur3_permission_per_role(string $role, string $url, int $expectedStatus): void
    {
        $cabang = $role === 'admin_gudang' ? $this->hoCabang() : $this->cabangOperasional();
        $user = $this->buatUser($role, $cabang->id);

        $response = $this->actingAs($user)->withSession(['active_cabang_id' => $cabang->id])->get($url);

        $response->assertStatus($expectedStatus);
    }

    public function test_alur3_kasir_bisa_akses_pos_dan_setoran_kasir_sendiri(): void
    {
        $cabang = $this->cabangOperasional();
        $kasir = $this->buatUser('kasir', $cabang->id);

        $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])->get('/penjualan/pos')->assertOk();
        $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])->get('/setoran-kasir')->assertOk();
    }

    public function test_alur3_owner_bypass_semua_permission(): void
    {
        $owner = $this->buatUser('owner');

        $this->actingAs($owner)->get('/master/produk-jual')->assertOk();
        $this->actingAs($owner)->get('/coa')->assertOk();
        $this->actingAs($owner)->get('/setoran-kasir')->assertOk();
    }

    // ===== ALUR 4: Cara Pakai + Panduan Consistency =====

    public static function halamanPanduanProvider(): array
    {
        return [
            ['/master/bahan-baku', 'bahan-baku'],
            ['/master/produk-jual', 'produk-jual'],
            ['/setoran-kasir', 'setoran-kasir'],
            ['/laporan/setoran-kasir', 'laporan-setoran-kasir'],
        ];
    }

    /** @dataProvider halamanPanduanProvider */
    public function test_alur4_tombol_cara_pakai_dan_modal_konsisten(string $url, string $slug): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get($url);

        $response->assertOk();
        $modalId = 'panduanModal-' . \Illuminate\Support\Str::slug($slug);
        $response->assertSee('data-bs-target="#' . $modalId . '"', false);

        $panduan = \App\Models\Panduan::getBySlug($slug);
        $this->assertNotNull($panduan, "Panduan slug '{$slug}' harus ada di DB.");
        $response->assertSee(e($panduan->judul), false);
    }

    public function test_alur4_dashboard_owner_punya_tombol_cara_pakai(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/dashboard/pusat');

        $response->assertOk();
        $response->assertSee('data-bs-target="#panduanModal-dashboard-owner"', false);
    }
}
