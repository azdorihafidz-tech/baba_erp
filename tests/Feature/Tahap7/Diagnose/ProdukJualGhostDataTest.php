<?php

namespace Tests\Feature\Tahap7\Diagnose;

use App\Models\Item;
use App\Models\ItemAttribute;
use App\Models\ItemAttributeValue;
use App\Models\ItemCategory;
use App\Models\ItemVariant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Diagnose Bug 3 (data ghost): reproduksi PERSIS payload yang dikirim browser
 * nyata (bukan payload sintetis lama yang selalu isi 'tipe' eksplisit dsb),
 * termasuk field checkbox yang TIDAK dikirim kalau unchecked.
 */
class ProdukJualGhostDataTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_edit_produk_simple_tanpa_ubah_apapun_lalu_save(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = Item::create([
            'kode_item' => 'GHOST-001', 'nama_item' => 'Dimsum Ghost Test', 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => 15000, 'is_active' => true,
        ]);

        // Payload PERSIS seperti browser kirim utk produk simple (tanpa varian/resep),
        // checkbox is_active tercentang default -> ikut terkirim, cabang_aktif[] semua tercentang.
        $payload = [
            'kode_item' => 'GHOST-001', 'nama_item' => 'Dimsum Ghost Test', 'tipe' => 'produk_jual',
            'item_category_id' => '', 'satuan' => 'pcs', 'harga_jual' => '15000', 'deskripsi' => '',
            'is_active' => '1',
        ];

        $response = $this->actingAs($admin)->put("/master/produk-jual/{$item->id}", $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('master.produk-jual.index'));

        $item->refresh();
        $this->assertNull($item->deleted_at, 'Item TIDAK BOLEH ter-soft-delete cuma dari edit biasa.');
        $this->assertEquals('produk_jual', $item->tipe, 'Tipe tidak boleh berubah kalau form kirim tipe yg sama.');

        // Cek index list masih menampilkan produk ini.
        $indexResponse = $this->actingAs($admin)->get('/master/produk-jual');
        $indexResponse->assertOk();
        $indexResponse->assertSee('Dimsum Ghost Test');
    }

    public function test_edit_produk_dengan_varian_tanpa_klik_update_preview_kombinasi(): void
    {
        // Simulasi user edit produk YANG SUDAH PUNYA VARIAN, tapi TIDAK
        // menyentuh section varian sama sekali sebelum save (skenario paling
        // umum: cuma ubah harga/nama). JS regenerateKombinasi() jalan
        // otomatis on-load (baris 316 _form.blade.php) jadi harga_override[]
        // tetap ke-generate dari ATRIBUT_AWAL -- tapi field `atribut[idx][nama/nilai]`
        // hidden inputs SUDAH ada dari tambahBarisAtribut() on-load juga.
        $admin = $this->buatUser('admin_pusat');
        $item = Item::create([
            'kode_item' => 'GHOST-002', 'nama_item' => 'Dimsum Varian Ghost', 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => 20000, 'is_active' => true, 'punya_varian' => true,
        ]);
        $attr = ItemAttribute::create(['item_id' => $item->id, 'nama' => 'Size', 'urutan' => 0]);
        $valS = ItemAttributeValue::create(['item_attribute_id' => $attr->id, 'nilai' => 'S', 'urutan' => 0]);
        $valM = ItemAttributeValue::create(['item_attribute_id' => $attr->id, 'nilai' => 'M', 'urutan' => 1]);
        $variantS = ItemVariant::create(['item_id' => $item->id, 'is_active' => true, 'urutan' => 0]);
        $variantS->attributeValues()->sync([$valS->id]);
        $variantM = ItemVariant::create(['item_id' => $item->id, 'is_active' => true, 'urutan' => 1]);
        $variantM->attributeValues()->sync([$valM->id]);

        // Payload PERSIS seperti browser: punya_varian checked (dikirim '1'),
        // atribut[0][nama/nilai] terisi dari ATRIBUT_AWAL, harga_override[i] dari kombinasi.
        $payload = [
            'kode_item' => 'GHOST-002', 'nama_item' => 'Dimsum Varian Ghost (edited)', 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => '25000', 'is_active' => '1',
            'punya_varian' => '1',
            'atribut' => [0 => ['nama' => 'Size', 'nilai' => 'S, M']],
            'harga_override' => ['', ''],
        ];

        $response = $this->actingAs($admin)->put("/master/produk-jual/{$item->id}", $payload);

        $response->assertSessionHasNoErrors();
        $item->refresh();
        $this->assertNull($item->deleted_at);
        $this->assertEquals(2, $item->variants()->count(), 'Kedua varian S & M harus tetap ada (tidak boleh ke-soft-delete kalau strukturnya sama).');
    }

    public function test_edit_ubah_kode_item_ke_kode_yang_dipakai_item_lain_gagal_validasi_bukan_silent(): void
    {
        $admin = $this->buatUser('admin_pusat');
        Item::create(['kode_item' => 'GHOST-EXIST', 'nama_item' => 'Existing', 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 1000, 'is_active' => true]);
        $item = Item::create(['kode_item' => 'GHOST-003', 'nama_item' => 'Item Diedit', 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 1000, 'is_active' => true]);

        $response = $this->actingAs($admin)->put("/master/produk-jual/{$item->id}", [
            'kode_item' => 'GHOST-EXIST', 'nama_item' => 'Item Diedit', 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => '1000', 'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('kode_item');
        $item->refresh();
        $this->assertEquals('GHOST-003', $item->kode_item, 'Gagal validasi -> data ASLI tidak boleh berubah.');
        $this->assertNull($item->deleted_at);
    }

    public function test_kategori_dihapus_lalu_item_diedit_tanpa_pilih_kategori_baru(): void
    {
        // Skenario: item punya kategori X, kategori X di-soft-delete oleh
        // admin lain, lalu user edit item ini (kategori dropdown otomatis
        // kosong krn ItemCategory::orderBy() tidak include yg trashed) tanpa
        // pilih kategori baru -> item_category_id dikirim '' -> exists:item_categories,id
        // gagal krn row nullable tapi value '' -- cek behavior.
        $admin = $this->buatUser('admin_pusat');
        $kategori = ItemCategory::create(['nama_kategori' => 'Kategori Ghost', 'kode_kategori' => 'KAT-GHOST']);
        $item = Item::create([
            'kode_item' => 'GHOST-004', 'nama_item' => 'Item Kategori Hilang', 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => 5000, 'is_active' => true, 'item_category_id' => $kategori->id,
        ]);
        $kategori->delete();

        $response = $this->actingAs($admin)->put("/master/produk-jual/{$item->id}", [
            'kode_item' => 'GHOST-004', 'nama_item' => 'Item Kategori Hilang', 'tipe' => 'produk_jual',
            'item_category_id' => '', 'satuan' => 'pcs', 'harga_jual' => '5000', 'is_active' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $item->refresh();
        $this->assertNull($item->deleted_at);
    }

    /**
     * ROOT CAUSE Bug 3: _form.blade.php punya <form> "Zona Berbahaya" (hapus)
     * NESTED di dalam <form id="formProdukJual"> (edit/simpan) -- baris
     * 206-210 di dalam baris 22-217. HTML tidak mengizinkan nested <form>;
     * browser akan MEMBUANG tag <form> dalam yang bersarang tapi TETAP
     * memasukkan child input-nya (termasuk hidden _method=DELETE) sebagai
     * bagian dari form LUAR. Karena hidden _method=DELETE itu posisinya di
     * DOM SETELAH hidden _method=PUT bawaan form utama, saat "Simpan Produk"
     * diklik, $_POST['_method'] PHP akan berisi NILAI TERAKHIR ('DELETE'),
     * bukan 'PUT' -- request PUT yang dimaksud kasir malah di-spoof jadi
     * DELETE -> routes ke destroy() -> item ke-soft-delete.
     * Test ini mensimulasikan persis efek itu (duplikat _method, terakhir
     * menang -- exact PHP $_POST behavior utk field bernama sama).
     */
    public function test_root_cause_nested_form_menyebabkan_method_spoofing_jadi_delete(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = Item::create([
            'kode_item' => 'GHOST-006', 'nama_item' => 'Korban Nested Form', 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => 12000, 'is_active' => true,
        ]);

        // PHP array cuma bisa punya 1 key '_method' -> otomatis merepresentasikan
        // "nilai terakhir menang" persis seperti $_POST asli dari raw body dgn 2 field sama.
        $response = $this->actingAs($admin)->post("/master/produk-jual/{$item->id}", [
            '_method' => 'DELETE', // <- ini yg browser kirim akibat form nested, BUKAN 'PUT'
            'kode_item' => 'GHOST-006', 'nama_item' => 'Korban Nested Form (edited)', 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => '15000', 'is_active' => '1',
        ]);

        $item->refresh();
        $this->assertNotNull($item->deleted_at, 'KONFIRMASI BUG: klik "Simpan" ter-spoof jadi DELETE -> item ke-soft-delete alih-alih ter-update.');

        // Buktikan juga item TETAP ADA di Data Terhapus (bukan hilang permanen) --
        // cuma user perlu pilih filter model "items" (bukan default "orders").
        $owner = $this->buatUser('owner');
        $trash = $this->actingAs($owner)->get('/trash?model=items');
        $trash->assertOk();
        $trash->assertSee('Korban Nested Form');
    }

    public function test_index_setelah_banyak_edit_berturut_turut_tidak_kehilangan_produk(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = Item::create([
            'kode_item' => 'GHOST-005', 'nama_item' => 'Produk Diedit Berkali', 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => 10000, 'is_active' => true,
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($admin)->put("/master/produk-jual/{$item->id}", [
                'kode_item' => 'GHOST-005', 'nama_item' => "Produk Diedit Berkali v{$i}", 'tipe' => 'produk_jual',
                'satuan' => 'pcs', 'harga_jual' => (string) (10000 + $i), 'is_active' => '1',
            ])->assertSessionHasNoErrors();
        }

        $item->refresh();
        $this->assertNull($item->deleted_at);
        $this->assertEquals('produk_jual', $item->tipe);

        $indexResponse = $this->actingAs($admin)->get('/master/produk-jual');
        $indexResponse->assertOk();
        $indexResponse->assertSee('Produk Diedit Berkali v5');

        // Cek juga TIDAK muncul di Data Terhapus (harus bersih, bukan ke-trash tersembunyi).
        $owner = $this->buatUser('owner');
        $trashResponse = $this->actingAs($owner)->get('/trash?model=items');
        $trashResponse->assertOk();
        $trashResponse->assertDontSee('Produk Diedit Berkali v5');
    }
}
