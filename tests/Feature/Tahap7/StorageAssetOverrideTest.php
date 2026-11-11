<?php

namespace Tests\Feature\Tahap7;

use App\Models\Item;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap 7 D'mentai (2026-09-16) — fix foto produk tidak tampil di production
 * Rumah Web (symlink diblokir + .htaccess bawaan blokir /storage/). Fix:
 * override asset() ('storage/xxx' -> 'asset/xxx') di App\Support\StorageAwareUrlGenerator
 * + route baru '/asset/{path}' (StorageAssetController) yang stream file
 * dari storage/app/public/ lewat Laravel, TANPA symlink sama sekali.
 */
class StorageAssetOverrideTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    // ===== 1. asset() override path rewriting =====

    public function test_asset_storage_path_di_override_jadi_asset_path(): void
    {
        $url = asset('storage/produk/xxx.png');

        $this->assertStringContainsString('/asset/produk/xxx.png', $url);
        $this->assertStringNotContainsString('/storage/produk/xxx.png', $url);
    }

    public function test_asset_path_lain_tidak_terpengaruh(): void
    {
        $this->assertStringEndsWith('/css/app.css', asset('css/app.css'));
        $this->assertStringEndsWith('/js/app.js', asset('js/app.js'));
        $this->assertStringEndsWith('/images/logo.png', asset('images/logo.png'));
    }

    public function test_asset_url_absolute_tidak_terpengaruh(): void
    {
        $url = asset('http://external-cdn.com/storage/xxx.png');

        $this->assertEquals('http://external-cdn.com/storage/xxx.png', $url);
    }

    public function test_asset_path_storage_di_tengah_string_tidak_ikut_ter_rewrite(): void
    {
        // Cuma path yang LITERAL DIAWALI 'storage/' yang di-rewrite -- bukan
        // yang kebetulan mengandung kata 'storage' di tengah/lain posisi.
        $url = asset('images/mystorage/logo.png');

        $this->assertStringContainsString('/images/mystorage/logo.png', $url);
        $this->assertStringNotContainsString('/asset/', $url);
    }

    // ===== 2. Fitur lain yang bergantung ke UrlGenerator TIDAK rusak =====

    public function test_route_helper_tetap_normal(): void
    {
        $this->assertStringEndsWith('/login', route('login'));
    }

    public function test_signed_url_tetap_bisa_dibuat_dan_valid(): void
    {
        $signed = \Illuminate\Support\Facades\URL::signedRoute('login');

        $this->assertStringContainsString('signature=', $signed);

        // Buktikan signature-nya VALID (key resolver ter-preserve dgn benar,
        // bukan cuma "generate sesuatu" tapi genuinely verifiable).
        $request = \Illuminate\Http\Request::create($signed, 'GET');
        $this->assertTrue($request->hasValidSignature());
    }

    // ===== 3. Route /asset/{path} beneran serve file =====

    public function test_route_asset_serve_file_yang_ada(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('produk/test-foto.png', UploadedFile::fake()->image('test.png')->get());

        $response = $this->get('/asset/produk/test-foto.png');

        $response->assertOk();
    }

    public function test_route_asset_404_untuk_file_yang_tidak_ada(): void
    {
        Storage::fake('public');

        $response = $this->get('/asset/produk/tidak-ada.png');

        $response->assertNotFound();
    }

    public function test_route_asset_menolak_path_traversal(): void
    {
        Storage::fake('public');

        $response = $this->get('/asset/..%2F..%2F.env');

        $response->assertNotFound();
    }

    // ===== 4. End-to-end: halaman Produk Jual + POS render URL foto baru =====

    public function test_halaman_produk_jual_render_url_asset_bukan_storage(): void
    {
        Storage::fake('public');
        $foto = UploadedFile::fake()->image('produk.jpg', 500, 500);
        $path = $foto->store('produk', 'public');

        $admin = $this->buatUser('admin_pusat');
        $item = Item::create([
            'kode_item' => 'PJ-ASSET-001', 'nama_item' => 'Produk Test Asset URL', 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => 10000, 'is_active' => true, 'foto' => $path,
        ]);

        $response = $this->actingAs($admin)->get('/master/produk-jual');

        $response->assertOk();
        $response->assertSee('/asset/' . $path, false);
        $response->assertDontSee('/storage/' . $path, false);
    }

    public function test_pos_render_url_asset_bukan_storage(): void
    {
        Storage::fake('public');
        $foto = UploadedFile::fake()->image('produk.jpg', 500, 500);
        $path = $foto->store('produk', 'public');

        $cabang = $this->cabangPertama();
        $kasir = $this->buatUser('kasir', $cabang->id);
        // Item TANPA kategori tidak match query grid POS (whereHas('category',...)
        // butuh relasi ada) -- pakai kategori existing yang bukan 'TMB'.
        $kategori = \App\Models\ItemCategory::where('kode_kategori', '!=', 'TMB')->firstOrFail();
        $item = Item::create([
            'kode_item' => 'PJ-ASSET-002', 'nama_item' => 'Produk Test Asset POS', 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => 10000, 'is_active' => true, 'foto' => $path,
            'item_category_id' => $kategori->id,
        ]);

        $response = $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])->get('/penjualan/pos');

        $response->assertOk();
        $response->assertSee('/asset/' . $path, false);
        $response->assertDontSee('/storage/' . $path, false);
    }
}
