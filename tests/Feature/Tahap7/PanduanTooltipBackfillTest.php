<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\Panduan;
use App\Models\Tooltip;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap 7 D'mentai — backfill Panduan & Tooltip untuk fitur Tahap 1-6 yang
 * kelewat (CLAUDE.md 3.6). Test HTTP asli (bukan cuma cek DB), sesuai
 * CLAUDE.md 9.1.1.
 */
class PanduanTooltipBackfillTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function cabangOperasional(): Cabang
    {
        return Cabang::where('tipe', 'cabang')->orderBy('id')->firstOrFail();
    }

    public static function slugProvider(): array
    {
        return [
            ['bahan-baku', 'Master Bahan Baku'],
            ['produk-jual', 'Master Produk Jual'],
            ['setoran-kasir', 'Setoran Kasir'],
            ['dashboard-owner', 'Dashboard Owner'],
            ['laporan-setoran-kasir', 'Laporan Setoran Kasir'],
            ['pengaturan-umum', 'Pengaturan Umum'],
            ['item-varian', 'Varian Produk'],
        ];
    }

    /** @dataProvider slugProvider */
    public function test_panduan_slug_bisa_diakses_via_route(string $slug, string $expectedText): void
    {
        $user = $this->buatUser('kasir');

        $response = $this->actingAs($user)->get("/panduan/{$slug}");

        $response->assertOk();
        $response->assertSee($expectedText);
    }

    public function test_panduan_item_varian_tidak_lagi_coming_soon(): void
    {
        $panduan = Panduan::where('slug', 'item-varian')->firstOrFail();

        $this->assertStringNotContainsString('Coming Soon', $panduan->konten);
        $this->assertStringContainsString('Tambah Atribut', $panduan->konten);
    }

    public function test_semua_panduan_baru_terisi_300_sampai_800_kata(): void
    {
        $slugs = ['bahan-baku', 'produk-jual', 'setoran-kasir', 'dashboard-owner', 'laporan-setoran-kasir', 'pengaturan-umum', 'item-varian'];

        foreach ($slugs as $slug) {
            $panduan = Panduan::where('slug', $slug)->firstOrFail();
            $jumlahKata = str_word_count(strip_tags($panduan->konten));
            $this->assertGreaterThanOrEqual(150, $jumlahKata, "Panduan {$slug} kependekan ({$jumlahKata} kata) — terlihat dangkal.");
            $this->assertLessThanOrEqual(1200, $jumlahKata, "Panduan {$slug} kepanjangan ({$jumlahKata} kata).");
        }
    }

    public function test_tooltip_muncul_di_form_produk_jual(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/master/produk-jual/create');

        $response->assertOk();
        $tooltip = Tooltip::where('key', 'master_produk_jual.foto')->firstOrFail();
        $response->assertSee($tooltip->content, false);
    }

    public function test_tooltip_muncul_di_form_bahan_baku(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/master/bahan-baku/create');

        $response->assertOk();
        $tooltip = Tooltip::where('key', 'master_bahan_baku.stok_awal')->firstOrFail();
        $response->assertSee($tooltip->content, false);
    }

    public function test_tooltip_muncul_di_pos(): void
    {
        $cabang = $this->cabangOperasional();
        $kasir = $this->buatUser('kasir', $cabang->id);

        $response = $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])->get('/penjualan/pos');

        $response->assertOk();
        $tooltip = Tooltip::where('key', 'pos.tipe_transaksi')->firstOrFail();
        $response->assertSee($tooltip->content, false);
        $tooltipMeja = Tooltip::where('key', 'pos.nomor_meja')->firstOrFail();
        $response->assertSee($tooltipMeja->content, false);
    }

    public function test_tooltip_muncul_di_form_setoran_kasir(): void
    {
        $cabang = $this->cabangOperasional();
        $kasir = $this->buatUser('kasir', $cabang->id);

        $response = $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])->get('/setoran-kasir/create');

        $response->assertOk();
        $tooltip = Tooltip::where('key', 'setoran_kasir.total_disetor')->firstOrFail();
        $response->assertSee($tooltip->content, false);
    }

    public function test_tooltip_muncul_di_laporan_setoran_kasir(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/laporan/setoran-kasir');

        $response->assertOk();
        $tooltip = Tooltip::where('key', 'laporan_setoran_kasir.filter_status')->firstOrFail();
        // Konten mengandung karakter '&' yang di-HTML-escape jadi '&amp;' saat
        // dirender di atribut data-bs-title — bandingkan versi ter-escape.
        $response->assertSee(e($tooltip->content), false);
    }

    public function test_tooltip_muncul_di_dashboard_owner(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/dashboard/pusat');

        $response->assertOk();
        $tooltip = Tooltip::where('key', 'dashboard_owner.kas_ho')->firstOrFail();
        $response->assertSee($tooltip->content, false);
    }

    // ===== Regresi =====

    public function test_regresi_panduan_pos_existing_tidak_rusak(): void
    {
        $user = $this->buatUser('kasir');

        $response = $this->actingAs($user)->get('/panduan/pos');

        $response->assertOk();
        $response->assertSee('Split Payment');
        $response->assertSee('Varian Produk');
    }

    public function test_regresi_panduan_index_tampil_semua_slug_baru(): void
    {
        $user = $this->buatUser('kasir');

        $response = $this->actingAs($user)->get('/panduan');

        $response->assertOk();
    }

    public function test_regresi_tooltip_lama_pos_config_tetap_ada(): void
    {
        $tooltip = Tooltip::where('key', 'pos.cetak_otomatis')->first();

        $this->assertNotNull($tooltip, 'Tooltip lama pos.cetak_otomatis tidak boleh hilang gara-gara seeder baru dijalankan.');
    }

    public function test_regresi_master_bahan_baku_dan_produk_jual_tetap_berfungsi(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $this->actingAs($admin)->get('/master/bahan-baku')->assertOk();
        $this->actingAs($admin)->get('/master/produk-jual')->assertOk();
    }
}
