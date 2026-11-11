<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\CustomRole;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * CLAUDE.md 4.43 Tahap 2 — menu yang dulu dikunci per role sekarang ikut
 * centangan Role & Hak Akses (termasuk role buatan), plus pengaman menu User.
 */
class HakAksesMenuRoleTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    /** @var array<int,string> */
    private array $roles = [];

    protected function tearDown(): void
    {
        foreach ($this->roles as $r) {
            Cache::forget("role_permissions_{$r}");
        }
        parent::tearDown();
    }

    /** Tiap panggilan = role buatan baru, jadi izin tidak saling menumpuk. */
    private function userDengan(array $izin, ?int $cabangId = null, bool $semuaCabang = false): User
    {
        $role = 'ujirol' . count($this->roles) . rand(1000, 9999);
        $this->roles[] = $role;
        CustomRole::create(['kode' => $role, 'nama' => 'Uji Role ' . $role, 'akses_semua_cabang' => $semuaCabang]);
        $ids = Permission::whereIn('name', $izin)->pluck('id');
        $this->assertCount(count($izin), $ids, 'izin tidak ditemukan: ' . implode(',', $izin));
        DB::table('role_permissions')->insert($ids->map(fn ($id) => ['role' => $role, 'permission_id' => $id])->all());
        Cache::forget("role_permissions_{$role}");

        return $this->buatUser($role, $cabangId ?? $this->cabangPertama()->id);
    }

    private function sidebar(User $u): string
    {
        return $this->actingAs($u)->get(route('panduan.index'))->assertOk()->getContent();
    }

    public function test_role_buatan_dengan_centangan_absensi_bisa_membuka_menunya(): void
    {
        // Kasus role "Team": menu tampil tapi dulu ditolak 403
        $u = $this->userDengan(['dashboard_absensi', 'registrasi_wajah', 'absensi.manage']);
        $this->actingAs($u)->get(route('dashboard.absensi'))->assertOk();
        $this->actingAs($u)->get(route('face-registration.index'))->assertOk();
        $this->actingAs($u)->get(route('absensi.index'))->assertOk();
        $this->actingAs($u)->get(route('absensi.rekap'))->assertOk();
        $this->actingAs($u)->get(route('face-attendance.today'))->assertOk();
    }

    public function test_lihat_absensi_saja_tidak_membuka_absensi_manual(): void
    {
        $u = $this->userDengan(['absensi.view']);
        $html = $this->sidebar($u);
        $this->assertStringNotContainsString('href="' . route('absensi.index') . '"', $html);
        $this->assertStringContainsString(route('absensi-saya.index'), $html);
        $this->actingAs($u)->get(route('absensi.index'))->assertForbidden();
        $this->actingAs($u)->get(route('dashboard.absensi'))->assertForbidden();
        $this->actingAs($u)->get(route('absen-device.index'))->assertForbidden();
    }

    public function test_shift_hari_libur_pengaturan_penggajian_ikut_centangan(): void
    {
        $tanpa = $this->userDengan(['karyawan.view']);
        foreach (['shift.index', 'hari-libur.index', 'pengaturan.penggajian'] as $r) {
            $this->actingAs($tanpa)->get(route($r))->assertForbidden();
        }

        $u = $this->userDengan(['kelola_shift', 'kelola_hari_libur', 'pengaturan_penggajian']);
        foreach (['shift.index', 'hari-libur.index', 'pengaturan.penggajian'] as $r) {
            $this->actingAs($u)->get(route($r))->assertOk();
        }
        $html = $this->sidebar($u);
        foreach (['shift.index', 'hari-libur.index', 'pengaturan.penggajian'] as $r) {
            $this->assertStringContainsString(route($r), $html, $r);
        }
    }

    public function test_supplier_lihat_dan_kelola_terpisah(): void
    {
        $po = $this->userDengan(['pembelian.view']);
        $this->assertStringNotContainsString(route('supplier.index'), $this->sidebar($po));
        $this->actingAs($po)->get(route('supplier.index'))->assertForbidden();

        $u = $this->userDengan(['supplier.view']);
        $this->assertStringContainsString(route('supplier.index'), $this->sidebar($u));
        $this->actingAs($u)->get(route('supplier.index'))->assertOk()->assertDontSee(route('supplier.create'), false);
        $this->actingAs($u)->get(route('supplier.create'))->assertForbidden();

        $m = $this->userDengan(['supplier.view', 'supplier.manage']);
        $this->actingAs($m)->get(route('supplier.index'))->assertOk()->assertSee(route('supplier.create'), false);
        $this->actingAs($m)->get(route('supplier.create'))->assertOk();
    }

    public function test_cabang_per_aksi(): void
    {
        $u = $this->userDengan(['cabang.view']);
        $this->actingAs($u)->get(route('cabang.index'))->assertOk()->assertDontSee(route('cabang.create'), false);
        $this->actingAs($u)->get(route('cabang.create'))->assertForbidden();
        $this->actingAs($u)->get(route('cabang.edit', $this->cabangPertama()))->assertForbidden();

        $e = $this->userDengan(['cabang.create', 'cabang.edit']);
        $this->actingAs($e)->get(route('cabang.create'))->assertOk();
        $this->actingAs($e)->get(route('cabang.edit', $this->cabangPertama()))->assertOk();
    }

    public function test_penggajian_lihat_dan_kelola_terpisah(): void
    {
        $u = $this->userDengan(['penggajian.view']);
        $this->actingAs($u)->get(route('penggajian.index'))->assertOk()->assertDontSee(route('penggajian.generate'), false);
        $this->actingAs($u)->get(route('penggajian.generate'))->assertForbidden();

        $m = $this->userDengan(['penggajian.manage']);
        $this->actingAs($m)->get(route('penggajian.generate'))->assertOk();
    }

    public function test_evaluasi_lihat_dan_kelola_terpisah(): void
    {
        $tanpa = $this->userDengan(['evaluasi.fill']);
        $this->actingAs($tanpa)->get(route('evaluasi.periods'))->assertForbidden();
        $this->actingAs($tanpa)->get(route('evaluasi.my-reviews'))->assertOk();

        $u = $this->userDengan(['evaluasi.view']);
        $this->actingAs($u)->get(route('evaluasi.periods'))->assertOk()->assertDontSee(route('evaluasi.period-create'), false);
        $this->actingAs($u)->get(route('evaluasi.period-create'))->assertForbidden();

        $m = $this->userDengan(['evaluasi.manage']);
        $this->actingAs($m)->get(route('evaluasi.period-create'))->assertOk();
    }

    public function test_jenis_menu_panduan_tooltip_ikut_centangan(): void
    {
        $tanpa = $this->userDengan(['karyawan.view']);
        foreach (['master.jenis-olahan.index', 'admin.panduan.index', 'admin.tooltips.index'] as $r) {
            $this->actingAs($tanpa)->get(route($r))->assertForbidden();
        }
        $u = $this->userDengan(['jenis-olahan.view', 'panduan.kelola']);
        $html = $this->sidebar($u);
        foreach (['master.jenis-olahan.index', 'admin.panduan.index', 'admin.tooltips.index'] as $r) {
            $this->actingAs($u)->get(route($r))->assertOk();
        }
        foreach (['admin.panduan.index', 'admin.tooltips.index'] as $r) {
            $this->assertStringContainsString(route($r), $html, $r);
        }
        // BABA: menu Jenis Menu (khusus dimsum) sengaja dihapus dari sidebar — diganti Resep Parfum (Tahap 1)
        $this->assertStringNotContainsString(route('master.jenis-olahan.index'), $html);
    }

    public function test_pengaturan_umum_dan_role_tetap_khusus_owner(): void
    {
        $u = $this->userDengan(['pengaturan.umum_lihat', 'pengaturan.umum_edit']);
        $this->actingAs($u)->get(route('pengaturan.umum'))->assertForbidden();
        $this->actingAs($u)->get(route('role.index'))->assertForbidden();
        $this->assertStringNotContainsString(route('pengaturan.umum'), $this->sidebar($u));
        $this->assertStringContainsString('khusus Owner', Permission::where('name', 'pengaturan.umum_lihat')->value('display_name'));

        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->get(route('pengaturan.umum'))->assertOk();
        $this->assertStringContainsString(route('pengaturan.umum'), $this->sidebar($owner));
    }

    public function test_tambah_kategori_boleh_untuk_pengelola_produk_jual(): void
    {
        $u = $this->userDengan(['master.produk_jual.create']);
        $kode = 'UK' . rand(1000, 9999);
        $this->actingAs($u)->post(route('item.kategori.store'), ['kode_kategori' => $kode, 'nama_kategori' => 'Uji Kategori'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('item_categories', ['kode_kategori' => $kode]);

        $tanpa = $this->userDengan(['stok.view']);
        $this->actingAs($tanpa)->post(route('item.kategori.store'), ['kode_kategori' => $kode . 'X', 'nama_kategori' => 'X'])->assertForbidden();
    }

    public function test_role_sistem_dengan_centangan_produk_jual_tidak_ditolak_form(): void
    {
        // Dulu ProdukJualRequest hanya mengizinkan 4 role sistem tertentu
        $kasir = $this->buatUser('kasir', $this->cabangPertama()->id);
        $perm = Permission::where('name', 'master.produk_jual.create')->value('id');
        DB::table('role_permissions')->insertOrIgnore(['role' => 'kasir', 'permission_id' => $perm]);
        Cache::forget('role_permissions_kasir');

        $this->actingAs($kasir)->withSession(['active_cabang_id' => $this->cabangPertama()->id])
            ->post(route('master.produk-jual.store'), [])
            ->assertSessionHasErrors('nama_item');
        Cache::forget('role_permissions_kasir');
    }

    public function test_tombol_transfer_stok_ikut_izin_bukan_nama_role(): void
    {
        $u = $this->userDengan(['stok.view', 'stok.transfer']);
        $this->actingAs($u)->get(route('stock-transfer.index'))->assertOk()->assertSee(route('stock-transfer.create'), false);

        $v = $this->userDengan(['stok.view']);
        $this->actingAs($v)->get(route('stock-transfer.index'))->assertOk()->assertDontSee(route('stock-transfer.create'), false);
    }

    public function test_user_non_owner_tidak_bisa_membuat_atau_mengubah_owner(): void
    {
        $u = $this->userDengan(['user.view', 'user.create', 'user.edit', 'user.delete'], null, true);

        $this->actingAs($u)->get(route('user.create'))->assertOk()->assertDontSee('value="owner"', false);
        $this->actingAs($u)->post(route('user.store'), [
            'name' => 'Calon Owner', 'email' => 'calon' . rand(1000, 9999) . '@uji.test', 'role' => 'owner',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('role');

        $owner = $this->buatUser('owner');
        $this->actingAs($u)->get(route('user.edit', $owner))->assertForbidden();
        $this->actingAs($u)->patch(route('user.toggle-aktif', $owner))->assertForbidden();
        $this->actingAs($u)->delete(route('user.destroy', $owner))->assertForbidden();
        $this->assertTrue($owner->fresh()->is_active);
    }

    public function test_user_role_satu_cabang_hanya_mengelola_cabangnya(): void
    {
        $a = $this->cabangPertama();
        $b = $this->cabangKedua();
        $u = $this->userDengan(['user.view', 'user.create', 'user.edit'], $a->id);
        $lain = $this->buatUser('kasir', $b->id);
        $sendiri = $this->buatUser('kasir', $a->id);

        $this->actingAs($u)->get(route('user.edit', $lain))->assertForbidden();
        $this->actingAs($u)->get(route('user.edit', $sendiri))->assertOk();
        $this->actingAs($u)->post(route('user.store'), [
            'name' => 'Kasir Baru', 'email' => 'kb' . rand(1000, 9999) . '@uji.test', 'role' => 'kasir',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123', 'cabang_ids' => [$b->id],
        ])->assertSessionHasErrors('cabang_ids.0');
    }

    public function test_pewarisan_tahap2_ke_role_yang_dulu_diizinkan(): void
    {
        $manage = Permission::where('name', 'supplier.view')->value('id');
        DB::table('role_permissions')->where('permission_id', $manage)->whereIn('role', ['admin_gudang', 'kasir'])->delete();
        $po = Permission::where('name', 'pembelian.view')->value('id');
        DB::table('role_permissions')->insertOrIgnore([['role' => 'admin_gudang', 'permission_id' => $po], ['role' => 'kasir', 'permission_id' => $po]]);

        \App\Support\HakAksesMenu::terapkan();

        // admin_gudang dulu boleh membuka Supplier (role) & melihat menunya (pembelian.view)
        $this->assertTrue(DB::table('role_permissions')->where(['role' => 'admin_gudang', 'permission_id' => $manage])->exists());
        // kasir melihat menu tapi dulu ditolak → tidak diwarisi
        $this->assertFalse(DB::table('role_permissions')->where(['role' => 'kasir', 'permission_id' => $manage])->exists());
        Cache::forget('role_permissions_kasir');
        Cache::forget('role_permissions_admin_gudang');
    }
}
