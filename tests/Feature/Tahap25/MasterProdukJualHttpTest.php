<?php

namespace Tests\Feature\Tahap25;

use App\Models\Item;
use App\Models\ItemVariant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

class MasterProdukJualHttpTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_index_200(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/master/produk-jual');

        $response->assertOk();
        $response->assertViewIs('master.produk-jual.index');
        $response->assertSee('Dimsum');
    }

    public function test_index_403_utk_kasir(): void
    {
        $kasir = $this->buatUser('kasir');

        $response = $this->actingAs($kasir)->get('/master/produk-jual');

        $response->assertForbidden();
    }

    public function test_create_200(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/master/produk-jual/create');

        $response->assertOk();
    }

    public function test_store_lengkap_foto_resep_varian_dan_outlet(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $bahan = Item::where('kode_item', 'BB-BHN-001')->firstOrFail(); // Kulit Dimsum
        $cabang1 = $this->cabangPertama();
        $cabang2 = $this->cabangKedua();
        $foto = UploadedFile::fake()->image('produk.jpg', 1200, 1200); // > 800px, uji resize

        $response = $this->actingAs($admin)->post('/master/produk-jual', [
            'kode_item' => 'PJ-HTTP-001',
            'nama_item' => 'Produk Uji HTTP',
            'tipe' => 'produk_jual',
            'satuan' => 'porsi',
            'harga_jual' => '20000',
            'is_active' => '1',
            'foto' => $foto,
            'resep' => [
                ['item_id' => $bahan->id, 'qty_per_unit' => '2', 'satuan' => 'pcs', 'is_wajib' => '1', 'mode_harga' => 'gratis'],
            ],
            'punya_varian' => '1',
            'atribut' => [['nama' => 'Size', 'nilai' => 'S, M, L']],
            'harga_override' => ['0' => '', '1' => '25000', '2' => '30000'],
            'cabang_aktif' => [$cabang1->id],
            'cabang_harga' => [$cabang1->id => '22000'],
        ]);

        $response->assertRedirect(route('master.produk-jual.index'));

        $item = Item::where('kode_item', 'PJ-HTTP-001')->first();
        $this->assertNotNull($item);
        $this->assertTrue($item->punya_varian);
        $this->assertNotNull($item->foto);
        Storage::disk('public')->assertExists($item->foto);

        // Resize: file jpg tersimpan, max dimensi 800px
        [$width, $height] = getimagesize(Storage::disk('public')->path($item->foto));
        $this->assertLessThanOrEqual(800, $width);
        $this->assertLessThanOrEqual(800, $height);

        $item->load('resep.items', 'variants.attributeValues', 'itemCabang');
        $this->assertCount(1, $item->resep->items);
        $this->assertCount(3, $item->variants);

        $mVariant = $item->variants->first(fn ($v) => $v->label === 'M');
        $this->assertEquals(25000, $mVariant->harga_override);

        // 4.39 — produk milik 1 cabang (pengganti ketersediaan per outlet); admin pusat
        // tanpa cabang aktif → Gudang Pusat. Field outlet lama diabaikan.
        $this->assertNotNull($item->cabang_id);
        $this->assertCount(0, $item->itemCabang);
    }

    public function test_store_foto_ditolak_kalau_lebih_dari_2mb(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $fotoBesar = UploadedFile::fake()->create('besar.jpg', 3000, 'image/jpeg'); // 3000 KB > 2048

        $response = $this->actingAs($admin)->post('/master/produk-jual', [
            'kode_item' => 'PJ-HTTP-002', 'nama_item' => 'Produk Foto Besar', 'tipe' => 'produk_jual',
            'satuan' => 'porsi', 'harga_jual' => '10000', 'foto' => $fotoBesar,
        ]);

        $response->assertSessionHasErrors('foto');
        $this->assertNull(Item::where('kode_item', 'PJ-HTTP-002')->first());
    }

    public function test_edit_200_menampilkan_varian_existing(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = Item::where('kode_item', 'PJ-DIM-003')->firstOrFail(); // sudah punya varian S/M/L

        $response = $this->actingAs($admin)->get("/master/produk-jual/{$item->id}/edit");

        $response->assertOk();
        $response->assertSee('Size');
    }

    public function test_update_tanpa_ubah_struktur_varian_preserve_id_dan_histori(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = Item::where('kode_item', 'PJ-DIM-003')->firstOrFail();
        $item->load('variants');
        $idSebelum = $item->variants->pluck('id')->sort()->values()->all();

        $response = $this->actingAs($admin)->put("/master/produk-jual/{$item->id}", [
            'kode_item' => $item->kode_item, 'nama_item' => $item->nama_item, 'tipe' => 'produk_jual',
            'satuan' => $item->satuan, 'harga_jual' => (string) $item->harga_jual, 'is_active' => '1',
            'punya_varian' => '1',
            'atribut' => [['nama' => 'Size', 'nilai' => 'S, M, L']],
            'harga_override' => ['0' => '12000', '1' => '15000', '2' => '18000'],
            'cabang_aktif' => \App\Models\Cabang::aktif()->pluck('id')->all(),
        ]);

        $response->assertRedirect(route('master.produk-jual.index'));

        $item->refresh();
        $idSesudah = $item->variants()->pluck('id')->sort()->values()->all();
        $this->assertSame($idSebelum, $idSesudah, 'Variant ID harus TETAP SAMA kalau struktur atribut tidak berubah — kalau berubah berarti histori order_items.item_variant_id lama bisa rusak.');
    }

    public function test_update_ubah_struktur_varian_soft_delete_bukan_hard_delete(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = Item::where('kode_item', 'PJ-DIM-003')->firstOrFail();
        $variantSSebelum = $item->variants()->get()->first(fn ($v) => $v->label === 'S');
        $this->assertNotNull($variantSSebelum);

        $response = $this->actingAs($admin)->put("/master/produk-jual/{$item->id}", [
            'kode_item' => $item->kode_item, 'nama_item' => $item->nama_item, 'tipe' => 'produk_jual',
            'satuan' => $item->satuan, 'harga_jual' => (string) $item->harga_jual, 'is_active' => '1',
            'punya_varian' => '1',
            'atribut' => [['nama' => 'Size', 'nilai' => 'M, L, XL']], // S dihapus, XL baru
            'harga_override' => ['0' => '15000', '1' => '18000', '2' => '22000'],
            'cabang_aktif' => \App\Models\Cabang::aktif()->pluck('id')->all(),
        ]);

        $response->assertRedirect();

        // S soft-deleted (BUKAN hilang permanen — histori order tetap valid)
        $this->assertSoftDeleted('item_variants', ['id' => $variantSSebelum->id]);
        $this->assertDatabaseHas('item_variants', ['id' => $variantSSebelum->id]); // baris masih ada fisik

        $item->refresh();
        $labelsAktif = $item->variants()->get()->pluck('label')->sort()->values()->all();
        $this->assertSame(['L', 'M', 'XL'], $labelsAktif);
    }

    public function test_toggle_off_varian_tidak_menghapus_data_varian_lama(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = Item::where('kode_item', 'PJ-DIM-003')->firstOrFail();
        $jumlahVariantSebelum = ItemVariant::where('item_id', $item->id)->count();

        $response = $this->actingAs($admin)->put("/master/produk-jual/{$item->id}", [
            'kode_item' => $item->kode_item, 'nama_item' => $item->nama_item, 'tipe' => 'produk_jual',
            'satuan' => $item->satuan, 'harga_jual' => (string) $item->harga_jual, 'is_active' => '1',
            'punya_varian' => '0', // di-uncheck
            'cabang_aktif' => \App\Models\Cabang::aktif()->pluck('id')->all(),
        ]);

        $response->assertRedirect();

        $item->refresh();
        $this->assertFalse($item->punya_varian);
        $this->assertEquals($jumlahVariantSebelum, ItemVariant::where('item_id', $item->id)->count(), 'Data varian lama tidak boleh terhapus cuma karena toggle off.');
    }

    public function test_hapus_foto_checkbox_menghapus_file(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $foto = UploadedFile::fake()->image('produk.jpg', 100, 100);
        $item = Item::create([
            'kode_item' => 'PJ-HTTP-003', 'nama_item' => 'Produk Ada Foto', 'tipe' => 'produk_jual',
            'satuan' => 'porsi', 'harga_jual' => 10000, 'is_active' => true,
            'foto' => $foto->store('produk', 'public'),
        ]);
        Storage::disk('public')->assertExists($item->foto);
        $fotoPathLama = $item->foto;

        $response = $this->actingAs($admin)->put("/master/produk-jual/{$item->id}", [
            'kode_item' => $item->kode_item, 'nama_item' => $item->nama_item, 'tipe' => 'produk_jual',
            'satuan' => $item->satuan, 'harga_jual' => '10000', 'is_active' => '1',
            'hapus_foto' => '1',
            'cabang_aktif' => \App\Models\Cabang::aktif()->pluck('id')->all(),
        ]);

        $response->assertRedirect();
        $item->refresh();
        $this->assertNull($item->foto);
        Storage::disk('public')->assertMissing($fotoPathLama);
    }

    public function test_destroy_soft_delete_dan_hapus_foto(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $foto = UploadedFile::fake()->image('produk.jpg', 100, 100);
        $item = Item::create([
            'kode_item' => 'PJ-HTTP-004', 'nama_item' => 'Akan Dihapus', 'tipe' => 'produk_jual',
            'satuan' => 'porsi', 'harga_jual' => 10000, 'is_active' => true,
            'foto' => $foto->store('produk', 'public'),
        ]);

        $response = $this->actingAs($admin)->delete("/master/produk-jual/{$item->id}");

        $response->assertRedirect(route('master.produk-jual.index'));
        $this->assertSoftDeleted('items', ['id' => $item->id]);
        Storage::disk('public')->assertMissing($item->foto);
    }

    public function test_kalkulator_resep_ajax_mengembalikan_breakdown_benar(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = Item::where('kode_item', 'PJ-DIM-001')->firstOrFail(); // punya resep dari ResepBumbuSeeder

        $response = $this->actingAs($admin)->getJson("/master/produk-jual/{$item->id}/kalkulator-resep?jumlah=5");

        $response->assertOk();
        $response->assertJsonStructure(['jumlah_produksi', 'breakdown', 'total_hpp']);
        $response->assertJson(['jumlah_produksi' => 5]);
    }
}
