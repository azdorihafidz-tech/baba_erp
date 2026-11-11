<?php

namespace Tests\Feature\Tahap7;

use App\Models\Item;
use App\Models\ResepBumbu;
use App\Models\ResepBumbuItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap 7 D'mentai (2026-09-19) — Bug fix Ronde 3: Simulasi Produksi Lengkap
 * dulu SELALU baca resep dari DB (data tersimpan), bukan dari form yang
 * sedang diedit — kalau user ubah qty tapi belum klik Simpan, Simulasi
 * Produksi menampilkan angka LAMA (dari DB), beda dari kolom Subtotal
 * (client-side, baca form real-time). Laporan Owner: form "0.2 g", Simulasi
 * tampil "35 g" (data lama sebelum diedit). Lihat CLAUDE.md 4.19 addendum.
 *
 * Fix: `MasterProdukJualController::kalkulatorResep()` sekarang terima
 * payload `resep[]` (serialize form saat ini dari JS) via POST, fallback ke
 * DB kalau payload kosong (mis. dipanggil tanpa JS / backward compat).
 */
class SimulasiProduksiFormRealtimeTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_kalkulator_pakai_qty_dari_payload_form_bukan_db(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $ayam = Item::create(['kode_item' => 'BB-SIM-001', 'nama_item' => 'Isian Ayam Sim', 'tipe' => 'bahan_baku', 'satuan' => 'kg', 'harga_beli_terakhir' => 45000, 'is_active' => true]);

        $item = Item::create(['kode_item' => 'PJ-SIM-001', 'nama_item' => 'Produk Sim Test', 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 10000, 'is_active' => true]);
        $resep = ResepBumbu::create(['nama' => $item->nama_item, 'kode' => 'R-SIM-' . uniqid(), 'item_id' => $item->id, 'is_active' => true]);
        // Data DB "lama" -- 35 gram (persis skenario laporan Owner)
        ResepBumbuItem::create(['resep_bumbu_id' => $resep->id, 'item_id' => $ayam->id, 'qty_per_unit' => 35, 'satuan' => 'gram', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 0]);

        // Form saat ini (belum Disimpan) sudah diubah user jadi 0.2 gram
        $response = $this->actingAs($admin)->postJson("/master/produk-jual/{$item->id}/kalkulator-resep?jumlah=1", [
            'resep' => [
                ['item_id' => $ayam->id, 'qty_per_unit' => '0.2', 'satuan' => 'gram', 'is_wajib' => 1, 'mode_harga' => 'pakai_master'],
            ],
        ]);

        $response->assertOk();
        $data = $response->json();
        // 0.2 gram raw x 45000 (TANPA konversi satuan, konsisten dgn Opsi B) = 9000
        $this->assertEquals(9000, $data['total_hpp']);
        $this->assertEquals(9000, $data['breakdown'][0]['subtotal']);
        $this->assertNotEquals(1575000, $data['total_hpp']); // bug lama: 35 x 45000
    }

    public function test_kalkulator_fallback_ke_db_kalau_payload_resep_kosong(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $bahan = Item::create(['kode_item' => 'BB-SIM-002', 'nama_item' => 'Bahan Sim Fallback', 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'harga_beli_terakhir' => 300, 'is_active' => true]);

        $item = Item::create(['kode_item' => 'PJ-SIM-002', 'nama_item' => 'Produk Sim Fallback', 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 10000, 'is_active' => true]);
        $resep = ResepBumbu::create(['nama' => $item->nama_item, 'kode' => 'R-SIM2-' . uniqid(), 'item_id' => $item->id, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $resep->id, 'item_id' => $bahan->id, 'qty_per_unit' => 5, 'satuan' => 'pcs', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 0]);

        // GET tanpa payload resep -- backward compat, harus tetap baca DB
        $response = $this->actingAs($admin)->getJson("/master/produk-jual/{$item->id}/kalkulator-resep?jumlah=1");

        $response->assertOk();
        $data = $response->json();
        $this->assertEquals(1500, $data['total_hpp']); // 5 x 300
    }

    public function test_kalkulator_expand_bumbu_pusat_masih_benar_dengan_resep_dari_form(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $bahanBumbu = Item::create(['kode_item' => 'BB-SIM-003', 'nama_item' => 'Bahan Bumbu Sim', 'tipe' => 'bahan_baku', 'satuan' => 'gram', 'harga_beli_terakhir' => 5000, 'is_active' => true]);
        $bumbu = ResepBumbu::create(['nama' => 'Bumbu Sim Test', 'kode' => 'BMB-S-' . uniqid('', false), 'item_id' => null, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $bumbu->id, 'item_id' => $bahanBumbu->id, 'qty_per_unit' => 1000, 'satuan' => 'gram', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 0]);

        $item = Item::create(['kode_item' => 'PJ-SIM-003', 'nama_item' => 'Produk Sim Linked', 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 10000, 'is_active' => true]);

        $response = $this->actingAs($admin)->postJson("/master/produk-jual/{$item->id}/kalkulator-resep?jumlah=2", [
            'resep' => [
                ['resep_bumbu_ref_id' => $bumbu->id, 'qty_per_unit' => '1', 'is_wajib' => 1],
            ],
        ]);

        $response->assertOk();
        $data = $response->json();
        // 1000 gram = 1kg x 5000/kg x 1 porsi x 2 jumlah_produksi = 10000
        $this->assertEquals(10000, $data['total_hpp']);
        $this->assertTrue($data['breakdown'][0]['linked']);
    }

    public function test_kalkulator_skip_baris_invalid_dan_kosong(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $bahan = Item::create(['kode_item' => 'BB-SIM-004', 'nama_item' => 'Bahan Sim Valid', 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'harga_beli_terakhir' => 100, 'is_active' => true]);
        $item = Item::create(['kode_item' => 'PJ-SIM-004', 'nama_item' => 'Produk Sim Invalid Row', 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 10000, 'is_active' => true]);

        $response = $this->actingAs($admin)->postJson("/master/produk-jual/{$item->id}/kalkulator-resep?jumlah=1", [
            'resep' => [
                ['item_id' => $bahan->id, 'qty_per_unit' => '3', 'satuan' => 'pcs', 'is_wajib' => 1, 'mode_harga' => 'pakai_master'],
                ['item_id' => 999999, 'qty_per_unit' => '10', 'satuan' => 'pcs', 'is_wajib' => 1, 'mode_harga' => 'pakai_master'], // item_id tidak ada
                ['item_id' => '', 'qty_per_unit' => '5'], // baris kosong (belum pilih bahan)
            ],
        ]);

        $response->assertOk();
        $data = $response->json();
        $this->assertEquals(300, $data['total_hpp']); // cuma baris pertama yang valid
        $this->assertCount(1, $data['breakdown']);
    }

    public function test_kalkulator_dgn_resep_saat_produk_punya_varian_tidak_terganggu(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $bahan = Item::create(['kode_item' => 'BB-SIM-005', 'nama_item' => 'Bahan Sim Varian', 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'harga_beli_terakhir' => 250, 'is_active' => true]);
        $item = Item::create(['kode_item' => 'PJ-SIM-005', 'nama_item' => 'Produk Sim Varian', 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 10000, 'is_active' => true, 'punya_varian' => true]);

        $response = $this->actingAs($admin)->postJson("/master/produk-jual/{$item->id}/kalkulator-resep?jumlah=3", [
            'resep' => [
                ['item_id' => $bahan->id, 'qty_per_unit' => '2', 'satuan' => 'pcs', 'is_wajib' => 1, 'mode_harga' => 'pakai_master'],
            ],
        ]);

        $response->assertOk();
        $this->assertEquals(1500, $response->json('total_hpp')); // 2 x 250 x 3
    }

    // ===== Rendering: Total HPP tunggal (2026-09-19 -- konsolidasi 2 mode jadi 1) =====
    // Tombol "Simulasi Produksi Lengkap" DIHAPUS -- footer Total HPP sekarang
    // satu-satunya total, benar untuk baris manual (client-side) MAUPUN baris
    // linked Bumbu Pusat (AJAX otomatis, lihat hitungSubtotalLinkedBaris()).

    public function test_footer_total_hpp_tampil_dan_tombol_simulasi_lama_sudah_hilang(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = Item::create(['kode_item' => 'PJ-SIM-006', 'nama_item' => 'Produk Sim Label', 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 10000, 'is_active' => true]);

        $response = $this->actingAs($admin)->get("/master/produk-jual/{$item->id}/edit");

        $response->assertOk();
        $response->assertSee('id="totalHppFooter"', false);
        $response->assertSee('>Total HPP<', false);
        $response->assertDontSee('Simulasi Produksi Lengkap');
        $response->assertDontSee('id="jumlahProduksi"', false);
        $response->assertDontSee('id="hasilKalkulator"', false);
    }

    public function test_footer_total_hpp_markup_ada_di_halaman_create(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/master/produk-jual/create');

        $response->assertOk();
        $response->assertSee('id="totalHppFooter"', false);
        $response->assertSee('function hitungTotalHpp', false);
        $response->assertSee('function hitungSubtotalLinkedBaris', false);
    }

    // ===== Format qty input (2026-09-19) =====
    // DB `qty_per_unit` DECIMAL(10,3) -> Eloquent cast 'decimal:3' balikin
    // STRING fixed-3-desimal ("1.000", "0.200"), yang kalau ditaruh mentah ke
    // value input rancu dibaca org Indonesia (titik = pemisah ribuan di sana,
    // jadi "1.000" seperti "seribu"). Fix: formatQtyInput() di JS parseFloat
    // lalu stringify ulang sebelum masuk ke value= input.

    public function test_formatqtyinput_dipakai_utk_baris_manual_dan_linked(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = Item::create(['kode_item' => 'PJ-SIM-008', 'nama_item' => 'Produk Sim Format Qty', 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 10000, 'is_active' => true]);

        $response = $this->actingAs($admin)->get("/master/produk-jual/{$item->id}/edit");

        $response->assertOk();
        $response->assertSee('function formatQtyInput', false);
        $response->assertSee('formatQtyInput(data.qty_per_unit)', false);
        $response->assertSee('formatQtyInput(data.qty_per_unit ?? 1)', false);
    }

    public function test_resep_tersimpan_dgn_qty_desimal_tetap_akurat_setelah_edit_ulang(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $bahan = Item::create(['kode_item' => 'BB-SIM-007', 'nama_item' => 'Bahan Sim Qty Desimal', 'tipe' => 'bahan_baku', 'satuan' => 'gram', 'harga_beli_terakhir' => 45000, 'is_active' => true]);
        $item = Item::create(['kode_item' => 'PJ-SIM-009', 'nama_item' => 'Produk Sim Qty Desimal', 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 10000, 'is_active' => true]);
        $resep = ResepBumbu::create(['nama' => $item->nama_item, 'kode' => 'R-SIM3-' . uniqid('', false), 'item_id' => $item->id, 'is_active' => true]);
        // Disimpan sbg DECIMAL(10,3) -- Eloquent balikin string "0.200"
        ResepBumbuItem::create(['resep_bumbu_id' => $resep->id, 'item_id' => $bahan->id, 'qty_per_unit' => 0.2, 'satuan' => 'gram', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 0]);

        $item->refresh();
        $this->assertEquals('0.200', $item->resep->items->first()->qty_per_unit); // konfirmasi memang string ber-trailing-zero

        $response = $this->actingAs($admin)->get("/master/produk-jual/{$item->id}/edit");
        $response->assertOk(); // halaman tetap render normal, JS yg bersihkan formatnya saat load
    }

    // ===== Regresi =====

    public function test_regresi_full_suite_produk_masih_bisa_disimpan(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $bahan = Item::create(['kode_item' => 'BB-SIM-006', 'nama_item' => 'Bahan Sim Regresi', 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'harga_beli_terakhir' => 300, 'is_active' => true]);

        $response = $this->actingAs($admin)->post('/master/produk-jual', [
            'kode_item' => 'PJ-SIM-007', 'nama_item' => 'Produk Sim Regresi Save', 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => '10000', 'is_active' => '1',
            'resep' => [['item_id' => $bahan->id, 'qty_per_unit' => '5', 'satuan' => 'pcs', 'is_wajib' => '1', 'mode_harga' => 'pakai_master']],
        ]);

        $response->assertRedirect();
        $item = Item::where('kode_item', 'PJ-SIM-007')->firstOrFail();
        $this->assertEquals(5, (float) $item->resep->items->first()->qty_per_unit);
    }
}
