<?php

namespace Tests\Feature\Tahap25;

use App\Models\Item;
use App\Models\ItemCabang;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap 2.5 D'mentai — POS TERDAMPAK karena filter grid berubah dari
 * `tipe='produk_jadi'` (tunggal) jadi `whereIn(['produk_jual','produk_tambahan'])`
 * + tambahan filter ketersediaan per-cabang (`item_cabang`). Wajib ditest
 * karena ini perubahan struktur, bukan cuma penambahan menu baru.
 */
class PosImpactTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_grid_pos_tampil_produk_jual_dan_produk_tambahan(): void
    {
        $cabang = $this->cabangPertama();
        $kasir = $this->buatUser('kasir', $cabang->id);

        $response = $this->actingAs($kasir)
            ->withSession(['active_cabang_id' => $cabang->id])
            ->get('/penjualan/pos');

        $response->assertOk();
        $response->assertSee('Dimsum Ayam');       // produk_jual
        $response->assertSee('Saus Cabai Extra');   // produk_tambahan (item tambahan berbayar)
        $response->assertSee('Garpu Plastik');      // tambahan_gratis
    }

    public function test_item_cabang_fallback_item_lama_tanpa_row_tetap_tampil_di_semua_outlet(): void
    {
        $cabang = $this->cabangKedua(); // sengaja BUKAN cabang pertama
        $kasir = $this->buatUser('kasir', $cabang->id);

        $item = Item::where('kode_item', 'PJ-DIM-002')->firstOrFail();
        $this->assertSame(0, ItemCabang::where('item_id', $item->id)->count(), 'Prasyarat: item ini belum pernah di-assign eksplisit.');

        $response = $this->actingAs($kasir)
            ->withSession(['active_cabang_id' => $cabang->id])
            ->get('/penjualan/pos');

        $response->assertOk();
        $response->assertSee('Dimsum Udang', false); // PJ-DIM-002 tetap tampil (fallback aktif di semua cabang)
    }

    public function test_assign_produk_ke_sebagian_outlet_membuat_hilang_di_outlet_lain(): void
    {
        $cabangAktif = $this->cabangPertama();
        $cabangTidakAktif = $this->cabangKedua();
        $item = Item::where('kode_item', 'PJ-GYZ-001')->firstOrFail(); // Gyoza Original

        ItemCabang::create(['item_id' => $item->id, 'cabang_id' => $cabangAktif->id, 'is_active' => true]);
        ItemCabang::create(['item_id' => $item->id, 'cabang_id' => $cabangTidakAktif->id, 'is_active' => false]);

        $kasirAktif = $this->buatUser('kasir', $cabangAktif->id);
        $responseAktif = $this->actingAs($kasirAktif)
            ->withSession(['active_cabang_id' => $cabangAktif->id])
            ->get('/penjualan/pos');
        $responseAktif->assertSee('Gyoza Original');

        $kasirTidakAktif = $this->buatUser('kasir', $cabangTidakAktif->id);
        $responseTidakAktif = $this->actingAs($kasirTidakAktif)
            ->withSession(['active_cabang_id' => $cabangTidakAktif->id])
            ->get('/penjualan/pos');
        $responseTidakAktif->assertDontSee('Gyoza Original');
    }

    public function test_endpoint_ajax_varian_mengembalikan_data_utk_item_ber_varian(): void
    {
        $cabang = $this->cabangPertama();
        $kasir = $this->buatUser('kasir', $cabang->id);
        $item = Item::where('kode_item', 'PJ-DIM-003')->firstOrFail(); // punya_varian = true

        $response = $this->actingAs($kasir)->getJson("/pos/item/{$item->id}/varian");

        $response->assertOk();
        $response->assertJsonStructure(['item_id', 'nama_item', 'attributes', 'variants']);
        $response->assertJsonCount(3, 'variants'); // S, M, L
    }

    public function test_endpoint_ajax_varian_404_utk_item_tanpa_varian(): void
    {
        $cabang = $this->cabangPertama();
        $kasir = $this->buatUser('kasir', $cabang->id);
        $item = Item::where('kode_item', 'PJ-MNM-001')->firstOrFail(); // tidak punya varian

        $response = $this->actingAs($kasir)->getJson("/pos/item/{$item->id}/varian");

        $response->assertNotFound();
    }

    public function test_produk_tambahan_tidak_ikut_grid_utama_produk_jual(): void
    {
        // Saus Cabai Extra (tipe=produk_tambahan, kategori TMB) TIDAK boleh
        // ikut ke-query oleh $produkJadi (grid utama, exclude kategori TMB) —
        // cuma boleh muncul lewat $itemTambahan. Dicek di level controller
        // (bukan hitung substring HTML) karena halaman juga sengaja meng-embed
        // JSON gabungan produkJadi+itemTambahan utk search dropdown JS (by
        // design, lihat komentar $produkJadiJson di pos.blade.php) — jadi
        // hitung kemunculan teks mentah di HTML BUKAN sinyal valid utk "dobel
        // tampil ke user".
        $cabang = $this->cabangPertama();
        auth()->login($this->buatUser('kasir', $cabang->id));
        session(['active_cabang_id' => $cabang->id]);

        $data = app()->call([app(\App\Http\Controllers\PenjualanController::class), 'pos'])->getData();

        $this->assertFalse(
            $data['produkJadi']->contains(fn ($i) => $i->kode_item === 'TB-TMB-002'),
            'Saus Cabai Extra tidak boleh ikut ke grid utama $produkJadi.'
        );
        $this->assertTrue(
            $data['itemTambahan']->contains(fn ($i) => $i->kode_item === 'TB-TMB-002'),
            'Saus Cabai Extra harus tetap ada di $itemTambahan (satu-satunya tempat tampil).'
        );
        $this->assertTrue(
            $data['itemTambahan']->contains(fn ($i) => $i->kode_item === 'TB-TMB-001'),
            'Garpu Plastik (tambahan_gratis) juga harus tetap ada di $itemTambahan.'
        );
    }
}
