<?php

namespace Tests\Feature\Tahap25;

use App\Models\Item;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap 2.5 D'mentai — menu "Master Barang (Lengkap)" (route `item.*` lama)
 * SENGAJA dipertahankan apa adanya sebagai unified fallback (bukan
 * di-redirect) — tapi tipe dropdown-nya ikut diperbarui ke 5 nilai baru,
 * jadi tetap wajib ditest supaya tidak regresi.
 */
class ItemLegacyRouteTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_index_masih_berfungsi(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/item');

        $response->assertOk();
    }

    public function test_create_menampilkan_5_pilihan_tipe_baru(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/item/create');

        $response->assertOk();
        foreach (['bahan_baku', 'kemasan', 'tambahan_gratis', 'produk_jual', 'produk_tambahan'] as $tipe) {
            $response->assertSee('value="' . $tipe . '"', false);
        }
    }

    public function test_edit_tidak_500_error_lagi_bug_authuser_pre_existing(): void
    {
        // Bug pre-existing ditemukan saat testing Tahap 2.5: view butuh
        // $authUser (Danger Zone) tapi tidak pernah di-compact controller ->
        // selalu 500. Sudah diperbaiki sekalian (lihat CLAUDE.md).
        $admin = $this->buatUser('admin_pusat');
        $item = Item::where('kode_item', 'BB-BHN-001')->firstOrFail();

        $response = $this->actingAs($admin)->get("/item/{$item->id}/edit");

        $response->assertOk();
    }

    public function test_store_masih_bisa_pakai_tipe_lama_untuk_backward_compat(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->post('/item', [
            'kode_item' => 'ITEM-LEGACY-001',
            'nama_item' => 'Item Tipe Lama',
            'tipe' => 'produk_jadi', // nilai lama, masih valid di enum DB
            'satuan' => 'pcs',
            'jenis' => 'bahan_baku',
            'harga_jual' => '10000',
            'harga_beli_terakhir' => '5000',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('item.index'));
        $this->assertDatabaseHas('items', ['kode_item' => 'ITEM-LEGACY-001', 'tipe' => 'produk_jadi']);
    }
}
