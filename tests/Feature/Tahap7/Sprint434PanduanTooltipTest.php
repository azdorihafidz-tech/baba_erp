<?php

namespace Tests\Feature\Tahap7;

use App\Models\Panduan;
use App\Models\Tooltip;
use Database\Seeders\Sprint434PanduanUpdateSeeder;
use Database\Seeders\TooltipKontenSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Sprint 4.33/4.34/4.35 (2026-10-08) — update panduan + tooltip + permission
 * untuk mencerminkan perubahan: Master Barang read-only, PO/Transfer/Request
 * tolak produk_jual, Dashboard/Laporan Stok exclude produk_jual, 4 field
 * pindahan ke Bahan Baku form.
 */
class Sprint434PanduanTooltipTest extends TestCase
{
    use DatabaseTransactions;

    public function test_seeder_append_section_ke_9_panduan(): void
    {
        // Siapkan 9 panduan dummy
        $slugs = [
            'master-barang', 'bahan-baku', 'produk-jual', 'pembelian',
            'transfer-stok', 'permintaan-stok', 'dashboard-stok',
            'stok-barang', 'laporan-stok',
        ];
        foreach ($slugs as $slug) {
            Panduan::updateOrCreate(['slug' => $slug], [
                'judul'  => ucfirst($slug) . ' Test',
                'konten' => "## Konten lama\nIni konten lama untuk {$slug}.",
                'modul'  => 'test',
                'urutan' => 1,
                'aktif'  => true,
            ]);
        }

        $seeder = new Sprint434PanduanUpdateSeeder();
        $seeder->run();

        // Semua 9 panduan harus punya marker + section baru
        foreach ($slugs as $slug) {
            $panduan = Panduan::where('slug', $slug)->first();
            $this->assertNotNull($panduan, "Panduan {$slug} tidak ada");
            $this->assertStringContainsString('<!-- SPRINT434 -->', $panduan->konten, "Marker tidak ada di {$slug}");
            $this->assertStringContainsString('Update 2026-10-08', $panduan->konten, "Section baru tidak ada di {$slug}");
            $this->assertStringContainsString('Konten lama', $panduan->konten, "Konten lama hilang di {$slug}");
        }
    }

    public function test_seeder_idempotent_tidak_dobel_append(): void
    {
        Panduan::updateOrCreate(['slug' => 'master-barang'], [
            'judul'  => 'Test',
            'konten' => "## Konten lama",
            'modul'  => 'test',
            'urutan' => 1,
            'aktif'  => true,
        ]);

        $seeder = new Sprint434PanduanUpdateSeeder();
        $seeder->run();
        $kontenPertama = Panduan::where('slug', 'master-barang')->value('konten');

        $seeder->run(); // Run kedua
        $kontenKedua = Panduan::where('slug', 'master-barang')->value('konten');

        $this->assertSame($kontenPertama, $kontenKedua, 'Append jalan 2x (dobel), harusnya idempotent');
    }

    public function test_tooltip_master_bahan_baku_3_field_baru_terdaftar_di_seeder(): void
    {
        // Baca file seeder: pastikan 3 key tooltip baru hadir di source
        $seederFile = file_get_contents(base_path('database/seeders/TooltipKontenSeeder.php'));
        $this->assertStringContainsString("'master_bahan_baku.jenis'", $seederFile);
        $this->assertStringContainsString("'master_bahan_baku.track_stok'", $seederFile);
        $this->assertStringContainsString("'master_bahan_baku.deskripsi'", $seederFile);
        // Isi tooltip mencerminkan konteks yang dimaksud
        $this->assertStringContainsString('Perlengkapan', $seederFile);
        $this->assertStringContainsString('Lacak Stok', $seederFile);
    }

    public function test_tooltip_terpasang_di_form_bahan_baku_view(): void
    {
        $create = file_get_contents(base_path('resources/views/master/bahan-baku/create.blade.php'));
        $edit = file_get_contents(base_path('resources/views/master/bahan-baku/edit.blade.php'));

        foreach ([$create, $edit] as $view) {
            $this->assertStringContainsString('master_bahan_baku.jenis', $view);
            $this->assertStringContainsString('master_bahan_baku.track_stok', $view);
            $this->assertStringContainsString('master_bahan_baku.deskripsi', $view);
        }
    }

    public function test_permission_item_crud_bertanda_deprecated(): void
    {
        $seeder = new \Database\Seeders\PermissionSeeder();
        $seeder->run();

        $this->assertDatabaseHas('permissions', [
            'name' => 'item.create',
            'display_name' => 'Tambah Master Barang [DEPRECATED]',
        ]);
        $this->assertDatabaseHas('permissions', [
            'name' => 'item.edit',
            'display_name' => 'Edit Master Barang [DEPRECATED]',
        ]);
        // 4.43: item.delete diberi akhiran "(tidak dipakai — ...)", penanda DEPRECATED tetap ada
        $this->assertStringContainsString('Hapus Master Barang [DEPRECATED]',
            (string) \App\Models\Permission::where('name', 'item.delete')->value('display_name'));
        // item.view TIDAK deprecated (menu overview masih berguna)
        $this->assertDatabaseHas('permissions', [
            'name' => 'item.view',
            'display_name' => 'Lihat Master Barang',
        ]);
    }
}
