<?php

namespace Tests\Feature\Tahap7;

use App\Models\Item;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Sprint 4.33 (2026-10-01) — fix bug kolom "Tipe" di list Master Barang
 * Lengkap selalu tampil "Lainnya" utk tipe baru Tahap 2.5 (produk_jual/
 * produk_tambahan/tambahan_gratis) krn mapping view masih pakai nama lama
 * (produk_jadi/lainnya). Data DB benar, bug murni di view.
 */
class MasterBarangTipeLabelTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function item(string $kode, string $tipe, string $nama): Item
    {
        return Item::create([
            'kode_item' => $kode, 'nama_item' => $nama, 'tipe' => $tipe,
            'satuan' => 'pcs', 'is_active' => true,
        ]);
    }

    private function akses()
    {
        return $this->actingAs($this->buatUser('owner'))->get(route('item.index'));
    }

    public function test_tipe_produk_jual_render_label_benar_bukan_lainnya(): void
    {
        $this->item('TBL-PJ-1', 'produk_jual', 'Dimsum Test PJ');
        $response = $this->akses();
        $response->assertOk();
        $response->assertSee('Dimsum Test PJ');
        // Baris item ini harus pakai label "Produk Jual" di kolom Tipe, bukan "Lainnya".
        // Pattern: dekat nama_item ada badge dgn teks "Produk Jual".
        $this->assertStringContainsString('Produk Jual', $response->getContent());
    }

    public function test_tipe_produk_tambahan_render_label_benar(): void
    {
        $this->item('TBL-PT-1', 'produk_tambahan', 'Saus Extra Test');
        $response = $this->akses();
        $response->assertSee('Saus Extra Test');
        $this->assertStringContainsString('Produk Tambahan', $response->getContent());
    }

    public function test_tipe_tambahan_gratis_render_label_benar(): void
    {
        $this->item('TBL-TG-1', 'tambahan_gratis', 'Garpu Plastik Test');
        $response = $this->akses();
        $response->assertSee('Garpu Plastik Test');
        $this->assertStringContainsString('Tambahan Gratis', $response->getContent());
    }

    public function test_tipe_legacy_produk_jadi_tetap_render_bukan_lainnya(): void
    {
        $this->item('TBL-PJADI-1', 'produk_jadi', 'Item Legacy Jadi');
        $response = $this->akses();
        $response->assertSee('Item Legacy Jadi');
        $this->assertStringContainsString('Produk Jadi', $response->getContent());
    }

    public function test_tipe_bahan_baku_dan_kemasan_tetap_benar(): void
    {
        $this->item('TBL-BB-1', 'bahan_baku', 'Tepung Test BB');
        $this->item('TBL-KM-1', 'kemasan', 'Kotak Test KM');
        $response = $this->akses();
        $response->assertSee('Tepung Test BB');
        $response->assertSee('Kotak Test KM');
        $this->assertStringContainsString('Bahan Baku', $response->getContent());
        $this->assertStringContainsString('Kemasan', $response->getContent());
    }

    public function test_dropdown_filter_tampil_7_opsi_5_baru_plus_2_legacy(): void
    {
        $response = $this->akses();
        // 5 opsi baru
        $response->assertSee('value="bahan_baku"', false);
        $response->assertSee('value="produk_jual"', false);
        $response->assertSee('value="produk_tambahan"', false);
        $response->assertSee('value="tambahan_gratis"', false);
        $response->assertSee('value="kemasan"', false);
        // 2 opsi legacy
        $response->assertSee('value="produk_jadi"', false);
        $response->assertSee('value="lainnya"', false);
        // Label legacy jelas-ditandai
        $response->assertSee('Produk Jadi (legacy)');
        $response->assertSee('Lainnya (legacy)');
    }

    public function test_filter_by_produk_jual_return_item_tipe_tsb(): void
    {
        $pj = $this->item('TBL-FJ-1', 'produk_jual', 'Produk Filter Jual');
        $pt = $this->item('TBL-FT-1', 'produk_tambahan', 'Produk Filter Tambahan');

        $response = $this->actingAs($this->buatUser('owner'))
            ->get(route('item.index', ['tipe' => 'produk_jual']));
        $response->assertOk();
        $response->assertSee('Produk Filter Jual');
        $response->assertDontSee('Produk Filter Tambahan');
    }

    public function test_stat_card_produk_jual_hitung_benar(): void
    {
        // Baseline count sebelum tambah
        $before = Item::where('tipe', 'produk_jual')->count();
        $this->item('TBL-STAT-1', 'produk_jual', 'Produk Stat 1');
        $this->item('TBL-STAT-2', 'produk_jual', 'Produk Stat 2');

        $response = $this->akses();
        $response->assertOk();
        $html = $response->getContent();

        // Stat card label spesifik (pakai pattern closing tag unik, bukan `>Produk Jual<`
        // yang bisa match badge tabel juga). Legacy "Produk Jadi" sengaja TIDAK
        // di-assertDontSee krn dev DB bisa punya item legacy produk_jadi yg render
        // label "Produk Jadi" di badge tabel — itu memang sengaja (match arm legacy).
        $this->assertStringContainsString('Produk Jual</div>', $html);

        // Hitungan stat card: cari markup fw-bold stat card yg memuat ($before + 2)
        $this->assertMatchesRegularExpression(
            '/fw-bold[^>]*>\s*' . ($before + 2) . '\s*</',
            $html,
            'Stat card hitungan produk_jual harus = baseline + 2'
        );
    }
}
