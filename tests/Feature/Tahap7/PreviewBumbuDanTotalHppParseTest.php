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
 * Tahap 7 D'mentai (2026-09-19) — Bug Fix Ronde 5, 2 bug ditemukan Owner
 * setelah Ronde 4 (konsolidasi Total HPP) deploy:
 *
 * Bug 1: Subtotal baris Bumbu Pusat selalu "—"/Rp0. Root cause:
 * hitungSubtotalLinkedBaris() (JS) dulu pakai KALKULATOR_URL, yang route-nya
 * `/{produkJual}/kalkulator-resep` -- BUTUH Item yang sudah tersimpan. Di
 * halaman Create belum ada $item, jadi endpoint itu genuinely tidak bisa
 * dipanggil (const-nya bahkan tidak didefinisikan, dibungkus @if($isEdit)).
 * Fix: endpoint baru `previewSubtotalBumbu()`, di-bind ke ResepBumbu (bukan
 * Item) -- tidak butuh produk tersimpan sama sekali, jalan di Create & Edit.
 *
 * Bug 2: Total HPP footer salah jumlah (1200+2250+200+800 jadi 1003, bukan
 * 4450). Root cause: hitungTotalHpp() parse teks "Rp 1.200" dgn regex yang
 * MENYISAKAN titik (utk jaga-jaga desimal) -- parseFloat("1.200") dibaca 1.2
 * (titik = decimal separator JS), padahal titik itu SELALU pemisah ribuan
 * Indonesia (formatRupiahPreview() selalu Math.round(), tidak pernah ada
 * desimal asli). Fix: buang SEMUA non-digit sebelum parse.
 */
class PreviewBumbuDanTotalHppParseTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    // ===== Bug 2: regex parse angka =====

    public function test_hitungtotalhpp_buang_semua_nondigit_bukan_sisakan_titik(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/master/produk-jual/create');

        $response->assertOk();
        $response->assertSee("replace(/[^0-9]/g, '')", false);
        $response->assertDontSee("replace(/[^0-9.-]/g, '')", false);
    }

    // ===== Bug 1: endpoint preview-bumbu decoupled dari Item =====

    public function test_preview_bumbu_hitung_subtotal_benar(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $bahan = Item::create(['kode_item' => 'BB-PB-001', 'nama_item' => 'Bahan Preview Bumbu', 'tipe' => 'bahan_baku', 'satuan' => 'gram', 'harga_beli_terakhir' => 5000, 'is_active' => true]);
        $bumbu = ResepBumbu::create(['nama' => 'Bumbu Preview Test', 'kode' => 'BP1-' . uniqid('', false), 'item_id' => null, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $bumbu->id, 'item_id' => $bahan->id, 'qty_per_unit' => 1000, 'satuan' => 'gram', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 0]);

        // 1000 gram = 1kg x 5000/kg = 5000/porsi
        $response = $this->actingAs($admin)->postJson("/master/produk-jual/preview-bumbu/{$bumbu->id}?jumlah=1", [
            'qty_per_unit' => 2,
        ]);

        $response->assertOk();
        $this->assertEquals(10000, $response->json('subtotal')); // 5000 x 2 porsi
    }

    public function test_preview_bumbu_TIDAK_butuh_produk_tersimpan(): void
    {
        // Ini skenario Bug 1 asli: halaman Create belum ada Item produk sama
        // sekali -- endpoint harus tetap bisa dipanggil krn bind ke ResepBumbu,
        // bukan ke Item produk.
        $admin = $this->buatUser('admin_pusat');
        $bahan = Item::create(['kode_item' => 'BB-PB-002', 'nama_item' => 'Bahan Preview Bumbu 2', 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'harga_beli_terakhir' => 300, 'is_active' => true]);
        $bumbu = ResepBumbu::create(['nama' => 'Bumbu Preview Test 2', 'kode' => 'BP2-' . uniqid('', false), 'item_id' => null, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $bumbu->id, 'item_id' => $bahan->id, 'qty_per_unit' => 5, 'satuan' => 'pcs', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 0]);

        // Tidak ada Item produk_jual dibuat sama sekali sebelum request ini.
        $response = $this->actingAs($admin)->postJson("/master/produk-jual/preview-bumbu/{$bumbu->id}?jumlah=1", [
            'qty_per_unit' => 1,
        ]);

        $response->assertOk();
        $this->assertEquals(1500, $response->json('subtotal')); // 5 x 300
    }

    // 4.39 — Mode Harga dihapus: semua bahan dihitung, termasuk yang dulu "gratis"
    public function test_preview_bumbu_hitung_semua_bahan_termasuk_mode_gratis_lama(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $bahanBerbayar = Item::create(['kode_item' => 'BB-PB-003', 'nama_item' => 'Bahan Berbayar', 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'harga_beli_terakhir' => 1000, 'is_active' => true]);
        $bahanGratis = Item::create(['kode_item' => 'BB-PB-004', 'nama_item' => 'Bahan Gratis', 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'harga_beli_terakhir' => 500, 'is_active' => true]);
        $bumbu = ResepBumbu::create(['nama' => 'Bumbu Mode Mix', 'kode' => 'BP3-' . uniqid('', false), 'item_id' => null, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $bumbu->id, 'item_id' => $bahanBerbayar->id, 'qty_per_unit' => 1, 'satuan' => 'pcs', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 0]);
        ResepBumbuItem::create(['resep_bumbu_id' => $bumbu->id, 'item_id' => $bahanGratis->id, 'qty_per_unit' => 1, 'satuan' => 'pcs', 'is_wajib' => true, 'mode_harga' => 'gratis', 'urutan' => 1]);

        $response = $this->actingAs($admin)->postJson("/master/produk-jual/preview-bumbu/{$bumbu->id}?jumlah=1", [
            'qty_per_unit' => 1,
        ]);

        $response->assertOk();
        $this->assertEquals(1500, $response->json('subtotal')); // 1000 + 500
    }

    public function test_preview_bumbu_tanpa_login_ditolak(): void
    {
        $bumbu = ResepBumbu::create(['nama' => 'Bumbu Guest Test', 'kode' => 'BP4-' . uniqid('', false), 'item_id' => null, 'is_active' => true]);

        $response = $this->postJson("/master/produk-jual/preview-bumbu/{$bumbu->id}?jumlah=1", ['qty_per_unit' => 1]);

        $response->assertStatus(401);
    }

    public function test_preview_bumbu_tanpa_permission_ditolak(): void
    {
        $kasir = $this->buatUser('kasir', $this->cabangPertama()->id);
        $bumbu = ResepBumbu::create(['nama' => 'Bumbu Kasir Test', 'kode' => 'BP5-' . uniqid('', false), 'item_id' => null, 'is_active' => true]);

        $response = $this->actingAs($kasir)->postJson("/master/produk-jual/preview-bumbu/{$bumbu->id}?jumlah=1", ['qty_per_unit' => 1]);

        $response->assertStatus(403);
    }

    public function test_form_create_pakai_endpoint_preview_bumbu_bukan_kalkulator_url(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/master/produk-jual/create');

        $response->assertOk();
        $response->assertSee('PREVIEW_BUMBU_URL_BASE', false);
        $response->assertSee('function hitungSubtotalLinkedBaris', false);
        // Halaman Create tidak punya KALKULATOR_URL (butuh $item tersimpan) --
        // pastikan hitungSubtotalLinkedBaris() TIDAK bergantung lagi ke situ.
        $response->assertDontSee('typeof KALKULATOR_URL', false);
    }

    // ===== Regresi =====

    public function test_regresi_full_suite_produk_masih_bisa_disimpan_dgn_bumbu_linked(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $bahan = Item::create(['kode_item' => 'BB-PB-005', 'nama_item' => 'Bahan Regresi Preview', 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'harga_beli_terakhir' => 400, 'is_active' => true]);
        $bumbu = ResepBumbu::create(['nama' => 'Bumbu Regresi Preview', 'kode' => 'BP6-' . uniqid('', false), 'item_id' => null, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $bumbu->id, 'item_id' => $bahan->id, 'qty_per_unit' => 1, 'satuan' => 'pcs', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 0]);

        $response = $this->actingAs($admin)->post('/master/produk-jual', [
            'kode_item' => 'PJ-PB-001', 'nama_item' => 'Produk Regresi Preview Bumbu', 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => '10000', 'is_active' => '1',
            'resep' => [['resep_bumbu_ref_id' => $bumbu->id, 'qty_per_unit' => '1', 'is_wajib' => '1']],
        ]);

        $response->assertRedirect();
        $item = Item::where('kode_item', 'PJ-PB-001')->firstOrFail();
        $this->assertTrue($item->resep->items->first()->isLinked());
    }
}
