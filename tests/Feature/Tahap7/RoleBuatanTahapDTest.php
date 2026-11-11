<?php

namespace Tests\Feature\Tahap7;

use App\Enums\RoleUser;
use App\Models\Cabang;
use App\Models\CustomRole;
use App\Models\Permission;
use App\Models\User;
use App\Support\CustomRoleValue;
use App\Support\Roles;
use Database\Seeders\Perbaikan438PanduanSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap D (CLAUDE.md 4.38, 2026-10-18) — role bebas buatan Owner (mis. Kru).
 */
class RoleBuatanTahapDTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function owner(): User
    {
        return $this->buatUser('owner');
    }

    private function buatRole(string $nama, bool $semua = false): CustomRole
    {
        $this->actingAs($this->owner())->post(route('role.custom.store'), ['nama' => $nama, 'akses_semua_cabang' => $semua ? 1 : 0])
            ->assertRedirect(route('role.index'));

        return CustomRole::where('nama', $nama)->latest('id')->firstOrFail();
    }

    private function beriHak(string $role, array $permNames): void
    {
        $ids = Permission::whereIn('name', $permNames)->pluck('id')->all();
        $this->actingAs($this->owner())->post(route('role.update-permissions', $role), ['permission_ids' => $ids])->assertRedirect();
        Cache::forget("role_permissions_{$role}");
    }

    public function test_owner_tambah_role_dan_kode_tidak_bentrok(): void
    {
        $kru = $this->buatRole('Kru');
        $this->assertSame('kru', $kru->kode);
        $this->assertSame('kru_2', $this->buatRole('Kru')->kode);
        $this->assertSame('kasir_2', $this->buatRole('Kasir')->kode, 'tidak boleh menimpa role sistem');
    }

    public function test_hanya_owner_yang_boleh_kelola_role(): void
    {
        $this->actingAs($this->buatUser('admin_pusat'))->post(route('role.custom.store'), ['nama' => 'Kru X'])->assertForbidden();
        $this->assertFalse(CustomRole::where('nama', 'Kru X')->exists());
    }

    public function test_user_role_buatan_dibaca_sbg_role_dengan_label_dan_akses_cabang(): void
    {
        $kru = $this->buatRole('Kru Dapur');
        $semua = $this->buatRole('Supervisor Area', true);
        $u1 = User::factory()->create(['role' => $kru->kode, 'is_active' => true]);
        $u2 = User::factory()->create(['role' => $semua->kode, 'is_active' => true]);

        $this->assertInstanceOf(CustomRoleValue::class, $u1->fresh()->role);
        $this->assertSame('Kru Dapur', $u1->fresh()->role->label());
        $this->assertSame($kru->kode, $u1->fresh()->role->value);
        $this->assertFalse($u1->fresh()->canAccessAllBranches());
        $this->assertTrue($u2->fresh()->canAccessAllBranches());
        $this->assertNotSame(RoleUser::Owner, $u1->fresh()->role);

        // Role sistem tetap enum
        $this->assertSame(RoleUser::Kasir, User::factory()->create(['role' => 'kasir'])->fresh()->role);
        // Kode tak dikenal → null (tidak crash)
        DB::table('users')->where('id', $u1->id)->update(['role' => 'role_hilang']);
        $this->assertNull($u1->fresh()->role);
        $this->assertFalse($u1->fresh()->hasPermission('order.view'));
    }

    public function test_hak_akses_role_buatan_berlaku_di_menu(): void
    {
        $kru = $this->buatRole('Kru');
        $this->beriHak($kru->kode, ['order.view', 'cabang.view']);
        $cab = Cabang::where('tipe', 'cabang')->orderBy('id')->firstOrFail();
        $user = User::factory()->create(['role' => $kru->kode, 'is_active' => true]);
        $user->cabangs()->attach($cab->id, ['is_default' => true]);

        $this->assertTrue($user->fresh()->hasPermission('order.view'));
        $this->assertFalse($user->fresh()->hasPermission('closing.view'));

        $this->actingAs($user->fresh())->withSession(['active_cabang_id' => $cab->id])->get(route('penjualan.index'))->assertOk();
        $this->actingAs($user->fresh())->get(route('closing.index'))->assertForbidden();
        // 4.43: menu manajemen sekarang juga mengikuti centangan (dulu khusus role sistem)
        $this->actingAs($user->fresh())->get(route('cabang.index'))->assertOk();
        $this->actingAs($user->fresh())->get(route('cabang.create'))->assertForbidden();
    }

    public function test_role_buatan_dengan_hak_bahan_baku_boleh_simpan_bahan(): void
    {
        $kru = $this->buatRole('Kru Gudang');
        $this->beriHak($kru->kode, ['master.bahan_baku.view', 'master.bahan_baku.create']);
        $user = User::factory()->create(['role' => $kru->kode, 'is_active' => true]);
        // 4.39 — barang baru milik cabang user (role cabang wajib terhubung ke cabang)
        $user->cabangs()->attach(Cabang::where('tipe', 'cabang')->orderBy('id')->value('id'), ['is_default' => true]);

        $this->actingAs($user->fresh())->post(route('master.bahan-baku.store'), [
            'kode_item' => 'KRU-' . uniqid(), 'nama_item' => 'Bahan Kru', 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'is_active' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('items', ['nama_item' => 'Bahan Kru']);
    }

    public function test_buat_user_dengan_role_buatan_dan_pilihan_role_tampil(): void
    {
        $kru = $this->buatRole('Kru');
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('user.create'))->assertOk()->assertSee('value="' . $kru->kode . '"', false)->assertSee('Kru');
        $this->actingAs($owner)->post(route('user.store'), [
            'name' => 'Budi Kru', 'email' => 'budi.kru.' . uniqid() . '@test.local', 'role' => $kru->kode,
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123', 'is_active' => 1,
        ])->assertRedirect();
        $this->assertSame($kru->kode, DB::table('users')->where('name', 'Budi Kru')->value('role'));

        $this->actingAs($owner)->post(route('user.store'), [
            'name' => 'Salah Role', 'email' => 'salah.' . uniqid() . '@test.local', 'role' => 'role_ngawur',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('role');
    }

    public function test_hapus_role_ditolak_kalau_dipakai_dan_boleh_kalau_tidak(): void
    {
        $kru = $this->buatRole('Kru');
        $this->beriHak($kru->kode, ['order.view']);
        $u = User::factory()->create(['role' => $kru->kode]);

        $this->actingAs($this->owner())->delete(route('role.custom.destroy', $kru))->assertRedirect()->assertSessionHas('error');
        $this->assertTrue(CustomRole::whereKey($kru->id)->exists());

        $u->update(['role' => 'kasir']);
        $this->actingAs($this->owner())->delete(route('role.custom.destroy', $kru))->assertRedirect()->assertSessionHas('success');
        $this->assertFalse(CustomRole::whereKey($kru->id)->exists());
        $this->assertSame(0, DB::table('role_permissions')->where('role', $kru->kode)->count());
    }

    public function test_halaman_role_menampilkan_role_buatan_dan_panduan(): void
    {
        $this->seed(Perbaikan438PanduanSeeder::class);
        $kru = $this->buatRole('Kru');

        $this->actingAs($this->owner())->get(route('role.index'))->assertOk()
            ->assertSee('Tambah Role')->assertSee('Role Buatan')->assertSee('tab-' . $kru->kode, false)
            ->assertSee('panduanModal-role-hak-akses', false);

        $this->assertContains($kru->kode, Roles::values());
        $this->assertNotContains('owner', Roles::values(false));
    }

    public function test_halaman_lama_yang_memakai_role_tetap_normal_untuk_user_role_buatan(): void
    {
        $kru = $this->buatRole('Kru');
        $u = User::factory()->create(['role' => $kru->kode, 'is_active' => true]);
        $owner = $this->owner();

        foreach ([route('user.index'), route('user.show', $u), route('user.edit', $u)] as $url) {
            $this->actingAs($owner)->get($url)->assertOk()->assertSee('Kru');
        }
        $this->actingAs($u->fresh())->get(route('profile.edit'))->assertOk()->assertSee('Kru');
    }
}
