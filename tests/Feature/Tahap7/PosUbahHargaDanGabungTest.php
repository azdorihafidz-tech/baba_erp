<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Stock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * 4.47 — Harga/Unit di POS terkunci kecuali punya izin "Ubah Harga Jual di
 * POS" (server mengabaikan harga dari form), dan klik produk yang sama
 * menambah qty baris yang sudah ada.
 */
class PosUbahHargaDanGabungTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $c;
    private Item $produk;

    protected function setUp(): void
    {
        parent::setUp();
        (new \Database\Seeders\PosUbahHargaSeeder())->run();
        $this->lupakanCache();
        $this->c = Cabang::create(['nama_cabang' => 'Outlet Harga', 'kode_cabang' => 'UH' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true,
            'dine_in_aktif' => true, 'takeaway_aktif' => true, 'frozen_aktif' => true, 'wajib_bukti_transfer' => false, 'wajib_bukti_qris' => false]);
        foreach (['tunai', 'transfer', 'qris'] as $jenis) {
            Kas::create(['cabang_id' => $this->c->id, 'default_untuk' => $jenis, 'nama_kas' => "Kas {$jenis} UH", 'tipe_kas' => $jenis === 'tunai' ? 'tunai' : 'bank', 'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true]);
        }
        $kategori = \App\Models\ItemCategory::where('kode_kategori', '!=', 'TMB')->value('id');
        $this->produk = Item::create(['cabang_id' => $this->c->id, 'item_category_id' => $kategori, 'kode_item' => 'UH-' . uniqid(), 'nama_item' => 'Dimsum UH',
            'tipe' => 'produk_jual', 'satuan' => 'porsi', 'harga_jual' => 15000, 'is_active' => true]);
        Stock::create(['item_id' => $this->produk->id, 'lokasi_id' => $this->c->id, 'qty' => 50, 'qty_minimum' => 0]);
    }

    protected function tearDown(): void
    {
        $this->lupakanCache();
        parent::tearDown();
    }

    private function lupakanCache(): void
    {
        Cache::forget('all_permission_names');
        foreach (['kasir', 'manajer_cabang', 'admin_pusat', 'owner'] as $r) {
            Cache::forget("role_permissions_{$r}");
        }
    }

    private function payload(float $harga): array
    {
        return [
            'nama_pelanggan' => 'Walk-in', 'tipe_transaksi' => 'takeaway',
            'items' => [['item_id' => $this->produk->id, 'qty' => 2, 'harga_satuan' => $harga, 'satuan' => 'porsi', 'nama_item' => 'Dimsum UH']],
            'payments' => [['metode' => 'tunai', 'jumlah' => 100000]],
            'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => 100000,
        ];
    }

    private function pesan(string $role, float $harga): Order
    {
        $user = $this->buatUser($role, $role === 'owner' ? null : $this->c->id);
        $this->actingAs($user)->withSession(['active_cabang_id' => $this->c->id])
            ->post(route('penjualan.store'), $this->payload($harga))->assertSessionHasNoErrors()->assertRedirect();

        return Order::where('cabang_id', $this->c->id)->latest('id')->first();
    }

    public function test_izin_dibuat_tanpa_centangan_role(): void
    {
        $perm = Permission::where('name', 'order.ubah_harga')->first();
        $this->assertNotNull($perm);
        $this->assertSame('penjualan', $perm->group);
        $this->assertSame(0, DB::table('role_permissions')->where('permission_id', $perm->id)->count());
    }

    public function test_kasir_tanpa_izin_harga_dari_form_diabaikan(): void
    {
        $order = $this->pesan('kasir', 1);
        $item = $order->items()->first();
        $this->assertSame(15000.0, (float) $item->harga_satuan);
        $this->assertSame(30000.0, (float) $order->total_bayar);
    }

    public function test_owner_dan_role_berizin_boleh_ubah_harga(): void
    {
        $this->assertSame(12000.0, (float) $this->pesan('owner', 12000)->items()->first()->harga_satuan);

        DB::table('role_permissions')->insert(['role' => 'kasir', 'permission_id' => Permission::where('name', 'order.ubah_harga')->value('id')]);
        $this->lupakanCache();
        $order = $this->pesan('kasir', 13000);
        $this->assertSame(13000.0, (float) $order->items()->first()->harga_satuan);
        $this->assertSame(26000.0, (float) $order->total_bayar);
    }

    public function test_pos_kolom_harga_terkunci_untuk_kasir(): void
    {
        $this->actingAs($this->buatUser('kasir', $this->c->id))->withSession(['active_cabang_id' => $this->c->id])
            ->get(route('penjualan.pos'))->assertOk()
            ->assertSee('window.POS_BOLEH_UBAH_HARGA = false', false)
            ->assertSee('function gabungKeBarisSama', false)
            ->assertSee('tambahkanItemKeForm(item, null, false)', false)
            ->assertSee("<strong>Harga/Unit</strong>", false);

        $this->actingAs($this->buatUser('owner'))->withSession(['active_cabang_id' => $this->c->id])
            ->get(route('penjualan.pos'))->assertOk()->assertSee('window.POS_BOLEH_UBAH_HARGA = true', false);
    }

    public function test_panduan_pos_diperbarui_sekali(): void
    {
        (new \Database\Seeders\PosUbahHargaSeeder())->run();
        $konten = (string) \App\Models\Panduan::getBySlug('pos')?->konten;
        $this->assertSame(1, substr_count($konten, '<!-- POS-447 -->'));
        $this->assertStringContainsString('Ubah Harga Jual di POS', $konten);
    }
}
