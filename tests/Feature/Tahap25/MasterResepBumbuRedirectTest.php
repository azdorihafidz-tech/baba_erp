<?php

namespace Tests\Feature\Tahap25;

use App\Models\Item;
use App\Models\ResepBumbu;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap 2.5 D'mentai — Master Resep Bumbu TERDAMPAK: source-of-truth edit
 * resep pindah ke form Produk Jual (item_id linkage). List tetap tampil,
 * tombol Edit sekarang redirect.
 */
class MasterResepBumbuRedirectTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_index_resep_bumbu_tetap_tampil(): void
    {
        // master.resep_bumbu.* sengaja TIDAK di-assign default ke role
        // manapun (lihat CLAUDE.md/RolePermissionSeeder) — cuma Owner yang
        // bypass semua permission otomatis, jadi test ini pakai Owner.
        $owner = $this->buatUser('owner');

        $response = $this->actingAs($owner)->get('/master/resep-bumbu');

        $response->assertOk();
        $response->assertSee('Dimsum Mentai');
    }

    public function test_edit_resep_dengan_item_id_redirect_ke_produk_jual(): void
    {
        $owner = $this->buatUser('owner');
        $resep = ResepBumbu::whereNotNull('item_id')->firstOrFail();

        $response = $this->actingAs($owner)->get("/master/resep-bumbu/{$resep->id}/edit");

        $response->assertRedirect(route('master.produk-jual.edit', $resep->item_id));
    }

    public function test_edit_resep_tanpa_item_id_tetap_pakai_form_lama(): void
    {
        $owner = $this->buatUser('owner');
        $resepLegacy = ResepBumbu::create([
            'nama' => 'Resep Legacy Tanpa Item', 'kode' => 'RESEP-LEGACY-TEST', 'is_active' => true,
        ]);

        $response = $this->actingAs($owner)->get("/master/resep-bumbu/{$resepLegacy->id}/edit");

        $response->assertOk();
        $response->assertViewIs('master.resep-bumbu.edit');
    }
}
