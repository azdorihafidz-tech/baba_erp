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
 * Tahap 7 D'mentai (2026-09-19) — UI preview "Harga Master" & "Subtotal" di
 * section Resep form Produk Jual (Opsi B, deferred konversi satuan ke
 * Sprint 2 — lihat CLAUDE.md TODO). Preview MURNI JS client-side (perkalian
 * apa adanya, tanpa konversi satuan, disengaja) -- test ini verifikasi
 * BAHAN (markup, data JS embedded) benar; perkalian aktualnya cuma bisa
 * diverifikasi manual di browser (tidak ada headless browser di test suite
 * project ini).
 */
class ResepHargaMasterPreviewTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    // ===== Rendering =====

    public function test_kolom_harga_master_dan_subtotal_tampil_di_form_create(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/master/produk-jual/create');

        $response->assertOk();
        $response->assertSee('Harga Bahan', false); // 4.39 — harga FIFO
        $response->assertSee('Subtotal', false);
        $response->assertDontSee('name="resep[${idx}][mode_harga]"', false); // 4.39 — Mode Harga dihapus
    }

    public function test_kolom_harga_master_dan_subtotal_tampil_di_form_edit(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = Item::create(['kode_item' => 'PJ-HM-001', 'nama_item' => 'Produk HM Test', 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 10000, 'is_active' => true]);

        $response = $this->actingAs($admin)->get("/master/produk-jual/{$item->id}/edit");

        $response->assertOk();
        $response->assertSee('Harga Master', false);
        $response->assertSee('Subtotal', false);
    }

    public function test_info_alert_warning_tampil_dengan_link_ke_master_bahan_baku(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/master/produk-jual/create');

        $response->assertOk();
        $response->assertSee('alert-warning', false);
        $response->assertSee('tanpa konversi satuan', false);
        $response->assertSee(route('master.bahan-baku.index'), false);
    }

    // ===== Data yang dikirim ke JS (BAHAN_OPTIONS) =====

    public function test_bahan_options_js_mengandung_field_harga(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $bahan = Item::create([
            'kode_item' => 'BB-HM-001', 'nama_item' => 'Kulit Dimsum HM Test', 'tipe' => 'bahan_baku',
            'satuan' => 'pcs', 'harga_jual' => 0, 'harga_beli_terakhir' => 300, 'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get('/master/produk-jual/create');

        $response->assertOk();
        // BAHAN_OPTIONS di-json-encode ke JS -- field 'harga' harus ada & benar.
        $response->assertSee('"harga":300', false);
    }

    public function test_bahan_options_harga_nol_kalau_belum_ada_harga_beli(): void
    {
        $admin = $this->buatUser('admin_pusat');
        Item::create([
            'kode_item' => 'BB-HM-002', 'nama_item' => 'Bahan Tanpa Harga HM Test', 'tipe' => 'bahan_baku',
            'satuan' => 'gram', 'harga_jual' => 0, 'harga_beli_terakhir' => null, 'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get('/master/produk-jual/create');

        $response->assertOk();
        $response->assertSee('"nama":"Bahan Tanpa Harga HM Test"', false);
        $response->assertSee('"harga":0', false);
    }

    // ===== Baris Bumbu Pusat terlink: subtotal via AJAX (bukan placeholder statis) =====
    // Ronde 3 (2026-09-19): dulu placeholder statis "— (lihat Simulasi Produksi)"
    // krn ada mode kalkulator terpisah; sekarang mode itu dihapus (konsolidasi 1
    // Total HPP), baris linked hitung subtotal-nya sendiri via AJAX ke endpoint
    // kalkulator-resep (fungsi JS hitungSubtotalLinkedBaris) -- lihat CLAUDE.md.

    public function test_baris_bumbu_terlink_tampil_menghitung_bukan_placeholder_statis(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $bahan = Item::create(['kode_item' => 'BB-HM-003', 'nama_item' => 'Bahan Bumbu HM', 'tipe' => 'bahan_baku', 'satuan' => 'gram', 'harga_beli_terakhir' => 5000, 'is_active' => true]);
        $bumbu = ResepBumbu::create(['nama' => 'Bumbu HM Test', 'kode' => 'BMB-HM-' . uniqid(), 'item_id' => null, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $bumbu->id, 'item_id' => $bahan->id, 'qty_per_unit' => 10, 'satuan' => 'gram', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 0]);

        $item = Item::create(['kode_item' => 'PJ-HM-002', 'nama_item' => 'Produk HM Linked', 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 10000, 'is_active' => true]);
        $resep = ResepBumbu::create(['nama' => $item->nama_item, 'kode' => 'R-HM-' . uniqid(), 'item_id' => $item->id, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $resep->id, 'resep_bumbu_ref_id' => $bumbu->id, 'qty_per_unit' => 1, 'satuan' => 'porsi', 'is_wajib' => true, 'mode_harga' => 'gratis', 'urutan' => 0]);

        $response = $this->actingAs($admin)->get("/master/produk-jual/{$item->id}/edit");

        $response->assertOk();
        $response->assertSee('Menghitung...', false);
        $response->assertSee('function hitungSubtotalLinkedBaris', false);
        $response->assertDontSee('— (lihat Simulasi Produksi)', false);
    }

    // ===== Regresi: Import Bumbu Pusat, Varian, Save =====

    public function test_regresi_tombol_import_bumbu_pusat_masih_ada(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/master/produk-jual/create');

        $response->assertOk();
        $response->assertSee('Import dari Bumbu Pusat');
        $response->assertSee('bukaModalImportBumbu()', false);
    }

    public function test_regresi_tidak_ada_nested_form(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = Item::create(['kode_item' => 'PJ-HM-003', 'nama_item' => 'Produk HM Nested Check', 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 10000, 'is_active' => true]);

        $response = $this->actingAs($admin)->get("/master/produk-jual/{$item->id}/edit");

        $response->assertOk();
        preg_match_all('/<form\b|<\/form>/i', $response->getContent(), $matches);
        $depth = 0; $maxDepth = 0;
        foreach ($matches[0] as $tag) {
            $depth += stripos($tag, '</form>') === 0 ? -1 : 1;
            $maxDepth = max($maxDepth, $depth);
        }
        $this->assertLessThanOrEqual(1, $maxDepth);
    }

    public function test_regresi_save_produk_dengan_resep_manual_masih_normal(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $bahan = Item::create(['kode_item' => 'BB-HM-004', 'nama_item' => 'Bahan Save HM', 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'harga_beli_terakhir' => 300, 'is_active' => true]);

        $response = $this->actingAs($admin)->post('/master/produk-jual', [
            'kode_item' => 'PJ-HM-004', 'nama_item' => 'Produk HM Save Test', 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => '10000', 'is_active' => '1',
            'resep' => [['item_id' => $bahan->id, 'qty_per_unit' => '5', 'satuan' => 'pcs', 'is_wajib' => '1', 'mode_harga' => 'pakai_master']],
        ]);

        $response->assertRedirect();
        $item = Item::where('kode_item', 'PJ-HM-004')->firstOrFail();
        $this->assertNotNull($item->resep);
        $this->assertEquals(5, (float) $item->resep->items->first()->qty_per_unit);
    }

    public function test_regresi_save_produk_dengan_varian_masih_normal(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = Item::create(['kode_item' => 'PJ-HM-005', 'nama_item' => 'Produk HM Varian Test', 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 10000, 'is_active' => true]);

        $response = $this->actingAs($admin)->put("/master/produk-jual/{$item->id}", [
            'kode_item' => 'PJ-HM-005', 'nama_item' => 'Produk HM Varian Test', 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => '10000', 'is_active' => '1',
            'punya_varian' => '1', 'atribut' => [0 => ['nama' => 'Size', 'nilai' => 'S, M']],
        ]);

        $response->assertRedirect();
        $item->refresh();
        $this->assertTrue($item->punya_varian);
        $this->assertEquals(2, $item->variants()->count());
    }

    public function test_regresi_edit_resep_existing_dan_save_berhasil(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $bahan = Item::create(['kode_item' => 'BB-HM-005', 'nama_item' => 'Bahan Edit HM', 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'harga_beli_terakhir' => 500, 'is_active' => true]);
        $item = Item::create(['kode_item' => 'PJ-HM-006', 'nama_item' => 'Produk HM Edit Resep', 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 10000, 'is_active' => true]);
        $resep = ResepBumbu::create(['nama' => $item->nama_item, 'kode' => 'R-HM6-' . uniqid(), 'item_id' => $item->id, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $resep->id, 'item_id' => $bahan->id, 'qty_per_unit' => 2, 'satuan' => 'pcs', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 0]);

        $response = $this->actingAs($admin)->put("/master/produk-jual/{$item->id}", [
            'kode_item' => 'PJ-HM-006', 'nama_item' => 'Produk HM Edit Resep', 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => '10000', 'is_active' => '1',
            'resep' => [['item_id' => $bahan->id, 'qty_per_unit' => '4', 'satuan' => 'pcs', 'is_wajib' => '1', 'mode_harga' => 'pakai_master']],
        ]);

        $response->assertRedirect();
        $item->refresh();
        $this->assertEquals(4, (float) $item->resep->items->first()->qty_per_unit);
    }

    public function test_regresi_pos_tetap_render_normal(): void
    {
        $cabang = $this->cabangPertama();
        $kasir = $this->buatUser('kasir', $cabang->id);

        $response = $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])->get('/penjualan/pos');

        $response->assertOk();
    }
}
