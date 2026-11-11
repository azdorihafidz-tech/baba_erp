<?php

namespace Tests\Feature\Tahap7;

use App\Models\Closing;
use App\Models\Panduan;
use App\Models\Permission;
use App\Models\Tooltip;
use App\Services\ClosingArsipService;
use Carbon\Carbon;
use Database\Seeders\ClosingPanduanSeeder;
use Database\Seeders\ClosingPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\Feature\Tahap7\Concerns\ClosingTestHelpers;
use Tests\TestCase;

/**
 * Closing / Tutup Buku Tahap 3-4 (2026-10-17) — panduan, tombol Cara
 * Pakai, tooltip, pengingat dashboard, seeder permission additive.
 */
class ClosingPanduanDashboardTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;
    use ClosingTestHelpers;

    private const TGL = '2026-03-10';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ClosingPanduanSeeder::class);
    }

    public function test_panduan_closing_lengkap_dan_bisa_dibuka(): void
    {
        $p = Panduan::where('slug', 'closing')->firstOrFail();
        foreach (['## Tujuan', '## Langkah-langkah', '## Catatan Penting', '## Troubleshooting', 'Buka Kunci', 'Mutasi Internal'] as $teks) {
            $this->assertStringContainsString($teks, $p->konten);
        }

        $this->actingAs($this->buatUser('owner'))->get(route('panduan.show', 'closing'))->assertOk()->assertSee('Closing / Tutup Buku');
    }

    public function test_tombol_cara_pakai_dan_tooltip_tampil_di_halaman_closing(): void
    {
        $owner = $this->buatUser('owner');
        $cab = $this->cabang('Outlet Panduan');
        $tunai = $this->kas($cab, 'Laci Panduan', 'tunai');
        $closing = app(ClosingArsipService::class)->simpanHarian($cab->id, Carbon::parse(self::TGL), $owner->id, [$tunai->id => 0]);

        $index = $this->actingAs($owner)->get(route('closing.index', ['jenis' => 'harian', 'tanggal' => '2026-03-11', 'cabang_id' => $cab->id]));
        $index->assertOk()->assertSee('panduanModal-closing', false)->assertSee('tooltip-helper', false)
            ->assertSee(Tooltip::where('key', 'closing.jenis')->value('content'));

        $this->actingAs($owner)->get(route('closing.riwayat'))->assertOk()->assertSee('panduanModal-closing', false);
        $this->actingAs($owner)->get(route('closing.show', $closing))->assertOk()->assertSee('panduanModal-closing', false);

        $this->actingAs($owner)->get(route('pengaturan.umum'))->assertOk()
            ->assertSee(Tooltip::where('key', 'pengaturan_umum.tanggal_mulai_periode')->value('content'));
    }

    public function test_pengingat_dashboard_muncul_lalu_hilang_setelah_closing(): void
    {
        $cab = $this->cabang('Outlet Pengingat');
        $tunai = $this->kas($cab, 'Laci Pengingat', 'tunai');
        $kemarin = Carbon::yesterday()->toDateString();
        $this->order($cab, 50000, 20000, 'selesai', $kemarin);
        $kasir = $this->buatUser('kasir', $cab->id);

        $this->actingAs($kasir)->withSession(['active_cabang_id' => $cab->id])->get(route('dashboard.cabang'))
            ->assertOk()->assertSee('hari belum di-closing')->assertSee('Closing sekarang');

        app(ClosingArsipService::class)->simpanHarian($cab->id, Carbon::yesterday(), $kasir->id, [$tunai->id => 50000]);

        $this->actingAs($kasir)->withSession(['active_cabang_id' => $cab->id])->get(route('dashboard.cabang'))
            ->assertOk()->assertDontSee('hari belum di-closing');
    }

    public function test_pengingat_tidak_tampil_untuk_role_tanpa_permission(): void
    {
        $cab = $this->cabang('Outlet Tanpa Hak');
        $this->order($cab, 50000, 20000, 'selesai', Carbon::yesterday()->toDateString());

        $html = view('components.closing-pengingat', ['cabangId' => $cab->id])->render();
        $this->assertStringNotContainsString('hari belum di-closing', $html); // tanpa login

        $this->actingAs($this->buatUser('operator_produksi', $cab->id));
        $html = view('components.closing-pengingat', ['cabangId' => $cab->id])->render();
        $this->assertStringNotContainsString('hari belum di-closing', $html);
    }

    public function test_seeder_permission_production_tidak_menghapus_kustomisasi_role(): void
    {
        $custom = Permission::firstOrCreate(['name' => 'laporan.jam_ramai.view'], ['display_name' => 'x', 'group' => 'laporan']);
        DB::table('role_permissions')->insertOrIgnore(['role' => 'kasir', 'permission_id' => $custom->id]);
        $closingView = Permission::where('name', 'closing.view')->value('id');
        DB::table('role_permissions')->where('role', 'kasir')->where('permission_id', $closingView)->delete();

        $this->seed(ClosingPermissionSeeder::class);
        $this->seed(ClosingPermissionSeeder::class); // idempotent

        $this->assertTrue(DB::table('role_permissions')->where('role', 'kasir')->where('permission_id', $custom->id)->exists());
        $this->assertSame(1, DB::table('role_permissions')->where('role', 'kasir')->where('permission_id', $closingView)->count());
        $this->assertSame(6, Permission::where('name', 'like', 'closing.%')->count());
        $this->assertFalse(DB::table('role_permissions')
            ->where('permission_id', Permission::where('name', 'closing.buka_kunci')->value('id'))->exists(), 'buka_kunci Owner-only');
    }
}
