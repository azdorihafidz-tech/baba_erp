<?php

namespace Tests\Feature\Tahap7;

use App\Enums\TipeCabang;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\Stock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Sprint 4.34 (2026-10-08) — Master Barang Lengkap READ-ONLY + Dashboard/Laporan
 * Stok EXCLUDE produk_jual/produk_tambahan + Produk Jual track_stok dipaksa false.
 *
 * Keputusan Owner: 1 model. Barang jadi tetap masuk sbg bahan_baku, produk jual
 * = POS display saja + resep ke bahan baku. Stok fisik real cuma di
 * bahan_baku/kemasan/tambahan_gratis (= Item::TIPE_DAPAT_DIBELI, lihat [[4.33]]).
 */
class MasterBarangReadonlyDanStokFilterTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    protected function setUp(): void
    {
        parent::setUp();
        if (Cabang::count() < 1) {
            Cabang::firstOrCreate(['kode_cabang' => 'TST-MB1'], [
                'nama_cabang' => 'Cabang Test MB 1', 'tipe' => TipeCabang::Cabang, 'is_active' => true,
            ]);
        }
    }

    private function itemAktif(string $tipe, string $nama, bool $trackStok = true): Item
    {
        return Item::create([
            'kode_item' => 'MB-' . strtoupper(Str::random(6)),
            'nama_item' => $nama,
            'tipe'      => $tipe,
            'satuan'    => 'pcs',
            'is_active' => true,
            'track_stok' => $trackStok,
        ]);
    }

    private function stokAwal(Item $item, int $qty): Stock
    {
        return Stock::create([
            'item_id'   => $item->id,
            'lokasi_id' => $this->cabangPertama()->id,
            'qty'       => $qty,
            'qty_minimum' => 10,
        ]);
    }

    // ===== Master Barang Lengkap read-only =====

    public function test_item_index_tidak_tampilkan_tombol_tambah_item_lama(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $response = $this->actingAs($user)->get(route('item.index'));
        $response->assertOk();
        $html = $response->getContent();
        // Tombol baru 2 arah muncul
        $this->assertStringContainsString('+ Bahan/Kemasan', $html);
        $this->assertStringContainsString('+ Produk Jual', $html);
        // Tombol lama "Tambah Item" tunggal yang mengarah ke item.create tidak lagi dominant
        $this->assertStringNotContainsString(route('item.create'), $html);
    }

    public function test_item_create_redirect_ke_bahan_baku_index(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $response = $this->actingAs($user)->get(route('item.create'));
        $response->assertRedirect(route('master.bahan-baku.index'));
    }

    public function test_item_edit_bahan_baku_redirect_ke_master_bahan_baku(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $bb = $this->itemAktif('bahan_baku', 'MB-Edit BB');
        $response = $this->actingAs($user)->get(route('item.edit', $bb));
        $response->assertRedirect(route('master.bahan-baku.edit', $bb));
    }

    public function test_item_edit_produk_jual_redirect_ke_master_produk_jual(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $pj = $this->itemAktif('produk_jual', 'MB-Edit PJ');
        $response = $this->actingAs($user)->get(route('item.edit', $pj));
        $response->assertRedirect(route('master.produk-jual.edit', $pj));
    }

    public function test_item_destroy_tidak_hapus_data_lagi(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $bb = $this->itemAktif('bahan_baku', 'MB-Destroy BB');
        $response = $this->actingAs($user)->delete(route('item.destroy', $bb));
        $response->assertRedirect(route('item.index'));
        // Data TIDAK terhapus
        $this->assertDatabaseHas('items', ['id' => $bb->id, 'deleted_at' => null]);
    }

    // ===== Dashboard & Laporan Stok exclude produk_jual =====

    public function test_dashboard_stok_exclude_produk_jual_dari_list(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $bb = $this->itemAktif('bahan_baku', 'MB-STOK-BB-Unik');
        $pj = $this->itemAktif('produk_jual', 'MB-STOK-PJ-Unik');
        $this->stokAwal($bb, 50);
        $this->stokAwal($pj, 50); // meskipun ada stok, produk jual harus hidden

        $response = $this->actingAs($user)->get(route('stok.dashboard'));
        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('MB-STOK-BB-Unik', $html);
        $this->assertStringNotContainsString('MB-STOK-PJ-Unik', $html);
    }

    public function test_stok_index_exclude_produk_jual(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        session(['active_cabang_id' => $this->cabangPertama()->id]);
        $bb = $this->itemAktif('bahan_baku', 'MB-SI-BB-Unik');
        $pj = $this->itemAktif('produk_jual', 'MB-SI-PJ-Unik');
        $this->stokAwal($bb, 50);
        $this->stokAwal($pj, 50);

        $response = $this->actingAs($user)->get(route('stok.index'));
        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('MB-SI-BB-Unik', $html);
        $this->assertStringNotContainsString('MB-SI-PJ-Unik', $html);
    }

    public function test_laporan_stok_exclude_produk_jual(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $bb = $this->itemAktif('bahan_baku', 'MB-LS-BB-Unik');
        $pj = $this->itemAktif('produk_jual', 'MB-LS-PJ-Unik');
        $this->stokAwal($bb, 50);
        $this->stokAwal($pj, 50);

        $response = $this->actingAs($user)->get(route('laporan.stok'));
        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('MB-LS-BB-Unik', $html);
        $this->assertStringNotContainsString('MB-LS-PJ-Unik', $html);
    }

    public function test_stok_dashboard_dropdown_tipe_tanpa_produk_jadi(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $response = $this->actingAs($user)->get(route('stok.dashboard'));
        $html = $response->getContent();
        // Dropdown tipe cuma 3 opsi dapat_dibeli
        $this->assertStringContainsString('value="bahan_baku"', $html);
        $this->assertStringContainsString('value="kemasan"', $html);
        $this->assertStringContainsString('value="tambahan_gratis"', $html);
        $this->assertStringNotContainsString('value="produk_jadi"', $html);
    }

    // ===== Produk Jual track_stok dipaksa false =====

    public function test_produk_jual_track_stok_dipaksa_false_meski_user_kirim_true(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $kategori = \App\Models\ItemCategory::firstOrCreate(['kode_kategori' => 'CAT-MB-X'], ['nama_kategori' => 'Cat MB X']);

        $response = $this->actingAs($user)->post(route('master.produk-jual.store'), [
            'kode_item' => 'MB-PJ-TRACK-1',
            'nama_item' => 'MB Produk Track Test',
            'tipe'      => 'produk_jual',
            'satuan'    => 'pcs',
            'harga_jual' => 15000,
            'item_category_id' => $kategori->id,
            'is_active' => '1',
            'track_stok' => '1', // user kirim true, HARUS DIABAIKAN
            'cabang_aktif' => [$this->cabangPertama()->id],
        ]);

        $this->assertDatabaseHas('items', [
            'kode_item'  => 'MB-PJ-TRACK-1',
            'track_stok' => false,
        ]);
    }
}
