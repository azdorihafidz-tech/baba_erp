<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Panduan;
use Database\Seeders\Porting449Seeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * 4.49 Tahap C — POS tampilan HP (porting coffeeshop 512ccb2): bilah keranjang
 * melayang, kartu Pelanggan dilipat, produk 3 kolom — HANYA lewat CSS media
 * query ≤ 575px dan JS yang membaca keranjang. Elemen & alur POS lama tetap ada.
 */
class PosTampilanHpTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_pos_memuat_tampilan_hp_tanpa_mengubah_elemen_lama(): void
    {
        $cabang = Cabang::where('tipe', 'cabang')->where('is_active', true)->orderBy('id')->firstOrFail();
        $res = $this->actingAs($this->buatUser('owner', $cabang->id))->withSession(['active_cabang_id' => $cabang->id])
            ->get(route('penjualan.pos'))->assertOk();

        $res->assertSee('id="posBarHp"', false)
            ->assertSee('id="posBarKeranjang"', false)->assertSee('id="posBarBayar"', false)
            ->assertSee('id="posHeader"', false)
            ->assertSee('id="cardPelanggan"', false)->assertSee('id="cardItemOrder"', false)
            ->assertSee('@media (max-width: 575.98px)', false)
            ->assertSee('#posBarHp { display: none; }', false)          // di luar HP disembunyikan
            ->assertSee("ke('panelBayar')", false);

        // Elemen & alur lama tetap ada (tablet/PC tidak berubah)
        $res->assertSee('id="formPos"', false)->assertSee('id="btnProses"', false)->assertSee('id="btnSaveBill"', false)
            ->assertSee('id="gridProduk"', false)->assertSee('id="itemContainer"', false)->assertSee('id="totalBayarDisplay"', false)
            ->assertSee('id="panelBayar"', false)->assertSee('id="panelBayarKaki"', false)
            ->assertSee('id="btnModeDesktop"', false)->assertSee('id="btnModeTablet"', false)
            ->assertSee('Cek Antrian')->assertSee('PROSES ORDER');

        // Bilah HP berada di luar form transaksi (tidak ikut terkirim)
        $html = $res->getContent();
        $akhirFormPos = strpos($html, '</form>', strpos($html, 'id="formPos"'));
        $this->assertGreaterThan($akhirFormPos, strpos($html, '<div id="posBarHp"'));
    }

    public function test_panduan_pos_menjelaskan_tampilan_hp_idempotent(): void
    {
        (new Porting449Seeder())->run();
        (new Porting449Seeder())->run();
        $konten = (string) Panduan::getBySlug('pos')?->konten;
        $this->assertSame(1, substr_count($konten, '<!-- POS-HP-449 -->'));
        $this->assertStringContainsString('Memakai POS di HP', $konten);
    }
}
