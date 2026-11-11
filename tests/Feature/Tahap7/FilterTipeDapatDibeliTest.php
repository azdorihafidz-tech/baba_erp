<?php

namespace Tests\Feature\Tahap7;

use App\Enums\TipeCabang;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\DatabaseTransactions;


use Illuminate\Support\Str;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Sprint 4.33 (2026-10-08) — Filter tipe item di PO, StockTransfer, StockRequest.
 *
 * Bug: `produk_jual`/`produk_tambahan` muncul di dropdown "pilih item" ketiga
 * form itu, padahal keduanya dibuat on-demand lewat resep (bukan dibeli dari
 * supplier / tidak di-transfer antar cabang) — bisa menyebabkan dobel-count
 * stok kalau terpilih tidak sengaja.
 *
 * Fix: filter whitelist `Item::TIPE_DAPAT_DIBELI` (bahan_baku, kemasan,
 * tambahan_gratis) di query dropdown + guard rail di store() backend.
 */
class FilterTipeDapatDibeliTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    protected function setUp(): void
    {
        parent::setUp();
        if (Cabang::count() < 2) {
            Cabang::firstOrCreate(['kode_cabang' => 'TST-FT1'], [
                'nama_cabang' => 'Cabang Test FT 1', 'tipe' => TipeCabang::Cabang, 'is_active' => true,
            ]);
            Cabang::firstOrCreate(['kode_cabang' => 'TST-FT2'], [
                'nama_cabang' => 'Cabang Test FT 2', 'tipe' => TipeCabang::Cabang, 'is_active' => true,
            ]);
        }
    }

    private function supplier(string $nama): Supplier
    {
        return Supplier::firstOrCreate(
            ['nama_supplier' => $nama],
            ['kode_supplier' => 'SUP-' . strtoupper(Str::random(6)), 'is_active' => true],
        );
    }

    private function itemAktif(string $tipe, string $nama): Item
    {
        return Item::create([
            'kode_item' => 'FT-' . strtoupper(Str::random(6)),
            'nama_item' => $nama,
            'tipe'      => $tipe,
            'satuan'    => 'pcs',
            'is_active' => true,
        ]);
    }

    public function test_constant_tipe_dapat_dibeli_isi_benar(): void
    {
        $this->assertSame(['bahan_baku', 'kemasan', 'tambahan_gratis'], Item::TIPE_DAPAT_DIBELI);
    }

    public function test_scope_dapat_dibeli_filter_benar(): void
    {
        $bb = $this->itemAktif('bahan_baku', 'FT Tepung');
        $km = $this->itemAktif('kemasan', 'FT Box');
        $tg = $this->itemAktif('tambahan_gratis', 'FT Sumpit');
        $pj = $this->itemAktif('produk_jual', 'FT Dimsum');
        $pt = $this->itemAktif('produk_tambahan', 'FT Saus Extra');

        $ids = Item::dapatDibeli()->whereIn('id', [$bb->id, $km->id, $tg->id, $pj->id, $pt->id])->pluck('id')->all();

        $this->assertContains($bb->id, $ids);
        $this->assertContains($km->id, $ids);
        $this->assertContains($tg->id, $ids);
        $this->assertNotContains($pj->id, $ids);
        $this->assertNotContains($pt->id, $ids);
    }

    public function test_po_create_dropdown_exclude_produk_jual(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $bb = $this->itemAktif('bahan_baku', 'FT-PO Tepung Unik');
        $pj = $this->itemAktif('produk_jual', 'FT-PO Dimsum Unik');

        $response = $this->actingAs($user)->get(route('pembelian.create'));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('FT-PO Tepung Unik', $html);
        // Tahap C 4.38: produk jual ikut dimuat TAPI ditandai khusus_internal —
        // JS itemsUntukSupplier() hanya menampilkannya utk supplier internal.
        $this->assertMatchesRegularExpression('/"nama_item":"FT-PO Dimsum Unik"[^}]*"khusus_internal":true/', $html);
        $this->assertMatchesRegularExpression('/"nama_item":"FT-PO Tepung Unik"[^}]*"khusus_internal":false/', $html);
        $this->assertStringContainsString('function itemsUntukSupplier', $html);
    }

    public function test_po_store_tolak_item_produk_jual(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $pj = $this->itemAktif('produk_jual', 'FT-PO Guard Dimsum');
        $sup = $this->supplier('FT Supplier Test');

        $response = $this->actingAs($user)->post(route('pembelian.store'), [
            'supplier_id'    => $sup->id,
            'cabang_id'      => $this->cabangPertama()->id,
            'tanggal_po'     => now()->toDateString(),
            'pembelian_langsung' => '1',
            'items' => [
                ['item_id' => $pj->id, 'qty_pesan' => 10, 'harga_satuan' => 1000],
            ],
        ]);

        $response->assertSessionHasErrors(['items']);
        $this->assertDatabaseMissing('purchase_order_items', ['item_id' => $pj->id]);
    }

    public function test_po_store_terima_bahan_baku(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $bb = $this->itemAktif('bahan_baku', 'FT-PO OK Tepung');
        $sup = $this->supplier('FT Supplier Test 2');

        $response = $this->actingAs($user)->post(route('pembelian.store'), [
            'supplier_id'    => $sup->id,
            'cabang_id'      => $this->cabangPertama()->id,
            'tanggal_po'     => now()->toDateString(),
            'pembelian_langsung' => '1',
            'items' => [
                ['item_id' => $bb->id, 'qty_pesan' => 10, 'harga_satuan' => 1000],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors(['items']);
        $this->assertDatabaseHas('purchase_order_items', ['item_id' => $bb->id]);
    }

    public function test_stock_transfer_create_dropdown_exclude_produk_jual(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $this->itemAktif('bahan_baku', 'FT-ST Tepung Unik');
        $this->itemAktif('produk_jual', 'FT-ST Dimsum Unik');

        $response = $this->actingAs($user)->get(route('stock-transfer.create'));
        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('FT-ST Tepung Unik', $html);
        $this->assertStringNotContainsString('FT-ST Dimsum Unik', $html);
    }

    public function test_stock_transfer_store_tolak_item_produk_jual(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $pj = $this->itemAktif('produk_jual', 'FT-ST Guard Dimsum');

        $response = $this->actingAs($user)->post(route('stock-transfer.store'), [
            'dari_lokasi_id' => $this->cabangPertama()->id,
            'ke_lokasi_id'   => $this->cabangKedua()->id,
            'tanggal_kirim'  => now()->toDateString(),
            'items' => [
                ['item_id' => $pj->id, 'qty_kirim' => 5],
            ],
        ]);

        $this->assertDatabaseMissing('stock_transfer_items', ['item_id' => $pj->id]);
    }

    public function test_stock_request_create_dropdown_exclude_produk_jual(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        session(['active_cabang_id' => $this->cabangPertama()->id]);
        $this->itemAktif('bahan_baku', 'FT-SR Tepung Unik');
        $this->itemAktif('produk_jual', 'FT-SR Dimsum Unik');

        $response = $this->actingAs($user)->get(route('stock-request.create'));
        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('FT-SR Tepung Unik', $html);
        $this->assertStringNotContainsString('FT-SR Dimsum Unik', $html);
    }

    public function test_stock_request_store_tolak_item_produk_jual(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        session(['active_cabang_id' => $this->cabangPertama()->id]);
        $pj = $this->itemAktif('produk_jual', 'FT-SR Guard Dimsum');

        $response = $this->actingAs($user)->post(route('stock-request.store'), [
            'items' => [
                ['item_id' => $pj->id, 'qty_minta' => 5],
            ],
        ]);

        $this->assertDatabaseMissing('stock_request_items', ['item_id' => $pj->id]);
    }

    public function test_tambahan_gratis_boleh_dibeli(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $tg = $this->itemAktif('tambahan_gratis', 'FT-TG Sumpit');
        $sup = $this->supplier('FT Supplier TG');

        $response = $this->actingAs($user)->post(route('pembelian.store'), [
            'supplier_id'    => $sup->id,
            'cabang_id'      => $this->cabangPertama()->id,
            'tanggal_po'     => now()->toDateString(),
            'pembelian_langsung' => '1',
            'items' => [
                ['item_id' => $tg->id, 'qty_pesan' => 100, 'harga_satuan' => 50],
            ],
        ]);
        $response->assertSessionDoesntHaveErrors(['items']);
        $this->assertDatabaseHas('purchase_order_items', ['item_id' => $tg->id]);
    }
}
