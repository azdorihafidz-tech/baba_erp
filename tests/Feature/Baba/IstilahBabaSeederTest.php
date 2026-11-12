<?php

namespace Tests\Feature\Baba;

use App\Models\Panduan;
use App\Models\Tooltip;
use Database\Seeders\IstilahBabaSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** ERP BABA Tahap 4–5 — istilah dimsum di panduan & tooltip diganti sekali jalan, cache ikut diperbarui, idempoten. */
class IstilahBabaSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ganti_istilah_sekali_jalan_cache_dan_idempoten(): void
    {
        Tooltip::where('key', 'uji.istilah')->delete();
        Tooltip::create(['key' => 'uji.istilah', 'title' => 'Uji', 'modul' => 'lainnya', 'aktif' => true,
            'content' => 'Produk = bakso, sosis, tempura (Berkah Mulyo produksi & jual sendiri). Jasa Giling = pelanggan bawa daging sendiri untuk digiling (tarif per kg). Lain: mesin giling.']);
        $this->assertStringContainsString('bakso', Tooltip::get('uji.istilah')->content); // cache terisi teks lama

        $p = Panduan::updateOrCreate(['slug' => 'karyawan'], ['judul' => 'Cara Pakai Menu Karyawan', 'konten' => "## Tujuan\nData karyawan di Berkah Mulyo.", 'modul' => 'hr', 'aktif' => true]);
        $this->assertNotNull(Panduan::getBySlug('karyawan'));

        (new IstilahBabaSeeder())->run();
        $t = Tooltip::get('uji.istilah')->content;
        $this->assertStringNotContainsString('bakso', $t);
        $this->assertStringNotContainsString('giling', $t);
        $this->assertStringContainsString('parfum yang diproduksi', $t);
        $k = Panduan::getBySlug('karyawan')->konten;
        $this->assertStringContainsString('BABA PARFUME', $k);
        $this->assertStringContainsString('Password awal dibuat **acak**', $k);

        (new IstilahBabaSeeder())->run();
        $this->assertSame(1, substr_count(Panduan::where('slug', 'karyawan')->value('konten'), 'Password awal dibuat **acak**'));
        $this->assertSame($p->id, Panduan::where('slug', 'karyawan')->value('id'));
    }
}
