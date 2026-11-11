<?php

namespace Tests\Feature\Tahap7;

use App\Models\Item;
use App\Models\ResepBumbu;
use App\Models\ResepBumbuItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Sprint 4.31 (2026-09-29) — fix bug harga_jual → harga_beli_terakhir di
 * Master Bumbu Pusat (dulu tercatat di CLAUDE.md 4.19 sbg TODO belum difix)
 * + enhancement Import Bumbu Pusat picker tampil detail bahan.
 */
class MasterBumbuHargaBeliTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function bahan(array $attr = []): Item
    {
        return Item::create(array_merge([
            'kode_item' => 'BB-BMB-' . uniqid(), 'nama_item' => 'Ayam Giling',
            'tipe' => 'bahan_baku', 'satuan' => 'kg',
            'harga_beli_terakhir' => 45000, 'harga_jual' => 0, 'is_active' => true,
        ], $attr));
    }

    private function bumbu(): ResepBumbu
    {
        return ResepBumbu::create(['kode' => 'BMB-' . uniqid(), 'nama' => 'Bumbu Test', 'is_active' => true]);
    }

    // ===== Bagian A: fix bug =====

    public function test_total_harga_master_pakai_harga_beli_bukan_harga_jual(): void
    {
        $b = $this->bahan(['harga_beli_terakhir' => 45000, 'harga_jual' => 12000]);
        $bumbu = $this->bumbu();
        $ri = ResepBumbuItem::create([
            'resep_bumbu_id' => $bumbu->id, 'item_id' => $b->id,
            'qty_per_unit' => 1, 'satuan' => 'kg',
            'mode_harga' => 'pakai_master', 'is_wajib' => true, 'urutan' => 0,
        ]);

        $this->assertEqualsWithDelta(45000, $ri->fresh()->total_harga_master, 0.01);
    }

    public function test_bahan_tanpa_harga_beli_subtotal_nol_bukan_harga_jual_dipakai(): void
    {
        // Regresi eksplisit: bahan hanya punya harga_jual, harga_beli_terakhir=0
        // -> subtotal HARUS 0 (bukan pakai harga_jual sbg fallback).
        $b = $this->bahan(['harga_beli_terakhir' => 0, 'harga_jual' => 99999]);
        $bumbu = $this->bumbu();
        $ri = ResepBumbuItem::create([
            'resep_bumbu_id' => $bumbu->id, 'item_id' => $b->id,
            'qty_per_unit' => 1, 'satuan' => 'kg',
            'mode_harga' => 'pakai_master', 'is_wajib' => true, 'urutan' => 0,
        ]);

        $this->assertEqualsWithDelta(0, $ri->fresh()->total_harga_master, 0.01);
    }

    public function test_halaman_edit_master_bumbu_tampil_kolom_harga_beli_bukan_harga_master_lama(): void
    {
        $owner = $this->buatUser('owner');
        $b = $this->bahan(['harga_beli_terakhir' => 50000]);
        $bumbu = $this->bumbu();
        ResepBumbuItem::create([
            'resep_bumbu_id' => $bumbu->id, 'item_id' => $b->id,
            'qty_per_unit' => 2, 'satuan' => 'kg',
            'mode_harga' => 'pakai_master', 'is_wajib' => true, 'urutan' => 0,
        ]);

        $response = $this->actingAs($owner)->get(route('master.resep-bumbu.edit', $bumbu));

        $response->assertOk();
        $response->assertSee('<th class="text-end">Harga (FIFO)</th>', false); // 4.39 — harga FIFO, fallback Harga Standar
        $response->assertSee('<th class="text-end">Subtotal</th>', false);
        $response->assertSee('data-harga-beli', false);
        $response->assertSee('<strong>FIFO</strong>', false); // caption (4.39)
        $response->assertDontSee('data-harga-jual', false);
        // 2 kg × 50.000 = 100.000
        $response->assertSee('Rp 100.000');
    }

    // ===== Bagian A regresi (produk jual tidak terganggu) =====

    public function test_master_produk_jual_form_tetap_pakai_harga_jual(): void
    {
        $owner = $this->buatUser('owner');
        $produk = Item::create([
            'kode_item' => 'PJ-HJ-TEST', 'nama_item' => 'Produk Test Harga Jual',
            'tipe' => 'produk_jual', 'satuan' => 'porsi',
            'harga_jual' => 25000, 'harga_beli_terakhir' => 8000, 'is_active' => true,
        ]);

        $response = $this->actingAs($owner)->get(route('master.produk-jual.edit', $produk));

        $response->assertOk();
        // Field harga_jual di form produk jual tetap pakai harga_jual (bukan ke-fix salah)
        $response->assertSee('name="harga_jual"', false);
        // Nilai 25000 harus di-render di input harga jual (via input-rupiah component)
        $response->assertSee('25000', false);
    }

    // ===== Bagian B: enhancement Import Bumbu Pusat =====

    public function test_list_bumbu_pusat_endpoint_kembalikan_detail_bahan_dengan_harga_beli(): void
    {
        $owner = $this->buatUser('owner');
        $b1 = $this->bahan(['nama_item' => 'Bahan A', 'harga_beli_terakhir' => 30000]);
        $b2 = $this->bahan(['nama_item' => 'Bahan B', 'harga_beli_terakhir' => 20000]);
        $bumbu = $this->bumbu();
        ResepBumbuItem::create(['resep_bumbu_id' => $bumbu->id, 'item_id' => $b1->id, 'qty_per_unit' => 0.5, 'satuan' => 'kg', 'mode_harga' => 'pakai_master', 'is_wajib' => true, 'urutan' => 0]);
        ResepBumbuItem::create(['resep_bumbu_id' => $bumbu->id, 'item_id' => $b2->id, 'qty_per_unit' => 1, 'satuan' => 'kg', 'mode_harga' => 'pakai_master', 'is_wajib' => true, 'urutan' => 1]);

        $response = $this->actingAs($owner)->getJson(route('master.produk-jual.bumbu-pusat.list', ['search' => 'Bumbu Test']));

        $response->assertOk();
        $json = $response->json('data');
        $row = collect($json)->firstWhere('id', $bumbu->id);
        $this->assertNotNull($row);
        $this->assertEquals(2, $row['jumlah_bahan']);
        $this->assertEqualsWithDelta(35000, $row['total_hpp'], 0.01); // 0.5×30k + 1×20k = 35k
        $this->assertCount(2, $row['items']);
        $bahanA = collect($row['items'])->firstWhere('nama_bahan', 'Bahan A');
        $this->assertEqualsWithDelta(30000, $bahanA['harga_beli'], 0.01);
        $this->assertEqualsWithDelta(15000, $bahanA['subtotal'], 0.01); // 0.5 × 30k
        $this->assertArrayNotHasKey('mode_harga', $bahanA); // 4.39 — Mode Harga dihapus
    }

    // 4.39 — Mode Harga dihapus: bahan yang dulu "gratis" sekarang tetap dihitung ke HPP
    public function test_list_bumbu_pusat_bahan_mode_gratis_lama_tetap_dihitung(): void
    {
        $owner = $this->buatUser('owner');
        $b = $this->bahan(['harga_beli_terakhir' => 50000]);
        $bumbu = $this->bumbu();
        ResepBumbuItem::create([
            'resep_bumbu_id' => $bumbu->id, 'item_id' => $b->id,
            'qty_per_unit' => 2, 'satuan' => 'kg',
            'mode_harga' => 'gratis', 'is_wajib' => true, 'urutan' => 0,
        ]);

        $response = $this->actingAs($owner)->getJson(route('master.produk-jual.bumbu-pusat.list', ['search' => 'Bumbu Test']));
        $row = collect($response->json('data'))->firstWhere('id', $bumbu->id);
        $this->assertEqualsWithDelta(100000, $row['total_hpp'], 0.01); // 2 kg × 50.000
        $this->assertEqualsWithDelta(100000, $row['items'][0]['subtotal'], 0.01);
    }

    public function test_modal_picker_import_bumbu_pusat_render_dengan_js_accordion(): void
    {
        $owner = $this->buatUser('owner');
        $produk = Item::create([
            'kode_item' => 'PJ-ACC-TEST', 'nama_item' => 'Produk Accordion',
            'tipe' => 'produk_jual', 'satuan' => 'porsi',
            'harga_jual' => 20000, 'is_active' => true,
        ]);

        $response = $this->actingAs($owner)->get(route('master.produk-jual.edit', $produk));

        $response->assertOk();
        $response->assertSee('modalImportBumbu', false);
        $response->assertSee('renderKartuBumbu', false); // fungsi JS baru accordion
        // @json(route(...)) escape slash jadi bumbu-pusat\/list
        $response->assertSee('BUMBU_PUSAT_LIST_URL', false);
        $response->assertSee('bumbu-pusat', false);
    }

    public function test_list_bumbu_pusat_tolak_tanpa_permission(): void
    {
        $kasir = $this->buatUser('kasir', $this->cabangPertama()->id);
        $response = $this->actingAs($kasir)->getJson(route('master.produk-jual.bumbu-pusat.list'));
        $response->assertForbidden();
    }
}
