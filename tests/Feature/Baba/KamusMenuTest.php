<?php

namespace Tests\Feature\Baba;

use App\Enums\RoleUser;
use App\Models\Cabang;
use App\Models\KamusMenu;
use App\Models\Panduan;
use App\Models\Tooltip;
use App\Models\User;
use Database\Seeders\KamusMenuSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 4.50 Kamus Menu — halaman, pencarian, hak lihat, tombol header, dan GUARD RAIL:
 * setiap menu di sidebar wajib punya penjelasan di Kamus Menu.
 */
class KamusMenuTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Menu sidebar yang penjelasannya BELUM ditulis. Sejak 4.50 Tahap 2 SEMUA menu sudah punya entri — daftar ini harus tetap KOSONG.
     * HANYA BOLEH BERKURANG. Menu baru di sidebar TIDAK boleh ditambahkan ke sini —
     * tulis penjelasannya di database/seeders/kamus/*.php (CLAUDE.md 3.6 poin 6).
     */
    private const BELUM = [
    ];

    /** Link sidebar yang nama route-nya dipilih lewat kondisi (tidak tertangkap regex). */
    private const EKSTRA = ['laporan.hr.absensi', 'laporan.hr.penggajian', 'laporan.hr.evaluasi'];

    protected function setUp(): void
    {
        parent::setUp();
        (new KamusMenuSeeder())->run();
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => RoleUser::Owner, 'is_active' => true]);
    }

    private function kasirTanpaIzinStok(): User
    {
        $u = User::factory()->create(['role' => RoleUser::Kasir, 'is_active' => true]);
        // pastikan kasir pada test ini tidak punya izin transfer & produksi
        DB::table('role_permissions')->where('role', 'kasir')
            ->whereIn('permission_id', DB::table('permissions')->whereIn('name', ['stok.transfer', 'produksi.view'])->pluck('id'))
            ->delete();
        Cache::forget('role_permissions_kasir');

        return $u;
    }

    /** @return string[] */
    private function routeSidebar(): array
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        preg_match('/<nav id="sidebar">(.*?)<\/nav>/s', $layout, $m);
        preg_match_all("/route\\('([a-zA-Z0-9_.-]+)'/", $m[1] ?? '', $r);

        return array_values(array_unique(array_merge($r[1], self::EKSTRA)));
    }

    public function test_guard_setiap_menu_sidebar_punya_kamus(): void
    {
        $pola = collect(KamusMenuSeeder::entri())->flatMap(fn ($e) => $e['route_names'] ?? [])->all();
        $kurang = [];
        foreach ($this->routeSidebar() as $nama) {
            if (in_array($nama, self::BELUM, true)) {
                continue;
            }
            if (! collect($pola)->contains(fn ($p) => Str::is($p, $nama))) {
                $kurang[] = $nama;
            }
        }
        $this->assertSame([], $kurang, 'Menu sidebar tanpa penjelasan Kamus Menu: ' . implode(', ', $kurang)
            . '. Tulis entrinya di database/seeders/kamus/*.php (CLAUDE.md 3.6 poin 6).');
    }

    public function test_guard_daftar_belum_hanya_berisi_menu_yang_memang_belum_ada_kamusnya(): void
    {
        $pola = collect(KamusMenuSeeder::entri())->flatMap(fn ($e) => $e['route_names'] ?? []);
        $sudah = array_filter(self::BELUM, fn ($n) => $pola->contains(fn ($p) => Str::is($p, $n)));
        $this->assertSame([], array_values($sudah), 'Hapus dari BELUM karena sudah punya Kamus: ' . implode(', ', $sudah));
    }

    public function test_guard_route_dan_terkait_valid(): void
    {
        $semuaRoute = collect(Route::getRoutes()->getRoutesByName())->keys();
        $entri = KamusMenuSeeder::entri();
        foreach ($entri as $slug => $e) {
            foreach ($e['route_names'] ?? [] as $p) {
                $this->assertTrue($semuaRoute->contains(fn ($n) => Str::is($p, $n)), "Kamus '{$slug}': pola route '{$p}' tidak cocok dengan route mana pun.");
            }
            foreach ($e['izin'] ?? [] as $i) {
                if (! str_starts_with($i, '@')) {
                    $this->assertTrue(DB::table('permissions')->where('name', $i)->exists(), "Kamus '{$slug}': izin '{$i}' tidak ada di tabel permissions.");
                }
            }
            $this->assertStringContainsString('## ', $e['konten'], "Kamus '{$slug}' belum memakai judul bagian (##).");
        }
    }

    public function test_index_dan_cari_untuk_owner(): void
    {
        $this->actingAs($this->owner())->get(route('kamus-menu.index'))
            ->assertOk()->assertSee('Kamus Menu')->assertSee('Transfer Stok')->assertSee('Cara Melakukan');

        $r = $this->actingAs($this->owner())->get(route('kamus-menu.index', ['q' => 'bagaimana pindah barang ke toko']));
        $r->assertOk()->assertSee('Bagaimana memindahkan barang dari gudang ke toko?')->assertSee('Transfer Stok');
    }

    /** BABA: kelompok Toko Online, entri khusus BABA, istilah dimsum tidak tersisa, Tim IT & paket. */
    public function test_isi_khusus_baba(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->get(route('kamus-menu.index'))->assertOk()
            ->assertSee('Toko Online')->assertSee('Pesanan Online')->assertSee('Produk Parfum Toko')->assertSee('Jadwal Otomatis');
        $hasil = KamusMenu::cari('ro belum masuk aplikasi member', KamusMenu::semuaAktif());
        $this->assertSame('cara-ro-belum-masuk', $hasil->first()['kamus']->slug);
        foreach (KamusMenuSeeder::entri() as $slug => $e) {
            $this->assertDoesNotMatchRegularExpression('/dimsum|gyoza|bakso|bumbu pusat|jasa giling|frozen/i', $e['konten'] . ' ' . $e['ringkasan'] . ' ' . $e['judul'], "Kamus '{$slug}' masih memuat istilah dimsum");
        }
        // Tim IT & Paket: hanya Tim IT
        $this->actingAs($owner)->get(route('kamus-menu.show', 'paket-langganan'))->assertNotFound();
        $teknisi = User::factory()->create(['role' => RoleUser::Teknisi, 'is_active' => true]);
        $this->actingAs($teknisi)->get(route('kamus-menu.show', 'paket-langganan'))->assertOk()->assertSee('Aplikasi member ikut paket');
        // modul mati → entri hilang (juga Owner)
        \App\Support\Paket::simpan(array_values(array_diff(array_keys(\App\Support\Paket::MODUL), ['toko_online'])), null, 7, null);
        $this->actingAs($owner)->get(route('kamus-menu.show', 'pesanan-online'))->assertNotFound();
        \App\Support\Paket::simpan(array_keys(\App\Support\Paket::MODUL), null, 7, null);
        $this->actingAs($owner)->get(route('kamus-menu.show', 'pesanan-online'))->assertOk();
        // tombol header di halaman Jadwal Otomatis menuju entrinya
        $this->actingAs($owner)->get(route('pengaturan.jadwal'))->assertOk()->assertSee(route('kamus-menu.show', 'jadwal-otomatis'), false);
    }

    public function test_pencarian_mengutamakan_entri_yang_memuat_semua_kata(): void
    {
        $hasil = KamusMenu::cari('batal order', KamusMenu::semuaAktif());
        $this->assertNotEmpty($hasil);
        $this->assertContains($hasil->first()['kamus']->slug, ['cara-membatalkan-transaksi', 'riwayat-order']);
        $this->assertTrue(KamusMenu::cari('zzzz qqqq', KamusMenu::semuaAktif())->isEmpty());
    }

    public function test_detail_menampilkan_isi_izin_dan_tombol_buka_menu(): void
    {
        $this->actingAs($this->owner())->get(route('kamus-menu.show', 'transfer-stok'))
            ->assertOk()
            ->assertSee('Apa yang terjadi kalau tombol diklik')
            ->assertSee('<table>', false)
            ->assertSee('Transfer Stok')          // teks izin dari tabel permissions
            ->assertSee('Menu terkait')
            ->assertSee(route('stock-transfer.index'), false);
    }

    public function test_entri_hanya_tampil_untuk_user_yang_boleh_membuka_menunya(): void
    {
        $kasir = $this->kasirTanpaIzinStok();
        $this->actingAs($kasir)->get(route('kamus-menu.show', 'transfer-stok'))->assertNotFound();
        $this->actingAs($kasir)->get(route('kamus-menu.index'))->assertOk()->assertDontSee('Memindahkan bahan antar lokasi sendiri tanpa jual-beli');
    }

    public function test_tombol_kamus_di_header_menuju_penjelasan_menu_yang_dibuka(): void
    {
        $owner = $this->owner();
        $cabang = Cabang::where('is_active', true)->where('tipe', '!=', 'gudang_pusat')->first();
        $this->actingAs($owner)->withSession(['active_cabang_id' => $cabang?->id])
            ->get(route('stock-transfer.index'))
            ->assertOk()->assertSee(route('kamus-menu.show', 'transfer-stok'), false);

        $this->actingAs($owner)->get(route('panduan.index'))
            ->assertOk()->assertSee(route('kamus-menu.show', 'panduan'), false);

        // halaman tanpa entri khusus (Profil) → tombol header menuju daftar Kamus
        $html = $this->actingAs($owner)->get(route('profile.edit'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '~<a href="' . preg_quote(route('kamus-menu.index'), '~') . '"\s+class="btn btn-sm btn-light~',
            $html
        );
    }

    public function test_panduan_tooltip_dan_link_sidebar_ada(): void
    {
        $this->assertTrue(Panduan::where('slug', 'kamus-menu')->where('aktif', true)->exists());
        $this->assertTrue(Tooltip::where('key', 'kamus_menu.cari')->exists());
        $this->actingAs($this->owner())->get(route('kamus-menu.index'))
            ->assertSee('Cara Pakai')->assertSee(route('kamus-menu.index'), false);
    }

    public function test_seeder_idempotent_dan_menghapus_entri_yang_tidak_ada_di_file(): void
    {
        KamusMenu::create(['slug' => 'entri-usang', 'judul' => 'X', 'kelompok' => 'Bantuan', 'letak' => 'X', 'ringkasan' => 'X', 'konten' => '## X']);
        (new KamusMenuSeeder())->run();
        (new KamusMenuSeeder())->run();
        $this->assertFalse(KamusMenu::where('slug', 'entri-usang')->exists());
        $this->assertSame(count(KamusMenuSeeder::entri()), KamusMenu::count());
    }
}
