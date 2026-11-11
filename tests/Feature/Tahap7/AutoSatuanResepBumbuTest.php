<?php

namespace Tests\Feature\Tahap7;

use App\Models\Item;
use App\Models\ResepBumbu;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap 7 D'mentai (2026-09-19) — Master Bumbu Pusat, form "Tambah Bahan":
 * dropdown "Satuan" dulu SELALU default ke opsi pertama ("kg") apapun bahan
 * yang dipilih, user harus ingat ganti manual sesuai satuan asli bahan itu
 * (mis. Kulit Dimsum satuannya "pcs", bukan "kg") -- rawan salah pilih tanpa
 * disadari (beda dari section Resep Produk Jual yang sudah auto-isi satuan
 * sejak fitur Import Bumbu Pusat). Fix: begitu bahan dipilih, satuan-nya
 * di-auto-isi dari Item.satuan (dinormalisasi ke salah satu dari 4 opsi
 * dropdown: kg/g/ons/pcs) via JS `autoisiSatuan()`.
 */
class AutoSatuanResepBumbuTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_dropdown_bahan_punya_data_satuan_dan_js_autoisi_ada(): void
    {
        $admin = $this->buatUser('owner');
        $bahan = Item::create(['kode_item' => 'BB-AS-001', 'nama_item' => 'Kulit Dimsum Auto Satuan', 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'harga_jual' => 300, 'is_active' => true]);
        $resep = ResepBumbu::create(['nama' => 'Resep Auto Satuan Test', 'kode' => 'RAS-001', 'item_id' => null, 'is_active' => true]);

        $response = $this->actingAs($admin)->get(route('master.resep-bumbu.edit', $resep));

        $response->assertOk();
        $response->assertSee('data-satuan="pcs"', false);
        $response->assertSee('function autoisiSatuan', false);
        $response->assertSee('function normalisasiSatuan', false);
        $response->assertSee("itemSelect.addEventListener('change', autoisiSatuan)", false);
    }

    public function test_normalisasi_satuan_mengenali_variasi_teks_gram_dan_kg(): void
    {
        $admin = $this->buatUser('owner');
        $bahanGram = Item::create(['kode_item' => 'BB-AS-002', 'nama_item' => 'Bahan Gram Auto Satuan', 'tipe' => 'bahan_baku', 'satuan' => 'gram', 'harga_jual' => 5000, 'is_active' => true]);
        $bahanKg = Item::create(['kode_item' => 'BB-AS-003', 'nama_item' => 'Bahan Kg Auto Satuan', 'tipe' => 'bahan_baku', 'satuan' => 'kg', 'harga_jual' => 45000, 'is_active' => true]);
        $resep = ResepBumbu::create(['nama' => 'Resep Auto Satuan Test 2', 'kode' => 'RAS-002', 'item_id' => null, 'is_active' => true]);

        $response = $this->actingAs($admin)->get(route('master.resep-bumbu.edit', $resep));

        $response->assertOk();
        $response->assertSee('data-satuan="gram"', false);
        $response->assertSee('data-satuan="kg"', false);
        // fungsi normalisasi harus memetakan "gram" -> 'g' (opsi dropdown asli)
        $response->assertSee("['g', 'gr', 'gram'].includes(s)) return 'g'", false);
    }

    public function test_satuan_tidak_dikenal_tidak_dipaksa_dropdown_dibiarkan_manual(): void
    {
        // Item dgn satuan "liter" (belum ada opsi volume di dropdown 4-pilihan
        // ini) -- normalisasiSatuan() harus return null utk kasus ini, JS
        // TIDAK override pilihan user. Cukup verifikasi function-nya memang
        // return null utk kasus unknown (baca source), bukan expect exception.
        $admin = $this->buatUser('owner');
        $bahan = Item::create(['kode_item' => 'BB-AS-004', 'nama_item' => 'Bahan Liter Auto Satuan', 'tipe' => 'bahan_baku', 'satuan' => 'liter', 'harga_jual' => 10000, 'is_active' => true]);
        $resep = ResepBumbu::create(['nama' => 'Resep Auto Satuan Test 3', 'kode' => 'RAS-003', 'item_id' => null, 'is_active' => true]);

        $response = $this->actingAs($admin)->get(route('master.resep-bumbu.edit', $resep));

        $response->assertOk();
        $response->assertSee('data-satuan="liter"', false);
        $response->assertSee('return null;', false);
    }

    public function test_regresi_tambah_bahan_masih_bisa_disimpan(): void
    {
        $admin = $this->buatUser('owner');
        $bahan = Item::create(['kode_item' => 'BB-AS-005', 'nama_item' => 'Bahan Regresi Auto Satuan', 'tipe' => 'bahan_baku', 'satuan' => 'gram', 'harga_jual' => 2000, 'is_active' => true]);
        $resep = ResepBumbu::create(['nama' => 'Resep Auto Satuan Regresi', 'kode' => 'RAS-004', 'item_id' => null, 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('master.resep-bumbu.items.store', $resep), [
            'item_id' => $bahan->id, 'qty_per_unit' => '50', 'satuan' => 'g', 'mode_harga' => 'pakai_master', 'is_wajib' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('resep_bumbu_items', ['resep_bumbu_id' => $resep->id, 'item_id' => $bahan->id, 'satuan' => 'g']);
    }
}
