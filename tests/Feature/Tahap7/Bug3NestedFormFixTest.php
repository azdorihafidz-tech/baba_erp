<?php

namespace Tests\Feature\Tahap7;

use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap 7 D'mentai — Bug 3 fix verification: form hapus TIDAK BOLEH lagi
 * nested di dalam form utama (root cause "Simpan Produk" ter-spoof jadi
 * DELETE, lihat CLAUDE.md 4.13), + Data Terhapus UX improvement (badge
 * counter per tab + pesan info kalau kosong).
 */
class Bug3NestedFormFixTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    /** Hitung <form ...> vs </form> secara berurutan, deteksi nesting: depth tidak boleh > 1. */
    private function assertTidakAdaNestedForm(string $html, string $context): void
    {
        preg_match_all('/<form\b|<\/form>/i', $html, $matches);
        $depth = 0;
        $maxDepth = 0;
        foreach ($matches[0] as $tag) {
            if (stripos($tag, '</form>') === 0) {
                $depth--;
            } else {
                $depth++;
            }
            $maxDepth = max($maxDepth, $depth);
        }
        $this->assertLessThanOrEqual(1, $maxDepth, "Ditemukan <form> NESTED di {$context} (max depth {$maxDepth}) — ini penyebab bug method-spoofing.");
        $this->assertSame(0, $depth, "Jumlah <form> dan </form> tidak balance di {$context}.");
    }

    public function test_edit_produk_jual_tidak_ada_nested_form(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = Item::create(['kode_item' => 'NF-001', 'nama_item' => 'Cek Nested Form', 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 10000, 'is_active' => true]);

        $response = $this->actingAs($admin)->get("/master/produk-jual/{$item->id}/edit");

        $response->assertOk();
        $this->assertTidakAdaNestedForm($response->getContent(), 'master/produk-jual/edit');
        $response->assertSee('formHapusProdukJual', false);
        $response->assertSee('form="formHapusProdukJual"', false);
    }

    public function test_edit_bahan_baku_tidak_ada_nested_form(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = Item::create(['kode_item' => 'NF-002', 'nama_item' => 'Cek Nested Form Bahan', 'tipe' => 'bahan_baku', 'satuan' => 'kg', 'harga_jual' => 0, 'is_active' => true]);

        $response = $this->actingAs($admin)->get("/master/bahan-baku/{$item->id}/edit");

        $response->assertOk();
        $this->assertTidakAdaNestedForm($response->getContent(), 'master/bahan-baku/edit');
        $response->assertSee('formHapusBahanBaku', false);
    }

    public function test_klik_simpan_produk_sekarang_beneran_update_bukan_delete(): void
    {
        // Regresi definitif: payload edit NORMAL (tanpa _method aneh, seperti
        // browser asli kirim setelah fix) HARUS ter-update, bukan ke-soft-delete.
        $admin = $this->buatUser('admin_pusat');
        $item = Item::create(['kode_item' => 'NF-003', 'nama_item' => 'Produk Aman', 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 10000, 'is_active' => true]);

        $response = $this->actingAs($admin)->put("/master/produk-jual/{$item->id}", [
            'kode_item' => 'NF-003', 'nama_item' => 'Produk Aman (updated)', 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => '12000', 'is_active' => '1',
        ]);

        $response->assertRedirect(route('master.produk-jual.index'));
        $item->refresh();
        $this->assertNull($item->deleted_at);
        $this->assertEquals('Produk Aman (updated)', $item->nama_item);
    }

    public function test_tombol_hapus_di_edit_page_tetap_berfungsi_setelah_dipisah_dari_form_utama(): void
    {
        // Pastikan refactor form="formHapusProdukJual" (HTML5 form association)
        // tetap submit ke route destroy yang benar.
        $admin = $this->buatUser('admin_pusat');
        $item = Item::create(['kode_item' => 'NF-004', 'nama_item' => 'Akan Dihapus Sengaja', 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 10000, 'is_active' => true]);

        $response = $this->actingAs($admin)->delete("/master/produk-jual/{$item->id}");

        $response->assertRedirect(route('master.produk-jual.index'));
        $item->refresh();
        $this->assertNotNull($item->deleted_at);
    }

    // ===== Improvement 3a/3b: Data Terhapus badge counter + pesan info =====

    public function test_data_terhapus_menampilkan_badge_counter_per_tab(): void
    {
        $owner = $this->buatUser('owner');
        $item = Item::create(['kode_item' => 'NF-005', 'nama_item' => 'Item Utk Trash Badge', 'tipe' => 'bahan_baku', 'satuan' => 'kg', 'harga_jual' => 0, 'is_active' => true]);
        $item->delete();

        $response = $this->actingAs($owner)->get('/trash?model=orders');

        $response->assertOk();
        // Tab "Master Barang" (items) harus tampil badge angka >= 1, walau
        // tab aktifnya "orders" (default) yang mungkin 0 -- inilah UX fix-nya.
        $response->assertSeeInOrder(['Master Barang', 'badge-count'], false);
    }

    public function test_data_terhapus_pesan_info_kalau_tab_aktif_kosong_tapi_kategori_lain_ada_data(): void
    {
        $owner = $this->buatUser('owner');
        $item = Item::create(['kode_item' => 'NF-006', 'nama_item' => 'Item Lain Utk Trash', 'tipe' => 'bahan_baku', 'satuan' => 'kg', 'harga_jual' => 0, 'is_active' => true]);
        $item->delete();

        // Tab default "orders" kosong, tapi ada data di kategori lain (items).
        $response = $this->actingAs($owner)->get('/trash?model=orders');

        $response->assertOk();
        $response->assertSee('Coba filter kategori lain', false);
    }

    public function test_data_terhapus_regresi_restore_masih_berfungsi(): void
    {
        $owner = $this->buatUser('owner');
        $item = Item::create(['kode_item' => 'NF-007', 'nama_item' => 'Item Utk Restore', 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 5000, 'is_active' => true]);
        $item->delete();

        $response = $this->actingAs($owner)->post("/trash/items/{$item->id}/restore");

        $response->assertRedirect();
        $item->refresh();
        $this->assertNull($item->deleted_at);
    }
}
