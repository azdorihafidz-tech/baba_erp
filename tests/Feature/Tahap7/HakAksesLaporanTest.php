<?php

namespace Tests\Feature\Tahap7;

use App\Models\CustomRole;
use App\Models\Permission;
use App\Models\User;
use App\Support\HakAksesMenu;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * CLAUDE.md 4.43 Tahap 1 — setiap laporan punya izin sendiri, bagian
 * Laporan di sidebar tampil kalau punya salah satu izin laporan.
 */
class HakAksesLaporanTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private string $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->role = 'ujilap' . rand(1000, 9999);
        CustomRole::create(['kode' => $this->role, 'nama' => 'Uji Laporan', 'akses_semua_cabang' => false]);
    }

    protected function tearDown(): void
    {
        Cache::forget("role_permissions_{$this->role}");
        Cache::forget('all_permission_names');
        parent::tearDown();
    }

    private function userDengan(array $izin): User
    {
        $ids = Permission::whereIn('name', $izin)->pluck('id');
        $this->assertCount(count($izin), $ids, 'izin tidak ditemukan: ' . implode(',', $izin));
        DB::table('role_permissions')->insert($ids->map(fn ($id) => ['role' => $this->role, 'permission_id' => $id])->all());
        Cache::forget("role_permissions_{$this->role}");

        return $this->buatUser($this->role, $this->cabangPertama()->id);
    }

    public function test_izin_baru_ada_di_kelompok_laporan(): void
    {
        foreach (array_filter(HakAksesMenu::IZIN_BARU, fn ($i) => str_starts_with($i['name'], 'laporan.')) as $izin) {
            $this->assertSame('laporan', Permission::where('name', $izin['name'])->value('group'), $izin['name']);
        }
        $this->assertStringContainsString('tidak dipakai', Permission::where('name', 'laporan.view')->value('display_name'));
    }

    public function test_cukup_izin_laporan_stok_untuk_buka_laporan_stok(): void
    {
        $u = $this->userDengan(['laporan.stok.view']);

        $this->actingAs($u)->get(route('panduan.index'))->assertOk()
            ->assertSee(route('laporan.stok'), false)
            ->assertDontSee(route('laporan.keuangan.laba-rugi'), false)
            ->assertDontSee(route('laporan.saldo-kas'), false);

        foreach (['laporan.stok', 'laporan.stok.pergerakan', 'laporan.stok.minimum'] as $r) {
            $this->actingAs($u)->get(route($r))->assertOk();
        }
        foreach (['laporan.keuangan.laba-rugi', 'laporan.saldo-kas', 'laporan.audit-bukti', 'laporan.setoran', 'laporan.kategori', 'laporan.penjualan', 'keuangan.laporan'] as $r) {
            $this->actingAs($u)->get(route($r))->assertForbidden();
        }
    }

    public function test_laporan_lain_tampil_walau_tanpa_izin_lama(): void
    {
        // Dulu: punya izin Laporan Setoran Kasir tapi tanpa "Lihat Laporan" → bagian Laporan tidak tampil.
        $u = $this->userDengan(['laporan.setoran_kasir.view']);
        $this->actingAs($u)->get(route('panduan.index'))->assertOk()
            ->assertSee('id="laporanMenu"', false)
            ->assertSee(route('laporan.setoran-kasir.index'), false);
    }

    public function test_tanpa_izin_laporan_bagian_laporan_tidak_tampil(): void
    {
        $u = $this->userDengan(['stok.view', 'keuangan.view']);
        $this->actingAs($u)->get(route('panduan.index'))->assertOk()->assertDontSee('id="laporanMenu"', false);
        $this->actingAs($u)->get(route('laporan.stok'))->assertForbidden();
    }

    public function test_tiap_laporan_dijaga_izinnya_sendiri(): void
    {
        $peta = [
            'laporan.penjualan.view'     => ['laporan.penjualan'],
            'laporan.keuangan.view'      => ['laporan.keuangan.laba-rugi', 'laporan.keuangan.arus-kas', 'keuangan.laporan'],
            'laporan.transfer_dana.view' => ['laporan.setoran'],
            'laporan.kategori.view'      => ['laporan.kategori'],
            'laporan.audit_bukti.view'   => ['laporan.audit-bukti'],
            'laporan.saldo_kas.view'     => ['laporan.saldo-kas'],
            'laporan.hr_absensi.view'    => ['laporan.hr.absensi'],
            'laporan.hr_penggajian.view' => ['laporan.hr.penggajian'],
            'laporan.hr_evaluasi.view'   => ['laporan.hr.evaluasi'],
            'laporan.aset.view'          => ['laporan.aset', 'laporan.aset.penyusutan'],
            'laporan.bep.view'           => ['laporan.bep'],
        ];
        $u = $this->userDengan(array_keys($peta));
        foreach ($peta as $routes) {
            foreach ($routes as $r) {
                $this->actingAs($u)->get(route($r))->assertOk();
            }
        }
        $html = $this->actingAs($u)->get(route('panduan.index'))->getContent();
        foreach (['laporan.penjualan', 'laporan.keuangan.laba-rugi', 'laporan.setoran', 'laporan.kategori', 'laporan.audit-bukti', 'laporan.saldo-kas', 'laporan.hr.absensi', 'laporan.aset', 'laporan.bep'] as $r) {
            $this->assertStringContainsString(route($r), $html, $r);
        }
    }

    public function test_tombol_pindah_laporan_hr_ikut_izin(): void
    {
        $u = $this->userDengan(['laporan.hr_absensi.view']);
        $this->actingAs($u)->get(route('laporan.hr.absensi'))->assertOk()->assertDontSee(route('laporan.hr.penggajian'), false);
        $this->actingAs($u)->get(route('laporan.hr.penggajian'))->assertForbidden();
    }

    public function test_laporan_absensi_dan_cabang_vs_cabang_butuh_izin(): void
    {
        $u = $this->userDengan(['stok.view']);
        $this->actingAs($u)->get(route('laporan.absensi'))->assertForbidden();
        $this->actingAs($u)->get(route('laporan.cabang-vs-cabang'))->assertForbidden();

        $a = $this->userDengan(['laporan_absensi']);
        $this->actingAs($a)->get(route('laporan.absensi'))->assertOk();
    }

    public function test_cabang_vs_cabang_butuh_izin_dan_akses_semua_cabang(): void
    {
        CustomRole::where('kode', $this->role)->update(['akses_semua_cabang' => true]);
        $u = $this->userDengan(['laporan.cabang_vs_cabang.view']);
        $this->actingAs($u)->get(route('laporan.cabang-vs-cabang'))->assertOk();

        CustomRole::where('kode', $this->role)->update(['akses_semua_cabang' => false]);
        $this->actingAs($u->fresh())->get(route('laporan.cabang-vs-cabang'))->assertForbidden();
    }

    public function test_pewarisan_hanya_untuk_role_yang_dulu_melihat_menunya(): void
    {
        $stokId = Permission::where('name', 'laporan.stok.view')->value('id');
        DB::table('role_permissions')->where('permission_id', $stokId)->delete();

        $lama = $this->role;
        $tanpa = 'ujilap2' . rand(1000, 9999);
        CustomRole::create(['kode' => $tanpa, 'nama' => 'Uji Tanpa', 'akses_semua_cabang' => false]);
        foreach ([$lama => ['laporan.view', 'stok.view'], $tanpa => ['stok.view']] as $role => $izin) {
            foreach (Permission::whereIn('name', $izin)->pluck('id') as $id) {
                DB::table('role_permissions')->insert(['role' => $role, 'permission_id' => $id]);
            }
        }

        $hasil = HakAksesMenu::terapkan();
        $this->assertContains(['role' => $lama, 'permission_id' => $stokId], $hasil['centang']);
        $this->assertTrue(DB::table('role_permissions')->where(['role' => $lama, 'permission_id' => $stokId])->exists());
        $this->assertFalse(DB::table('role_permissions')->where(['role' => $tanpa, 'permission_id' => $stokId])->exists());

        // dijalankan lagi: tidak menambah apa-apa untuk role yang sudah punya
        $lagi = HakAksesMenu::terapkan();
        $this->assertNotContains(['role' => $lama, 'permission_id' => $stokId], $lagi['centang']);
        Cache::forget("role_permissions_{$tanpa}");
    }
}
