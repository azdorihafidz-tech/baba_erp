<?php

namespace Tests\Feature\Tahap7;

use App\Models\Panduan;
use App\Models\Permission;
use App\Models\Tooltip;
use App\Support\HakAksesMenu;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * CLAUDE.md 4.43 Tahap 4 & 5 — centangan tanpa fungsi diberi label & tampil
 * redup, panduan Role & Hak Akses diperbarui, tooltip ditempel di halaman.
 */
class HakAksesPanduanTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_centangan_tanpa_fungsi_diberi_label(): void
    {
        foreach (HakAksesMenu::TIDAK_DIPAKAI as $name) {
            $this->assertStringContainsString('tidak berpengaruh', (string) Permission::where('name', $name)->value('display_name'), $name);
        }
        // izin yang sekarang dipakai tidak ikut diberi label
        foreach (['cabang.delete', 'user.delete', 'evaluasi.manage', 'penggajian.manage'] as $name) {
            $this->assertStringNotContainsString('tidak', (string) Permission::where('name', $name)->value('display_name'), $name);
        }
    }

    public function test_halaman_role_menampilkan_redup_dan_tooltip(): void
    {
        $this->seed(\Database\Seeders\Perbaikan438PanduanSeeder::class);
        $this->actingAs($this->buatUser('owner'))->get(route('role.index'))->assertOk()
            ->assertSee('text-muted fst-italic', false)
            ->assertSee('Lihat Laporan Stok (Stok, Pergerakan, Minimum)')
            ->assertSee('Izin Laporan');
        $this->assertStringContainsString('<x-panduan-button slug="role-hak-akses" />', file_get_contents(resource_path('views/role/index.blade.php')));
        $this->assertStringContainsString('<x-tooltip key="role.centangan_laporan" />', file_get_contents(resource_path('views/role/index.blade.php')));
    }

    public function test_panduan_role_diperbarui_dan_idempotent(): void
    {
        $this->seed(\Database\Seeders\Perbaikan438PanduanSeeder::class);
        $this->seed(\Database\Seeders\Perbaikan438PanduanSeeder::class);
        $konten = (string) Panduan::where('slug', 'role-hak-akses')->value('konten');

        $this->assertSame(1, substr_count($konten, '<!-- HAK-AKSES-443 -->'));
        $this->assertStringNotContainsString('tetap khusus role sistem', $konten);
        $this->assertStringContainsString('Lihat Laporan Stok', $konten);
        $this->assertLessThanOrEqual(1200, str_word_count(strip_tags($konten)));
        $this->assertTrue(Tooltip::where('key', 'role.centangan_laporan')->exists());

        $this->actingAs($this->buatUser('owner'))->get(route('panduan.show', 'role-hak-akses'))->assertOk()
            ->assertSee('setiap menu punya centangan sendiri');
    }
}
