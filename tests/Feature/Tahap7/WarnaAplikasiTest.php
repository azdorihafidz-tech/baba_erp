<?php

namespace Tests\Feature\Tahap7;

use App\Models\PengaturanUmum;
use App\Support\Branding;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * 4.48 — Warna Aplikasi di Pengaturan Umum. Bawaan = warna lama (tampilan
 * tidak berubah setelah deploy); Owner bisa memilih tema/warna sendiri.
 */
class WarnaAplikasiTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    protected function setUp(): void
    {
        parent::setUp();
        app(Branding::class)->lupakan();
    }

    protected function tearDown(): void
    {
        app(Branding::class)->lupakan(); // cache warna uji jangan terbawa ke test lain
        parent::tearDown();
    }

    private function setting(): PengaturanUmum
    {
        return PengaturanUmum::query()->firstOrFail();
    }

    private function simpan(array $warna, string $role = 'owner')
    {
        $s = $this->setting();

        return $this->actingAs($this->buatUser($role))->put(route('pengaturan.umum.update'), array_merge([
            'nama_perusahaan' => $s->nama_perusahaan, 'nama_singkat' => $s->nama_singkat, 'tagline' => $s->tagline,
        ], $warna));
    }

    public function test_bawaan_sama_dengan_warna_lama(): void
    {
        $this->setting()->update(['warna_utama' => null, 'warna_sidebar' => null, 'warna_latar' => null]);
        app(Branding::class)->lupakan();
        $b = app(Branding::class);

        $this->assertTrue($b->warnaBawaan());
        $css = $b->cssVariabel();
        $this->assertStringContainsString('--brand-utama:#FF6B00', $css);
        $this->assertStringContainsString('--brand-sidebar:#1A1A1A', $css);
        $this->assertStringContainsString('--brand-latar:#FFF8E7', $css);
        $this->assertStringContainsString('--brand-utama-teks:#FFFFFF', $css);       // menu aktif tetap tulisan putih
        $this->assertStringContainsString('--brand-sidebar-teks-rgb:255, 255, 255', $css);

        $this->actingAs($this->buatUser('owner'))->followingRedirects()->get(route('dashboard'))->assertOk()
            ->assertSee('<style id="brandWarna">:root{--brand-utama:#FF6B00', false)
            ->assertSee('--sidebar-bg: var(--brand-sidebar);', false)
            ->assertSee('window.BRAND', false);
        auth()->logout();
        $this->get(route('login'))->assertOk()
            ->assertSee('<style id="brandWarna">', false)
            ->assertSee('var(--brand-sidebar) 0%', false);
    }

    public function test_owner_simpan_tema_dan_seluruh_tampilan_ikut(): void
    {
        $kopi = Branding::TEMA['kopi'];
        $this->simpan(['warna_utama' => $kopi['utama'], 'warna_sidebar' => $kopi['sidebar'], 'warna_latar' => strtolower($kopi['latar'])])
            ->assertSessionHasNoErrors()->assertRedirect(route('pengaturan.umum'));

        $s = $this->setting()->fresh();
        $this->assertSame([$kopi['utama'], $kopi['sidebar'], $kopi['latar']], [$s->warna_utama, $s->warna_sidebar, $s->warna_latar]);

        $this->actingAs($this->buatUser('owner'))->followingRedirects()->get(route('dashboard'))->assertOk()
            ->assertSee('--brand-utama:' . $kopi['utama'], false)
            ->assertSee('--brand-sidebar:' . $kopi['sidebar'], false)
            ->assertSee('<meta name="theme-color" content="' . $kopi['utama'] . '">', false);

        $this->get(route('pengaturan.umum'))->assertOk()
            ->assertSee('id="kartuWarna"', false)->assertSee('value="' . $kopi['utama'] . '"', false);
        $this->assertSame(substr($kopi['latar'], 1), ltrim(app(Branding::class)->warna('latar'), '#')); // Excel header
    }

    public function test_warna_sama_dengan_bawaan_disimpan_kosong(): void
    {
        $this->simpan(Branding::WARNA_BAWAAN)->assertSessionHasNoErrors();
        $s = $this->setting()->fresh();
        $this->assertNull($s->warna_utama);
        $this->assertNull($s->warna_sidebar);
        $this->assertNull($s->warna_latar);
    }

    public function test_tanpa_isian_warna_tidak_mengubah_warna(): void
    {
        $this->setting()->update(['warna_utama' => '#2E8B57']);
        $s = $this->setting();
        $this->actingAs($this->buatUser('owner'))->put(route('pengaturan.umum.update'), ['nama_perusahaan' => $s->nama_perusahaan])
            ->assertSessionHasNoErrors();
        $this->assertSame('#2E8B57', $this->setting()->fresh()->warna_utama);
    }

    public function test_validasi_warna(): void
    {
        $this->simpan(['warna_utama' => 'oranye', 'warna_latar' => '#1A1A1A'])
            ->assertSessionHasErrors(['warna_utama', 'warna_latar']);
        $this->assertNotSame('#1A1A1A', $this->setting()->fresh()->warna_latar);
    }

    public function test_sidebar_terang_tulisan_jadi_gelap(): void
    {
        $this->simpan(['warna_sidebar' => '#FFFFFF', 'warna_utama' => '#FFC107'])->assertSessionHasNoErrors();
        $css = app(Branding::class)->cssVariabel();
        $this->assertStringContainsString('--brand-sidebar-teks-rgb:26, 26, 26', $css);
        $this->assertStringContainsString('--brand-utama-teks:#1A1A1A', $css);
    }

    public function test_hanya_owner_yang_bisa_mengubah(): void
    {
        $this->simpan(['warna_utama' => '#D62828'], 'admin_pusat')->assertForbidden();
        $this->assertNotSame('#D62828', $this->setting()->fresh()->warna_utama);
    }

    public function test_panduan_dan_tooltip(): void
    {
        (new \Database\Seeders\WarnaAplikasiSeeder())->run();
        (new \Database\Seeders\WarnaAplikasiSeeder())->run();
        $konten = (string) \App\Models\Panduan::getBySlug('pengaturan-umum')?->konten;
        $this->assertSame(1, substr_count($konten, '<!-- WARNA-448 -->'));
        $this->assertDatabaseHas('tooltips', ['key' => 'pengaturan_umum.warna']);
        $this->actingAs($this->buatUser('owner'))->get(route('pengaturan.umum'))->assertOk()
            ->assertSee('<strong>Warna Aplikasi</strong>', false);
    }
}
