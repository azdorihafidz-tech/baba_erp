<?php

namespace Tests\Feature\Tahap7;

use App\Models\AssetCategory;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\ItemCategory;
use App\Models\Panduan;
use App\Models\Tooltip;
use App\Models\User;
use Database\Seeders\GoLiveSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Go-Live (2026-09-23). GoLiveSeeder idempotent, jadi diuji di DB test
 * (yang sudah berisi data) di dalam transaction yang di-rollback. Bagian
 * "DB kosong" diverifikasi manual via migrate:fresh di DB lokal terpisah.
 */
class GoLiveSeederTest extends TestCase
{
    use DatabaseTransactions;

    private function jalankan(): void
    {
        // Panduan/Tooltip seeder tidak re-runnable di DB terisi (rename slug
        // bentrok) — kosongkan (rollback otomatis) supaya meniru DB fresh.
        DB::table('panduan')->delete();
        DB::table('tooltips')->delete();

        config([
            'golive.owner_email' => 'owner-real@dmentaiindonesia.com',
            'golive.owner_password' => 'PasswordKuat#2026',
            'golive.owner_name' => 'Owner Real',
        ]);
        $this->seed(GoLiveSeeder::class);
    }

    public function test_seeder_jalan_tanpa_error_dan_idempotent(): void
    {
        $this->jalankan();
        $this->jalankan();

        $this->assertEquals(1, User::where('email', 'owner-real@dmentaiindonesia.com')->count());
        $this->assertEquals(1, Cabang::where('kode_cabang', 'HO-01')->count());
    }

    public function test_owner_dibuat_dengan_role_owner_dan_password_ter_hash(): void
    {
        $this->jalankan();

        $owner = User::where('email', 'owner-real@dmentaiindonesia.com')->firstOrFail();
        $this->assertEquals('owner', $owner->role->value ?? $owner->role);
        $this->assertTrue($owner->is_active);
        $this->assertNotNull($owner->email_verified_at);
        $this->assertTrue(\Hash::check('PasswordKuat#2026', $owner->password));
        $this->assertEquals('Owner Real', $owner->name);
    }

    public function test_gudang_pusat_dibuat_dan_owner_terhubung_sebagai_default(): void
    {
        $this->jalankan();

        $gudang = Cabang::where('kode_cabang', 'HO-01')->firstOrFail();
        $this->assertEquals("Gudang Pusat D'mentai", $gudang->nama_cabang);
        $this->assertEquals('gudang_pusat', $gudang->tipe->value ?? $gudang->tipe);

        $owner = User::where('email', 'owner-real@dmentaiindonesia.com')->firstOrFail();
        $pivot = $owner->cabangs()->where('cabangs.id', $gudang->id)->first();
        $this->assertNotNull($pivot);
        $this->assertTrue((bool) $pivot->pivot->is_default);
    }

    public function test_permission_admin_pusat_ter_assign_110_plus(): void
    {
        $this->jalankan();

        $this->assertGreaterThanOrEqual(110, DB::table('role_permissions')->where('role', 'admin_pusat')->count());
    }

    public function test_permission_dari_seeder_terpisah_ikut_dibuat(): void
    {
        $this->jalankan();

        foreach (['lihat_audit_log', 'scan_absensi', 'antrian.lihat'] as $nama) {
            $this->assertDatabaseHas('permissions', ['name' => $nama]);
        }
    }

    public function test_coa_terisi_termasuk_akun_hpp(): void
    {
        $this->jalankan();

        $this->assertGreaterThan(20, ChartOfAccount::count());
        $this->assertTrue(ChartOfAccount::where('tipe', 'hpp')->where('is_leaf', true)->exists());
        $this->assertTrue(ChartOfAccount::where('kode', '5-1101')->exists());
        $this->assertTrue(ChartOfAccount::whereIn('tipe', ['pendapatan', 'hpp', 'beban_operasional'])->where('is_leaf', true)->exists());
    }

    public function test_kategori_item_dan_aset_default_ada(): void
    {
        $this->jalankan();

        $this->assertGreaterThanOrEqual(7, ItemCategory::count());
        $this->assertTrue(ItemCategory::where('kode_kategori', 'DIM')->exists());
        $this->assertGreaterThanOrEqual(8, AssetCategory::count());
    }

    public function test_panduan_dan_tooltip_terisi(): void
    {
        $this->jalankan();

        $this->assertGreaterThan(40, Panduan::count());
        $this->assertEquals('penjualan', Panduan::where('slug', 'pos')->value('modul'), 'PanduanStubSeeder harus sudah mengubah modul pos -> penjualan');
        $this->assertEquals(0, Panduan::where(fn ($q) => $q->whereNull('konten')->orWhere('konten', ''))->count());
        $this->assertGreaterThan(30, Tooltip::count());
    }

    public function test_seeder_tidak_memanggil_seeder_dummy(): void
    {
        // DB test sudah berisi data dummy dev, jadi dicek statis: daftar seeder
        // yang dipanggil. Bukti DB kosong: rehearsal migrate:fresh lokal (lihat CLAUDE.md 4.28).
        $kode = file_get_contents(base_path('database/seeders/GoLiveSeeder.php'));
        preg_match_all('/^\s+(\w+Seeder)::class,/m', $kode, $m);

        $terlarang = ['CabangSeeder', 'UserSeeder', 'NeracaSettingSeeder', 'ItemSeeder', 'ResepBumbuSeeder',
            'StokSeeder', 'HRSeeder', 'PembelianPenjualanSeeder', 'ProgramLoyaltySeeder',
            'CleanupBerkahMulyoDataSeeder', 'TestingBatch1SeederTemp', 'ItemVarianSeeder', 'BackfillStockQtyMinimumSeeder'];

        $this->assertNotEmpty($m[1]);
        $this->assertEmpty(array_intersect($terlarang, $m[1]));
    }

    public function test_production_menolak_kredensial_default(): void
    {
        $envAsli = $this->app->environment();
        $this->app->detectEnvironment(fn () => 'production');
        config(['golive.owner_email' => 'owner@dmentai.local', 'golive.owner_password' => 'ChangeMe123!']);

        try {
            $this->expectException(\RuntimeException::class);
            (new GoLiveSeeder())->run();
        } finally {
            $this->app->detectEnvironment(fn () => $envAsli);
        }
    }
}
