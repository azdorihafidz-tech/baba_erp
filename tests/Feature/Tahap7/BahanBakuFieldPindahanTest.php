<?php

namespace Tests\Feature\Tahap7;

use App\Enums\TipeCabang;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Sprint 4.34 lanjutan (2026-10-08) — 4 field dipindah dari Master Barang Lengkap
 * ke form Bahan Baku (create+edit) + Produk Jual (modal kategori). Field:
 * 1. Jenis Item (bahan_baku/perlengkapan)
 * 2. Lacak Stok (track_stok)
 * 3. Deskripsi
 * 4. Modal "Tambah Kategori Baru"
 */
class BahanBakuFieldPindahanTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    protected function setUp(): void
    {
        parent::setUp();
        if (Cabang::count() < 1) {
            Cabang::firstOrCreate(['kode_cabang' => 'TST-BF1'], [
                'nama_cabang' => 'Cabang Test BF 1', 'tipe' => TipeCabang::Cabang, 'is_active' => true,
            ]);
        }
    }

    public function test_form_create_bahan_baku_render_4_field_baru(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $response = $this->actingAs($user)->get(route('master.bahan-baku.create'));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('name="jenis"', $html);
        $this->assertStringContainsString('value="bahan_baku"', $html);
        $this->assertStringContainsString('value="perlengkapan"', $html);
        $this->assertStringContainsString('name="track_stok"', $html);
        $this->assertStringContainsString('name="deskripsi"', $html);
        $this->assertStringContainsString('modalKategori', $html);
        // modal pakai route('item.kategori.store') → URL '/item/kategori'
        $this->assertStringContainsString('/item/kategori', $html);
    }

    public function test_form_edit_bahan_baku_render_4_field_baru(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $item = Item::create([
            'kode_item' => 'BF-EDIT-' . Str::random(5),
            'nama_item' => 'BF Edit Test',
            'tipe'      => 'bahan_baku',
            'satuan'    => 'pcs',
            'is_active' => true,
            'jenis'     => 'perlengkapan',
            'track_stok' => false,
            'deskripsi' => 'Catatan test',
        ]);

        $response = $this->actingAs($user)->get(route('master.bahan-baku.edit', $item));
        $response->assertOk();
        $html = $response->getContent();

        // Nilai existing ter-populate (jenis=perlengkapan)
        $this->assertStringContainsString('value="perlengkapan"', $html);
        // Radio perlengkapan harus checked: cek id='jenisPerlengkapan' punya checked
        $this->assertTrue(
            (bool) preg_match('/id="jenisPerlengkapan"[^>]*checked|checked[^>]*id="jenisPerlengkapan"/', $html),
            'Radio Perlengkapan harus checked di edit form'
        );
        $this->assertStringContainsString('Catatan test', $html);
        $this->assertStringContainsString('modalKategori', $html);
    }

    public function test_form_produk_jual_create_render_modal_kategori(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $response = $this->actingAs($user)->get(route('master.produk-jual.create'));
        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('modalKategori', $html);
        $this->assertStringContainsString('/item/kategori', $html);
    }

    public function test_store_bahan_baku_simpan_jenis_perlengkapan_track_stok_false(): void
    {
        $this->withoutExceptionHandling();
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $kategori = ItemCategory::firstOrCreate(['kode_kategori' => 'CAT-BF-X'], ['nama_kategori' => 'Cat BF X']);

        $response = $this->actingAs($user)->post(route('master.bahan-baku.store'), [
            'kode_item' => 'BF-STORE-PRLG',
            'nama_item' => 'Nota Kertas Test',
            'tipe'      => 'bahan_baku',
            'satuan'    => 'rim',
            'item_category_id' => $kategori->id,
            'jenis'     => 'perlengkapan',
            // track_stok absen = false (checkbox tidak di-check)
            'deskripsi' => 'ATK, dicatat sbg beban langsung.',
            'is_active' => '1',
            'harga_beli_terakhir' => '2500',
            'qty_minimum' => '0',
        ]);

        $created = Item::where('kode_item', 'BF-STORE-PRLG')->first();
        $this->assertNotNull($created, 'Item not created. Status: ' . $response->getStatusCode());
        $this->assertSame('perlengkapan', $created->jenis instanceof \BackedEnum ? $created->jenis->value : $created->jenis);
        $this->assertFalse((bool) $created->track_stok);
        $this->assertSame('ATK, dicatat sbg beban langsung.', $created->deskripsi);
    }

    public function test_store_bahan_baku_default_jenis_bahan_baku_track_stok_true(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $kategori = ItemCategory::firstOrCreate(['kode_kategori' => 'CAT-BF-Y'], ['nama_kategori' => 'Cat BF Y']);

        $response = $this->actingAs($user)->post(route('master.bahan-baku.store'), [
            'kode_item' => 'BF-STORE-BB',
            'nama_item' => 'Tepung BF Test',
            'tipe'      => 'bahan_baku',
            'satuan'    => 'kg',
            'item_category_id' => $kategori->id,
            'jenis'     => 'bahan_baku',
            'track_stok' => '1',
            'is_active' => '1',
            'harga_beli_terakhir' => '1000',
            'qty_minimum' => '0',
        ]);
        $response->assertSessionDoesntHaveErrors();
        $created = Item::where('kode_item', 'BF-STORE-BB')->first();
        $this->assertNotNull($created, 'Item not created. Status: ' . $response->getStatusCode());
        $this->assertSame('bahan_baku', $created->jenis instanceof \BackedEnum ? $created->jenis->value : $created->jenis);
        $this->assertTrue((bool) $created->track_stok);
    }

    public function test_update_bahan_baku_ubah_jenis_dan_track_stok(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $item = Item::create([
            'kode_item' => 'BF-UPD-' . Str::random(5),
            'nama_item' => 'BF Update Test',
            'tipe'      => 'bahan_baku',
            'satuan'    => 'pcs',
            'is_active' => true,
            'jenis'     => 'bahan_baku',
            'track_stok' => true,
        ]);

        $this->actingAs($user)->put(route('master.bahan-baku.update', $item), [
            'kode_item' => $item->kode_item,
            'nama_item' => $item->nama_item,
            'tipe'      => 'bahan_baku',
            'satuan'    => 'pcs',
            'jenis'     => 'perlengkapan',
            // track_stok absen = false
            'is_active' => '1',
            'harga_beli_terakhir' => '1000',
            'qty_minimum' => '0',
        ]);

        $item->refresh();
        $this->assertSame('perlengkapan', $item->jenis instanceof \BackedEnum ? $item->jenis->value : $item->jenis);
        $this->assertFalse((bool) $item->track_stok);
    }

    public function test_tipe_dropdown_bahan_baku_form_tanpa_produk_jual(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $response = $this->actingAs($user)->get(route('master.bahan-baku.create'));
        $html = $response->getContent();

        // 3 opsi valid harus ada
        $this->assertStringContainsString('value="bahan_baku"', $html);
        $this->assertStringContainsString('value="kemasan"', $html);
        $this->assertStringContainsString('value="tambahan_gratis"', $html);

        // produk_jual/produk_tambahan HARUS TIDAK ADA sebagai option tipe
        // (cari string spesifik: option tipe produk_jual)
        $this->assertDoesNotMatchRegularExpression('/<option[^>]+value="produk_jual"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<option[^>]+value="produk_tambahan"/', $html);
    }

    public function test_kategori_bisa_disimpan_dari_modal_di_bahan_baku(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);

        $response = $this->actingAs($user)->post(route('item.kategori.store'), [
            'nama_kategori' => 'Kategori Dari Modal BF',
            'kode_kategori' => 'MDL-BF-' . Str::random(4),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('item_categories', ['nama_kategori' => 'Kategori Dari Modal BF']);
    }
}
