<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCabang;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Database\Seeders\Perbaikan438PanduanSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap C (CLAUDE.md 4.38, 2026-10-18) — PO cabang ke supplier internal
 * (Gudang Pusat) tampil di POS gudang & mengisi keranjang.
 */
class PoGudangTahapCTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $gudang;
    private Cabang $outlet;
    private Supplier $supGudang;
    private Supplier $supLuar;
    private Item $produk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gudang = Cabang::create(['nama_cabang' => 'Gudang C ' . uniqid(), 'kode_cabang' => 'GC' . rand(1000, 9999), 'tipe' => 'gudang_pusat', 'is_active' => true, 'dine_in_aktif' => true, 'takeaway_aktif' => true, 'frozen_aktif' => true]);
        $this->outlet = Cabang::create(['nama_cabang' => 'Outlet C ' . uniqid(), 'kode_cabang' => 'OC' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true, 'dine_in_aktif' => true, 'takeaway_aktif' => true, 'frozen_aktif' => true]);
        $this->supGudang = Supplier::create(['kode_supplier' => 'SG' . rand(1000, 9999), 'nama_supplier' => 'Gudang Pusat Test', 'is_active' => true, 'cabang_id' => $this->gudang->id]);
        $this->supLuar = Supplier::create(['kode_supplier' => 'SL' . rand(1000, 9999), 'nama_supplier' => 'Toko Luar Test', 'is_active' => true]);
        $this->produk = Item::create(['kode_item' => 'PJC-' . uniqid(), 'nama_item' => 'Dimsum Frozen C', 'tipe' => 'produk_jual', 'satuan' => 'pack', 'harga_jual' => 150, 'is_active' => true]);
        ItemCabang::create(['item_id' => $this->produk->id, 'cabang_id' => $this->gudang->id, 'harga_override' => 150, 'is_active' => true]);
    }

    private function po(Supplier $s, string $status = 'disetujui', float $harga = 150, float $qty = 10): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'cabang_id' => $this->outlet->id, 'supplier_id' => $s->id, 'nomor_po' => 'PO-C-' . uniqid(),
            'tanggal_po' => now()->toDateString(), 'status' => $status, 'total_harga' => $qty * $harga,
        ]);
        PurchaseOrderItem::create(['purchase_order_id' => $po->id, 'item_id' => $this->produk->id, 'qty_pesan' => $qty, 'harga_satuan' => $harga, 'total_harga' => $qty * $harga]);

        return $po;
    }

    private function postPo(Supplier $s)
    {
        return $this->actingAs($this->buatUser('admin_pusat'))->post(route('pembelian.store'), [
            'supplier_id' => $s->id, 'tanggal_po' => now()->toDateString(), 'cabang_id' => $this->outlet->id,
            'items' => [['item_id' => $this->produk->id, 'qty_pesan' => 5, 'harga_satuan' => 150]],
        ]);
    }

    public function test_po_produk_gudang_boleh_ke_supplier_internal_tapi_ditolak_ke_supplier_luar(): void
    {
        $this->postPo($this->supGudang)->assertRedirect(route('pembelian.index'));
        $this->assertTrue(PurchaseOrder::where('supplier_id', $this->supGudang->id)->exists());

        $this->postPo($this->supLuar)->assertSessionHasErrors('items');
        $this->assertFalse(PurchaseOrder::where('supplier_id', $this->supLuar->id)->exists());
    }

    public function test_po_ditolak_kalau_produk_tidak_dijual_di_gudang(): void
    {
        ItemCabang::where('item_id', $this->produk->id)->where('cabang_id', $this->gudang->id)->update(['is_active' => false]);

        $this->postPo($this->supGudang)->assertSessionHasErrors('items');
    }

    public function test_pos_gudang_menampilkan_po_masuk_yang_belum_diproses(): void
    {
        $siap = $this->po($this->supGudang, 'disetujui');
        $draft = $this->po($this->supGudang, 'draft');
        $luar = $this->po($this->supLuar, 'disetujui');
        $kasir = $this->buatUser('kasir', $this->gudang->id);

        $html = $this->actingAs($kasir)->withSession(['active_cabang_id' => $this->gudang->id])
            ->get(route('penjualan.pos'))->assertOk()->getContent();

        $this->assertStringContainsString('Pesanan PO dari Cabang', $html);
        $this->assertStringContainsString($siap->nomor_po, $html);
        $this->assertStringNotContainsString($draft->nomor_po, $html);
        $this->assertStringNotContainsString($luar->nomor_po, $html);
        $this->assertStringContainsString('isiDariPo(' . $siap->id . ')', $html);
        $this->assertStringContainsString('name="purchase_order_id"', $html);

        // Outlet (bukan supplier internal) tidak melihat panel
        $kasirOutlet = $this->buatUser('kasir', $this->outlet->id);
        $this->actingAs($kasirOutlet)->withSession(['active_cabang_id' => $this->outlet->id])
            ->get(route('penjualan.pos'))->assertOk()->assertDontSee('onclick="isiDariPo(', false);
    }

    public function test_nota_tersimpan_dengan_tautan_po_dan_po_hilang_dari_panel(): void
    {
        $po = $this->po($this->supGudang, 'dikirim_supplier');
        $kasir = $this->buatUser('kasir', $this->gudang->id);
        app(\App\Services\StokService::class)->masuk($this->produk->id, $this->gudang->id, 50, 'stok produksi', null, null, 80);

        $this->actingAs($kasir)->withSession(['active_cabang_id' => $this->gudang->id])
            ->postJson(route('penjualan.simpan-bill'), [
                'tipe_transaksi' => 'takeaway', 'nama_pelanggan' => $this->outlet->nama_cabang, 'purchase_order_id' => $po->id,
                'items' => [['item_id' => $this->produk->id, 'nama_item' => 'Dimsum Frozen C', 'qty' => 10, 'satuan' => 'pack', 'harga_satuan' => 150]],
            ])->assertOk();

        $order = Order::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($order);
        $this->assertSame($this->gudang->id, (int) $order->cabang_id);

        $this->actingAs($kasir)->withSession(['active_cabang_id' => $this->gudang->id])
            ->get(route('penjualan.pos'))->assertOk()->assertDontSee('isiDariPo(' . $po->id . ')', false);

        // Nota dibatalkan → PO muncul lagi
        $order->update(['status' => 'dibatalkan']);
        $this->actingAs($kasir)->withSession(['active_cabang_id' => $this->gudang->id])
            ->get(route('penjualan.pos'))->assertOk()->assertSee('isiDariPo(' . $po->id . ')', false);
    }

    public function test_tautan_po_diabaikan_kalau_po_bukan_untuk_lokasi_ini(): void
    {
        $po = $this->po($this->supGudang);
        $kasirOutlet = $this->buatUser('kasir', $this->outlet->id);

        $this->actingAs($kasirOutlet)->withSession(['active_cabang_id' => $this->outlet->id])
            ->postJson(route('penjualan.simpan-bill'), [
                'tipe_transaksi' => 'takeaway', 'purchase_order_id' => $po->id,
                'items' => [['nama_item' => 'Custom', 'qty' => 1, 'satuan' => 'pcs', 'harga_satuan' => 1000]],
            ])->assertOk();

        $this->assertFalse(Order::where('purchase_order_id', $po->id)->exists());
    }

    public function test_detail_po_menampilkan_nota_gudang_dan_peringatan_beda_total(): void
    {
        $this->seed(Perbaikan438PanduanSeeder::class);
        $po = $this->po($this->supGudang, 'dikirim_supplier', 140, 10); // PO Rp 1.400
        Order::create([
            'cabang_id' => $this->gudang->id, 'nomor_order' => 'NOTA-C-' . uniqid(), 'tanggal_order' => now()->toDateString(),
            'tipe_order' => 'penjualan', 'status' => 'selesai', 'total_bayar' => 1500, 'jumlah_bayar' => 1500, 'kembalian' => 0,
            'tipe_pembayaran' => 'tunai', 'purchase_order_id' => $po->id,
        ]);

        $this->actingAs($this->buatUser('owner'))->get(route('pembelian.show', $po))->assertOk()
            ->assertSee('Nota dari Gudang Pusat Test')->assertSee('Rp 1.500')->assertSee('berbeda dengan nota');
    }

    public function test_form_supplier_dan_po_membawa_tanda_internal(): void
    {
        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->get(route('supplier.edit', $this->supGudang))->assertOk()
            ->assertSee('Supplier Internal (lokasi kita)')->assertSee('name="cabang_id"', false);
        $this->actingAs($owner)->get(route('supplier.create'))->assertOk()->assertSee('Supplier Internal (lokasi kita)');

        $this->actingAs($owner)->get(route('pembelian.create'))->assertOk()
            ->assertSee('data-cabang-internal="' . $this->gudang->id . '"', false);
    }
}
