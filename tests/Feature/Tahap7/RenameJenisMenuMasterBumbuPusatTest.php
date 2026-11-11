<?php

namespace Tests\Feature\Tahap7;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap 7 D'mentai — rename UI (label saja, ZERO migration): "Jenis Olahan"
 * -> "Jenis Menu", "Resep Bumbu Standar" -> "Master Bumbu Pusat", + info box
 * Master Kategori Item (modal Tambah Kategori di menu Master Barang). Route
 * name, permission name, model, table SEMUA TETAP (jenis-olahan.*,
 * resep-bumbu, master.resep_bumbu.*) -- hanya teks yang user lihat berubah.
 */
class RenameJenisMenuMasterBumbuPusatTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_halaman_jenis_menu_render_dengan_label_baru(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/master/jenis-olahan');

        $response->assertOk();
        $response->assertSee('Jenis Menu');
        $response->assertDontSee('Jenis Olahan');
    }

    public function test_halaman_master_bumbu_pusat_render_dengan_label_baru(): void
    {
        // admin_pusat SENGAJA tidak punya master.resep_bumbu.* by default
        // (lihat komentar di PermissionSeeder) -- pakai owner (bypass semua permission).
        $admin = $this->buatUser('owner');

        $response = $this->actingAs($admin)->get('/master/resep-bumbu');

        $response->assertOk();
        $response->assertSee('Master Bumbu Pusat');
        $response->assertDontSee('Resep Bumbu Standar');
    }

    public function test_master_kategori_item_modal_punya_info_box(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/item/create');

        $response->assertOk();
        $response->assertSee('TIDAK langsung muncul sebagai filter di grid POS');
    }

    public function test_panduan_jenis_menu_berisi_konten_baru(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/panduan/jenis-olahan');

        $response->assertOk();
        $response->assertSee('Jenis Menu');
        $response->assertDontSee('dropdown POS untuk transaksi Jasa Giling');
    }

    public function test_panduan_master_bumbu_pusat_berisi_use_case_baru(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/panduan/resep-bumbu');

        $response->assertOk();
        $response->assertSee('Master Bumbu Pusat');
        $response->assertSee('Produk Terhubung');
        $response->assertDontSee('Terapkan Resep');
        $response->assertDontSee('Berat Gilingan (kg)');
    }

    public function test_regresi_route_lama_tetap_bisa_diakses(): void
    {
        $admin = $this->buatUser('owner');

        // Route name TIDAK berubah (master.jenis-olahan.*, master.resep-bumbu.*)
        $this->actingAs($admin)->get(route('master.jenis-olahan.index'))->assertOk();
        $this->actingAs($admin)->get(route('master.resep-bumbu.index'))->assertOk();
        $this->actingAs($admin)->get(route('master.jenis-olahan.create'))->assertOk();
        $this->actingAs($admin)->get(route('master.resep-bumbu.create'))->assertOk();
    }

    public function test_regresi_permission_name_tidak_berubah(): void
    {
        $this->assertTrue(\App\Models\Permission::where('name', 'jenis-olahan.view')->exists());
        $this->assertTrue(\App\Models\Permission::where('name', 'jenis-olahan.manage')->exists());
        $this->assertTrue(\App\Models\Permission::where('name', 'master.resep_bumbu.view')->exists());
        $this->assertTrue(\App\Models\Permission::where('name', 'master.resep_bumbu.create')->exists());
    }

    public function test_regresi_tombol_cara_pakai_masih_ada(): void
    {
        $admin = $this->buatUser('owner');

        $this->actingAs($admin)->get('/master/jenis-olahan')->assertSee('data-bs-target="#panduanModal-jenis-olahan"', false);
        $this->actingAs($admin)->get('/master/resep-bumbu')->assertSee('data-bs-target="#panduanModal-resep-bumbu"', false);
    }

    public function test_kolom_produk_terhubung_menampilkan_nama_item(): void
    {
        // Buat resep BARU yang sengaja terhubung ke item_id, pastikan nama
        // produknya genuinely tampil di kolom "Produk Terhubung" (bukan
        // placeholder "belum terhubung produk").
        $admin = $this->buatUser('owner');
        $item = \App\Models\Item::where('tipe', 'produk_jual')->first();
        $resep = \App\Models\ResepBumbu::create([
            'nama' => 'Resep Test Kolom Produk', 'kode' => 'RES-TESTKOLOM',
            'item_id' => $item->id, 'is_active' => true, 'dibuat_oleh' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get('/master/resep-bumbu');

        $response->assertOk();
        $response->assertSee($item->nama_item);
    }

    public function test_import_dari_bumbu_pusat_sudah_ada_di_form_produk_jual(): void
    {
        // Dulu (2026-09-15) TODO "belum ada" -- fitur ini sudah dibangun
        // 2026-09-17 (lihat tests/Feature/Tahap7/ImportBumbuPusatTest.php),
        // jadi assertion di-flip ke "sudah ada" supaya tidak konflik.
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/master/produk-jual/create');

        $response->assertOk();
        $response->assertSee('Import dari Bumbu Pusat');
    }
}
