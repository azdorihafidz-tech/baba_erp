<?php

namespace Tests\Feature\Tahap7;

use App\Models\CustomRole;
use App\Models\Order;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * CLAUDE.md 4.43 Tahap 3 — POS butuh "Buat Order", Riwayat Penjualan butuh
 * "Lihat Order"; detail order & struk butuh izin terkait + cabang sendiri.
 */
class HakAksesPosTest extends TestCase
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

    private function userDengan(array $izin, int $cabangId): User
    {
        $role = 'ujipos' . count($this->roles) . rand(1000, 9999);
        $this->roles[] = $role;
        CustomRole::create(['kode' => $role, 'nama' => 'Uji POS ' . $role, 'akses_semua_cabang' => false]);
        $ids = Permission::whereIn('name', $izin)->pluck('id');
        DB::table('role_permissions')->insert($ids->map(fn ($id) => ['role' => $role, 'permission_id' => $id])->all());

        return $this->buatUser($role, $cabangId);
    }

    private function order(int $cabangId): Order
    {
        $kasir = $this->buatUser('kasir', $cabangId);

        return Order::create([
            'nomor_order' => 'UJI-POS-' . uniqid(), 'cabang_id' => $cabangId, 'kasir_id' => $kasir->id,
            'tanggal_order' => now(), 'status' => 'pending', 'tipe_order' => 'penjualan',
            'total_harga' => 0, 'total_bayar' => 0,
        ]);
    }

    public function test_tanpa_izin_order_pos_dan_riwayat_ditolak(): void
    {
        $a = $this->cabangPertama()->id;
        $u = $this->userDengan(['karyawan.view'], $a);
        $this->actingAs($u)->withSession(['active_cabang_id' => $a])->get(route('penjualan.pos'))->assertForbidden();
        $this->actingAs($u)->get(route('penjualan.index'))->assertForbidden();
        $this->actingAs($u)->post(route('penjualan.store'), [])->assertForbidden();
        $this->actingAs($u)->post(route('penjualan.simpan-bill'), [])->assertForbidden();
    }

    public function test_buat_order_membuka_pos_lihat_order_membuka_riwayat(): void
    {
        $a = $this->cabangPertama()->id;
        $kasir = $this->userDengan(['order.create'], $a);
        $this->actingAs($kasir)->withSession(['active_cabang_id' => $a])->get(route('penjualan.pos'))->assertOk();
        $this->actingAs($kasir)->get(route('penjualan.index'))->assertForbidden();

        $lihat = $this->userDengan(['order.view'], $a);
        $this->actingAs($lihat)->get(route('penjualan.index'))->assertOk();
        $this->actingAs($lihat)->withSession(['active_cabang_id' => $a])->get(route('penjualan.pos'))->assertForbidden();
    }

    public function test_detail_order_dan_struk_butuh_izin_dan_cabang_sendiri(): void
    {
        $a = $this->cabangPertama()->id;
        $b = $this->cabangKedua()->id;
        $orderA = $this->order($a);
        $orderB = $this->order($b);

        $tanpa = $this->userDengan(['karyawan.view'], $a);
        $this->actingAs($tanpa)->get(route('penjualan.show', $orderA))->assertForbidden();
        $this->actingAs($tanpa)->get(route('penjualan.struk', $orderA))->assertForbidden();

        $antrian = $this->userDengan(['antrian.lihat'], $a);
        $this->actingAs($antrian)->get(route('penjualan.struk', $orderA))->assertOk();
        $this->actingAs($antrian)->get(route('penjualan.show', $orderA))->assertOk();
        $this->actingAs($antrian)->get(route('penjualan.show', $orderB))->assertForbidden();
        $this->actingAs($antrian)->get(route('penjualan.struk-modal', $orderB))->assertForbidden();

        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->get(route('penjualan.show', $orderB))->assertOk();
    }

    public function test_kasir_bawaan_tetap_bisa_pos(): void
    {
        $a = $this->cabangPertama()->id;
        $kasir = $this->buatUser('kasir', $a);
        $this->assertTrue($kasir->can('order.create'), 'role kasir di data test harus punya order.create');
        $this->actingAs($kasir)->withSession(['active_cabang_id' => $a])->get(route('penjualan.pos'))->assertOk();
    }
}
