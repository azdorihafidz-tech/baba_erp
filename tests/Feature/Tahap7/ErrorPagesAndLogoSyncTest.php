<?php

namespace Tests\Feature\Tahap7;

use App\Models\PengaturanUmum;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

class ErrorPagesAndLogoSyncTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_halaman_403_render_dengan_brand_dan_logo(): void
    {
        $manajer = $this->buatUser('manajer_cabang');

        // Endpoint owner-only untuk memicu 403 pada role rendah
        $response = $this->actingAs($manajer)->get('/coa');

        $response->assertForbidden();
        $response->assertSee('Akses Ditolak');
        $response->assertSee('images/logo.png', false);
        $response->assertSee('#E6B422', false);
    }

    public function test_halaman_404_render_dengan_brand_dan_logo(): void
    {
        $user = $this->buatUser('kasir');

        $response = $this->actingAs($user)->get('/halaman-tidak-ada-xyz-123');

        $response->assertNotFound();
        $response->assertSee('Halaman Tidak Ditemukan');
        $response->assertSee('images/logo.png', false);
    }

    public function test_halaman_500_view_compile_dan_isi_benar(): void
    {
        // Render langsung (bukan trigger exception asli) supaya deterministik,
        // fokus verifikasi tampilan/konten sesuai brand.
        $html = view('errors.500')->render();

        $this->assertStringContainsString('Ada Masalah di Server', $html);
        $this->assertStringContainsString('images/logo.png', $html);
        $this->assertStringContainsString('#E6B422', $html);
    }

    /**
     * 4.44 — observer TIDAK lagi menyalin logo ke public/images/logo.png (file
     * itu ikut git; menimpanya membuat git pull di server bentrok). Logo lama
     * di disk public dilayani lewat /img/{path}.
     */
    public function test_logo_lama_di_disk_public_dipakai_tanpa_menimpa_logo_bawaan(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('logo-baru.png', 200, 200);
        $path = $file->store('logo', 'public');

        $isiBawaan = file_get_contents(public_path('images/logo.png'));

        $setting = PengaturanUmum::getSetting();
        $setting->logo_path = $path;
        $setting->save();

        $this->assertSame($isiBawaan, file_get_contents(public_path('images/logo.png')));
        $this->assertSame(url('/img/' . $path), strtok(app(\App\Support\Branding::class)->logoUrl(), '?'));
    }

    // ===== Regresi cleanup dead code =====

    public function test_regresi_welcome_route_tidak_ada_dan_root_tetap_jalan(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('welcome'));

        // root '/' adalah closure custom (bukan view('welcome')) — tetap harus jalan
        $response = $this->get('/');
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_regresi_login_page_tetap_render_dengan_brand(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('BABA PARFUME');
    }

    public function test_regresi_profile_edit_tetap_bisa_diakses(): void
    {
        $user = $this->buatUser('kasir');

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
    }
}
