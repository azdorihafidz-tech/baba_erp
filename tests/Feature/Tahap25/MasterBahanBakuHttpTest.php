<?php

namespace Tests\Feature\Tahap25;

use App\Models\Item;
use App\Models\Stock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

class MasterBahanBakuHttpTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_index_200_utk_admin_pusat(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/master/bahan-baku');

        $response->assertOk();
        $response->assertViewIs('master.bahan-baku.index');
        $response->assertSee('Bahan Baku');
    }

    public function test_index_403_utk_kasir(): void
    {
        $kasir = $this->buatUser('kasir');

        $response = $this->actingAs($kasir)->get('/master/bahan-baku');

        $response->assertForbidden();
    }

    public function test_create_200(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/master/bahan-baku/create');

        $response->assertOk();
        $response->assertViewIs('master.bahan-baku.create');
    }

    public function test_store_dengan_stok_awal_tersimpan_benar(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $cabang = $this->cabangPertama();

        $response = $this->actingAs($admin)->post('/master/bahan-baku', [
            'kode_item' => 'BB-HTTP-001',
            'nama_item' => 'Bahan Uji HTTP',
            'tipe' => 'bahan_baku',
            'satuan' => 'kg',
            'harga_beli_terakhir' => '12000',
            'qty_minimum' => 5,
            'is_active' => '1',
            'stok_awal' => [$cabang->id => 30],
        ]);

        $response->assertRedirect(route('master.bahan-baku.index'));
        $response->assertSessionHas('success');

        $item = Item::where('kode_item', 'BB-HTTP-001')->first();
        $this->assertNotNull($item);
        $this->assertSame('bahan_baku', $item->tipe);

        $stock = Stock::where('item_id', $item->id)->where('lokasi_id', $cabang->id)->first();
        $this->assertNotNull($stock);
        $this->assertEquals(30, $stock->qty);
    }

    public function test_store_validasi_kode_item_wajib_unik(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->post('/master/bahan-baku', [
            'kode_item' => 'BB-BHN-001', // sudah ada
            'nama_item' => 'Duplikat',
            'tipe' => 'bahan_baku',
            'satuan' => 'kg',
        ]);

        $response->assertSessionHasErrors('kode_item');
    }

    public function test_edit_200_dan_menampilkan_data_existing(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = Item::where('kode_item', 'BB-BHN-002')->firstOrFail();

        $response = $this->actingAs($admin)->get("/master/bahan-baku/{$item->id}/edit");

        $response->assertOk();
        $response->assertSee('Isian Ayam');
    }

    public function test_update_menyimpan_perubahan(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = Item::create([
            'kode_item' => 'BB-HTTP-002', 'nama_item' => 'Sebelum Update', 'tipe' => 'kemasan',
            'satuan' => 'pcs', 'harga_beli_terakhir' => 1000, 'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put("/master/bahan-baku/{$item->id}", [
            'kode_item' => 'BB-HTTP-002',
            'nama_item' => 'Sesudah Update',
            'tipe' => 'kemasan',
            'satuan' => 'pcs',
            'harga_beli_terakhir' => '1500',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('master.bahan-baku.index'));
        $item->refresh();
        $this->assertSame('Sesudah Update', $item->nama_item);
        $this->assertEquals(1500, $item->harga_beli_terakhir);
    }

    public function test_update_ditolak_utk_item_produk_jual(): void
    {
        // Guard tipe scope: item produk_jual TIDAK boleh diedit lewat controller Bahan Baku
        $admin = $this->buatUser('admin_pusat');
        $produkJual = Item::where('kode_item', 'PJ-DIM-001')->firstOrFail();

        $response = $this->actingAs($admin)->get("/master/bahan-baku/{$produkJual->id}/edit");

        $response->assertNotFound();
    }

    public function test_destroy_soft_delete(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = Item::create([
            'kode_item' => 'BB-HTTP-003', 'nama_item' => 'Akan Dihapus', 'tipe' => 'bahan_baku',
            'satuan' => 'kg', 'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete("/master/bahan-baku/{$item->id}");

        $response->assertRedirect(route('master.bahan-baku.index'));
        $this->assertSoftDeleted('items', ['id' => $item->id]);
    }
}
