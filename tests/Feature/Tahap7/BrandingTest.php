<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Order;
use App\Models\PengaturanUmum;
use App\Models\Tooltip;
use App\Support\Branding;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * 4.44 Poin 3 — Identitas Usaha dari Pengaturan Umum (porting erp-coffeeshop).
 * Tujuan utama Dimsum: setelah deploy tampilan TIDAK berubah ("D'mentai" ·
 * "Dimsum & Gyoza"), lalu bisa diganti Owner tanpa ubah program.
 */
class BrandingTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function setting(): PengaturanUmum
    {
        return PengaturanUmum::query()->firstOrFail();
    }

    private function ubah(array $data): void
    {
        $this->setting()->update($data);
        app(Branding::class)->lupakan();
    }

    protected function tearDown(): void
    {
        // Bersihkan logo upload uji (folder public/uploads/branding)
        app(Branding::class)->hapusFileLogo();
        parent::tearDown();
    }

    public function test_migration_mengisi_nilai_lama_supaya_tampilan_tidak_berubah(): void
    {
        $this->assertTrue(Schema::hasColumns('pengaturan_umums', ['nama_singkat', 'tagline', 'telepon']));
        $s = $this->setting();
        // BABA: identitas awal dari GoLiveSeeder (DB tes diisi sama)
        $this->assertSame('BABA PARFUME', $s->nama_singkat);
        $this->assertSame('Indonesia', $s->tagline);
    }

    public function test_sidebar_login_dan_judul_memakai_nama_singkat_dan_tagline(): void
    {
        $owner = $this->buatUser('owner', $this->cabangPertama()->id);

        $this->actingAs($owner)->followingRedirects()->get(route('dashboard'))->assertOk()
            ->assertSee('<div class="brand-name">BABA PARFUME</div>', false)
            ->assertSee('<div class="brand-sub">Indonesia</div>', false)
            ->assertSee("<title>ERP BABA PARFUME - ", false);

        auth()->logout();
        $this->get('/login')->assertOk()->assertSee("Masuk ke ERP BABA PARFUME");
    }

    public function test_ganti_identitas_langsung_tampil_tanpa_ubah_program(): void
    {
        $owner = $this->buatUser('owner', $this->cabangPertama()->id);

        $this->actingAs($owner)->put(route('pengaturan.umum.update'), [
            'nama_perusahaan' => 'Dimsum Nusantara Jaya',
            'nama_singkat'    => 'Dimsum NJ',
            'tagline'         => 'Dimsum Halal',
            'telepon'         => '0812-1111-2222',
        ])->assertRedirect(route('pengaturan.umum'));

        $this->actingAs($owner)->followingRedirects()->get(route('dashboard'))->assertOk()
            ->assertSee('<div class="brand-name">Dimsum NJ</div>', false)
            ->assertSee('Dimsum Halal')
            ->assertDontSee('<div class="brand-name">D&#039;mentai</div>', false);

        $b = app(Branding::class);
        $this->assertSame('Dimsum Nusantara Jaya', $b->nama());
        $this->assertSame('Dimsum Halal · 0812-1111-2222', $b->subjudul());
        $this->assertSame(['Terima kasih atas kunjungan Anda!', 'Dimsum NJ - Dimsum Halal'], $b->footerStrukBaris());
    }

    public function test_nama_singkat_dan_tagline_kosong_pakai_nama_usaha_tanpa_tagline(): void
    {
        $this->ubah(['nama_perusahaan' => 'Usaha Uji', 'nama_singkat' => null, 'tagline' => null]);
        $owner = $this->buatUser('owner', $this->cabangPertama()->id);

        $this->actingAs($owner)->followingRedirects()->get(route('dashboard'))->assertOk()
            ->assertSee('<div class="brand-name">Usaha Uji</div>', false)
            ->assertDontSee('class="brand-sub"', false);
    }

    public function test_upload_logo_baru_disimpan_png_dan_ikon_dibuat_otomatis(): void
    {
        $owner = $this->buatUser('owner', $this->cabangPertama()->id);
        $isiBawaan = file_get_contents(public_path('images/logo.png'));

        $this->actingAs($owner)->put(route('pengaturan.umum.update'), [
            'nama_perusahaan' => 'D\'mentai Indonesia',
            'nama_singkat'    => "D'mentai",
            'logo'            => UploadedFile::fake()->image('logo.jpg', 300, 200),
        ])->assertRedirect(route('pengaturan.umum'));

        $path = $this->setting()->logo_path;
        $this->assertStringStartsWith(Branding::FOLDER . '/logo-', $path);
        $this->assertFileExists(public_path($path));
        foreach (Branding::UKURAN_IKON as $u) {
            $this->assertFileExists(public_path(Branding::FOLDER . "/icon-{$u}.png"));
        }

        $b = app(Branding::class);
        $this->assertTrue($b->adaLogoUpload());
        $this->assertStringContainsString(Branding::FOLDER . '/logo-', $b->logoUrl());
        $this->assertStringContainsString(Branding::FOLDER . '/icon-96.png', $b->faviconUrl());
        $this->assertSame(public_path($path), $b->logoFile());
        // Logo bawaan repo (ikut git) tidak disentuh
        $this->assertSame($isiBawaan, file_get_contents(public_path('images/logo.png')));

        // PWA manifest ikut ikon baru
        $b->terapkanKeConfigPwa();
        $this->assertSame('/' . Branding::FOLDER . '/icon-192.png', config('laravelpwa.manifest.icons.192x192.path'));

        // Hapus logo → kembali ke bawaan
        $this->actingAs($owner)->put(route('pengaturan.umum.update'), [
            'nama_perusahaan' => 'D\'mentai Indonesia',
            'hapus_logo'      => 1,
        ])->assertRedirect();
        $this->assertNull($this->setting()->logo_path);
        $this->assertFileDoesNotExist(public_path($path));
        $this->assertFalse(app(Branding::class)->adaLogoUpload());
        $this->assertSame(asset('images/logo.png'), app(Branding::class)->logoUrl());
    }

    public function test_svg_ditolak(): void
    {
        $owner = $this->buatUser('owner', $this->cabangPertama()->id);

        $this->actingAs($owner)->put(route('pengaturan.umum.update'), [
            'nama_perusahaan' => 'X',
            'logo'            => UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml'),
        ])->assertSessionHasErrors('logo');
    }

    public function test_logo_tercatat_tapi_file_hilang_pakai_logo_bawaan_bukan_gambar_rusak(): void
    {
        Storage::fake('public');
        $this->ubah(['logo_path' => 'logo/tidak-ada.jpg']);

        $b = app(Branding::class);
        $this->assertFalse($b->adaLogoUpload());
        $this->assertSame(asset('images/logo.png'), $b->logoUrl());
        $this->assertSame(asset('images/logo-icon.png'), $b->faviconUrl());
    }

    public function test_tanpa_logo_upload_favicon_dan_pwa_tetap_bawaan(): void
    {
        $this->ubah(['logo_path' => null]);
        $b = app(Branding::class);
        $ikonLama = config('laravelpwa.manifest.icons');
        $b->terapkanKeConfigPwa();

        $this->assertSame(asset('images/logo-icon.png'), $b->faviconUrl());
        $this->assertSame($ikonLama, config('laravelpwa.manifest.icons'));
        $this->assertSame('BABA PARFUME', config('laravelpwa.manifest.short_name'));
        $this->assertSame('ERP BABA PARFUME', config('laravelpwa.manifest.name'));
    }

    public function test_struk_dan_pratinjau_bill_memakai_nama_singkat(): void
    {
        $order = Order::query()->firstOrFail();
        $order->cabang?->update(['footer_struk' => null]);
        $this->ubah(['nama_singkat' => 'Dimsum NJ', 'tagline' => 'Dimsum Halal']);
        $owner = $this->buatUser('owner', $order->cabang_id);

        $this->actingAs($owner)->get(route('penjualan.struk-modal', $order))->assertOk()
            ->assertSee('<div class="center bold header-store">Dimsum NJ</div>', false)
            ->assertSee('Dimsum NJ - Dimsum Halal');
    }

    public function test_laporan_pdf_dan_slip_tetap_render(): void
    {
        $owner = $this->buatUser('owner', $this->cabangPertama()->id);

        $this->actingAs($owner)->get(route('laporan.penjualan', ['export' => 'pdf']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $html = view('laporan.pdf.layout', ['judulLaporan' => 'Uji'])->render();
        $this->assertStringContainsString('BABA PARFUME', $html);
        $this->assertStringContainsString('Indonesia', $html);
    }

    public function test_tv_antrian_memakai_nama_usaha(): void
    {
        $cabang = Cabang::where('is_active', true)->firstOrFail();
        $cabang->update(['antrian_produksi_aktif' => true]);
        $this->ubah(['nama_perusahaan' => 'Dimsum Nusantara']);

        $this->get(route('antrian.display', $cabang))->assertOk()->assertSee('DIMSUM NUSANTARA');
    }

    public function test_panduan_dan_tooltip_identitas_usaha_tersedia(): void
    {
        $owner = $this->buatUser('owner', $this->cabangPertama()->id);

        $this->actingAs($owner)->get(route('panduan.show', 'pengaturan-umum'))->assertOk()
            ->assertSee('Nama Singkat')
            ->assertDontSee('disiapkan terpisah oleh developer');

        foreach (['nama_singkat', 'tagline', 'logo'] as $k) {
            $this->assertTrue(Tooltip::where('key', "pengaturan_umum.$k")->exists());
        }

        $this->actingAs($owner)->get(route('pengaturan.umum'))->assertOk()
            ->assertSee('name="nama_singkat"', false)
            ->assertSee('name="tagline"', false)
            ->assertSee('name="telepon"', false)
            ->assertSee('panduanModal-pengaturan-umum', false);
    }
}
