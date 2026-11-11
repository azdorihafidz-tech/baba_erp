<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Gap ditemukan saat test manual: konten Panduan sudah ada di DB tapi
 * tombol "Cara Pakai" (<x-panduan-button>) belum di-embed di halaman fitur
 * baru — user harus manual cari lewat menu Panduan. Test ini pastikan
 * setiap halaman fitur baru punya tombol yang mengarah ke slug yang BENAR
 * (bukan cross-link ke slug lain).
 */
class PanduanButtonEmbedTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function cabangOperasional(): Cabang
    {
        return Cabang::where('tipe', 'cabang')->orderBy('id')->firstOrFail();
    }

    private function assertPunyaPanduanButton($response, string $slug): void
    {
        $response->assertOk();
        $modalId = 'panduanModal-' . Str::slug($slug);
        $response->assertSee('data-bs-target="#' . $modalId . '"', false);
    }

    public function test_bahan_baku_index_punya_tombol_cara_pakai(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $this->assertPunyaPanduanButton($this->actingAs($admin)->get('/master/bahan-baku'), 'bahan-baku');
    }

    public function test_bahan_baku_create_punya_tombol_cara_pakai(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $this->assertPunyaPanduanButton($this->actingAs($admin)->get('/master/bahan-baku/create'), 'bahan-baku');
    }

    public function test_bahan_baku_edit_punya_tombol_cara_pakai(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = Item::where('kode_item', 'BB-BHN-001')->firstOrFail();
        $this->assertPunyaPanduanButton($this->actingAs($admin)->get("/master/bahan-baku/{$item->id}/edit"), 'bahan-baku');
    }

    public function test_produk_jual_index_punya_tombol_cara_pakai(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $this->assertPunyaPanduanButton($this->actingAs($admin)->get('/master/produk-jual'), 'produk-jual');
    }

    public function test_produk_jual_create_punya_tombol_cara_pakai_dan_item_varian(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $response = $this->actingAs($admin)->get('/master/produk-jual/create');
        $this->assertPunyaPanduanButton($response, 'produk-jual');
        // Section Varian harus punya tombol TERPISAH ke slug item-varian (bukan cross-link ke produk-jual)
        $response->assertSee('data-bs-target="#panduanModal-item-varian"', false);
    }

    public function test_produk_jual_edit_punya_tombol_cara_pakai(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = Item::where('kode_item', 'PJ-DIM-001')->firstOrFail();
        $response = $this->actingAs($admin)->get("/master/produk-jual/{$item->id}/edit");
        $this->assertPunyaPanduanButton($response, 'produk-jual');
        $response->assertSee('data-bs-target="#panduanModal-item-varian"', false);
    }

    public function test_setoran_kasir_index_punya_tombol_cara_pakai(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $this->assertPunyaPanduanButton($this->actingAs($admin)->get('/setoran-kasir'), 'setoran-kasir');
    }

    public function test_setoran_kasir_create_punya_tombol_cara_pakai(): void
    {
        $cabang = $this->cabangOperasional();
        $kasir = $this->buatUser('kasir', $cabang->id);
        $response = $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])->get('/setoran-kasir/create');
        $this->assertPunyaPanduanButton($response, 'setoran-kasir');
    }

    public function test_setoran_kasir_show_punya_tombol_cara_pakai(): void
    {
        $cabang = $this->cabangOperasional();
        $kasir = $this->buatUser('kasir', $cabang->id);
        \App\Models\Kas::create([
            'cabang_id' => $cabang->id, 'nama_kas' => 'Kas Tunai Test', 'tipe_kas' => 'tunai',
            'default_untuk' => 'tunai', 'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true,
        ]);
        $setoran = app(\App\Services\SetoranKasirService::class)->submitSetoran([
            'cabang_id' => $cabang->id, 'tanggal' => now()->format('Y-m-d'), 'user_id' => $kasir->id, 'total_disetor' => 0,
        ]);

        $response = $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])->get("/setoran-kasir/{$setoran->id}");
        $this->assertPunyaPanduanButton($response, 'setoran-kasir');
    }

    public function test_laporan_setoran_kasir_punya_tombol_cara_pakai(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $this->assertPunyaPanduanButton($this->actingAs($admin)->get('/laporan/setoran-kasir'), 'laporan-setoran-kasir');
    }

    public function test_dashboard_pusat_punya_tombol_cara_pakai_dashboard_owner(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $this->assertPunyaPanduanButton($this->actingAs($admin)->get('/dashboard/pusat'), 'dashboard-owner');
    }

    public function test_pengaturan_umum_punya_tombol_cara_pakai(): void
    {
        $owner = $this->buatUser('owner');
        $this->assertPunyaPanduanButton($this->actingAs($owner)->get('/pengaturan/umum'), 'pengaturan-umum');
    }

    // ===== Regresi =====

    public function test_regresi_pos_tombol_cara_pakai_tidak_hilang(): void
    {
        $cabang = $this->cabangOperasional();
        $kasir = $this->buatUser('kasir', $cabang->id);
        $response = $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])->get('/penjualan/pos');
        $this->assertPunyaPanduanButton($response, 'pos');
    }
}
