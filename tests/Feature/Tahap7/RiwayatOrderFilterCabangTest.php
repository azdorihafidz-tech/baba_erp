<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Order;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Riwayat Order ikut cabang yang dipilih di header (bug 2026-11-17: role akses
 * semua cabang selalu melihat semua cabang walau cabang di header sudah dipilih).
 */
class RiwayatOrderFilterCabangTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $a;
    private Cabang $b;
    private string $noA;
    private string $noB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Cabang::create(['nama_cabang' => 'Outlet Riwayat A', 'kode_cabang' => 'RA' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true]);
        $this->b = Cabang::create(['nama_cabang' => 'Outlet Riwayat B', 'kode_cabang' => 'RB' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true]);
        $this->noA = $this->order($this->a);
        $this->noB = $this->order($this->b);
    }

    private function order(Cabang $c): string
    {
        $no = 'RWY-' . uniqid();
        Order::create([
            'cabang_id' => $c->id, 'nomor_order' => $no, 'tanggal_order' => now()->toDateString(),
            'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway', 'total_harga' => 10000, 'diskon' => 0,
            'total_bayar' => 10000, 'jumlah_bayar' => 10000, 'kembalian' => 0, 'tipe_pembayaran' => 'tunai', 'status' => 'selesai',
        ]);

        return $no;
    }

    private function cari(): string
    {
        return route('penjualan.index', ['search' => 'RWY-']);
    }

    public function test_owner_memilih_cabang_di_header_hanya_melihat_cabang_itu(): void
    {
        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->withSession(['active_cabang_id' => $this->a->id])->get($this->cari())
            ->assertOk()->assertSee($this->noA)->assertDontSee($this->noB)->assertSee('Outlet Riwayat A');

        $this->actingAs($owner)->withSession(['active_cabang_id' => $this->b->id])->get($this->cari())
            ->assertOk()->assertSee($this->noB)->assertDontSee($this->noA);
    }

    public function test_semua_cabang_menampilkan_semua_dengan_kolom_cabang(): void
    {
        $res = $this->actingAs($this->buatUser('admin_pusat'))->withSession(['active_cabang_id' => null])->get($this->cari())
            ->assertOk()->assertSee($this->noA)->assertSee($this->noB)->assertSee('Semua Cabang');
        $this->assertStringContainsString('<th>Cabang</th>', $res->getContent());
    }

    public function test_role_cabang_tetap_cabangnya_sendiri_walau_sesi_menunjuk_cabang_lain(): void
    {
        $kasir = $this->buatUser('kasir', $this->a->id);
        $this->actingAs($kasir)->withSession(['active_cabang_id' => $this->b->id])->get($this->cari())
            ->assertOk()->assertSee($this->noA)->assertDontSee($this->noB);
    }
}
