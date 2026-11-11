<?php

namespace Tests\Feature\Tahap7;

use App\Models\Item;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Bug fix 2026-09-19 (laporan Owner): modal "Tambah Kategori Baru" (menu
 * Master Barang → Tambah Item) — field "Kode Kategori" tampil placeholder
 * "(opsional)" dan TANPA atribut `required`, padahal backend
 * (`ItemController::storeKategori()`) SUDAH lama mewajibkan field ini
 * (`'kode_kategori' => 'required|string|max:20|unique:...'`). Front-end dan
 * back-end tidak sinkron -- user submit tanpa isi kode, form redirect balik
 * dgn error validasi yang membingungkan krn UI-nya bilang opsional. Fix:
 * murni UI (label + placeholder + atribut `required`) di 2 file
 * (item/create.blade.php, item/edit.blade.php) supaya sinkron dgn validasi
 * backend yang sudah ada -- 0 perubahan controller/migration.
 */
class KodeKategoriWajibTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_form_create_kode_kategori_wajib_bukan_opsional(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/item/create');

        $response->assertOk();
        $response->assertDontSee('(opsional)');
        $response->assertSee('Kode Kategori <span class="text-danger">*</span>', false);
    }

    public function test_form_edit_kode_kategori_wajib_bukan_opsional(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = Item::create(['kode_item' => 'ITM-KKW-001', 'nama_item' => 'Item Test Kode Kategori', 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'is_active' => true]);

        $response = $this->actingAs($admin)->get("/item/{$item->id}/edit");

        $response->assertOk();
        $response->assertDontSee('(opsional)');
        $response->assertSee('Kode Kategori <span class="text-danger">*</span>', false);
    }

    public function test_backend_masih_menolak_kode_kategori_kosong_sesuai_ui_baru(): void
    {
        // Regresi: backend TIDAK berubah sama sekali di fix ini -- validasi
        // 'required' pada kode_kategori sudah ada sejak awal, UI cuma
        // disinkronkan supaya tidak lagi menyesatkan user.
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->post(route('item.kategori.store'), [
            'nama_kategori' => 'Kategori Tanpa Kode',
        ]);

        $response->assertSessionHasErrors('kode_kategori');
        $this->assertDatabaseMissing('item_categories', ['nama_kategori' => 'Kategori Tanpa Kode']);
    }

    public function test_regresi_kategori_dgn_kode_lengkap_tetap_bisa_disimpan(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->post(route('item.kategori.store'), [
            'nama_kategori' => 'Kategori Wajib Test',
            'kode_kategori' => 'KWT01',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('item_categories', ['nama_kategori' => 'Kategori Wajib Test', 'kode_kategori' => 'KWT01']);
    }
}
