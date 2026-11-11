<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Ganti cabang dari header (CLAUDE.md 4.39): halaman edit/detail/tambah
 * kembali ke daftar menu itu; halaman daftar/laporan tetap tapi filter dibuang.
 */
class GantiCabangKembaliKeDaftarTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function ganti(string $dari, ?Cabang $ke = null)
    {
        $ke ??= Cabang::where('tipe', 'cabang')->orderBy('id')->firstOrFail();

        return $this->actingAs($this->buatUser('owner'))
            ->from($dari)
            ->post(route('cabang.switch'), ['cabang_id' => $ke->id]);
    }

    public function test_halaman_satu_data_kembali_ke_daftar_menu_semua_menu(): void
    {
        $kasus = [
            '/master/bahan-baku/999999/edit'      => route('master.bahan-baku.index'),
            '/master/bahan-baku/create'           => route('master.bahan-baku.index'),
            '/master/produk-jual/999999/edit'     => route('master.produk-jual.index'),
            '/master/produk-jual/create'          => route('master.produk-jual.index'),
            '/master/resep-bumbu/999999/edit'     => route('master.resep-bumbu.index'),
            '/item/999999'                        => route('item.index'),
            '/pembelian/999999'                   => route('pembelian.index'),
            '/pembelian/create'                   => route('pembelian.index'),
            '/penjualan/999999'                   => route('penjualan.index'),
            '/penjualan/999999/edit'              => route('penjualan.index'),
            '/penjualan/999999/struk'             => route('penjualan.index'),
            '/stock-transfer/999999'              => route('stock-transfer.index'),
            '/stock-transfer/create'              => route('stock-transfer.index'),
            '/stock-request/999999'               => route('stock-request.index'),
            '/pemakaian-perlengkapan/999999'      => route('pemakaian-perlengkapan.index'),
            '/setoran-kasir/999999'               => route('setoran-kasir.index'),
            '/keuangan/999999/detail'             => route('keuangan.index'),
            '/keuangan/closing/999999'            => route('closing.index'),
            '/karyawan/999999/edit'               => route('karyawan.index'),
            '/aset/999999'                        => route('aset.index'),
            '/evaluasi/999999/detail'             => url('/evaluasi'),
        ];

        foreach ($kasus as $dari => $harap) {
            $this->ganti($dari)->assertRedirect($harap)->assertSessionHas('success', fn ($m) => str_contains($m, 'Ditampilkan data milik cabang ini'));
        }
    }

    public function test_halaman_daftar_tetap_tapi_filter_dibuang(): void
    {
        $this->ganti('/stok/kartu?item_id=5&lokasi_id=1')->assertRedirect(url('/stok/kartu'));
        $this->ganti('/master/bahan-baku?search=sirup&page=2')->assertRedirect(url('/master/bahan-baku'));
        $this->ganti('/penjualan/pos')->assertRedirect(url('/penjualan/pos'));
        $this->ganti('/laporan/stok?lokasi_id=3')->assertRedirect(url('/laporan/stok'));
        $this->ganti('/profile')->assertRedirect(url('/profile'));
    }

    public function test_referer_tidak_dikenal_ke_dashboard_dan_cabang_tetap_diganti(): void
    {
        $cabang = Cabang::where('tipe', 'cabang')->orderBy('id')->firstOrFail();
        $this->ganti('/halaman-yang-tidak-ada/123', $cabang)->assertRedirect(route('dashboard'));
        $this->assertSame($cabang->id, (int) session('active_cabang_id'));
    }

    public function test_akses_cabang_tetap_dijaga(): void
    {
        $milik = Cabang::create(['nama_cabang' => 'GC Milik', 'kode_cabang' => 'GCM' . rand(100, 999), 'tipe' => 'cabang', 'is_active' => true]);
        $bukan = Cabang::create(['nama_cabang' => 'GC Bukan', 'kode_cabang' => 'GCB' . rand(100, 999), 'tipe' => 'cabang', 'is_active' => true]);
        $manajer = $this->buatUser('manajer_cabang', $milik->id);

        $this->actingAs($manajer)->from('/master/bahan-baku')->post(route('cabang.switch'), ['cabang_id' => $bukan->id])->assertForbidden();
    }

    public function test_peringatan_saat_membuka_barang_milik_cabang_lain(): void
    {
        $a = Cabang::create(['nama_cabang' => 'GC Outlet A', 'kode_cabang' => 'GCA' . rand(100, 999), 'tipe' => 'cabang', 'is_active' => true]);
        $b = Cabang::create(['nama_cabang' => 'GC Outlet B', 'kode_cabang' => 'GCX' . rand(100, 999), 'tipe' => 'cabang', 'is_active' => true]);
        $sirup = Item::create(['cabang_id' => $a->id, 'kode_item' => 'GC-' . uniqid(), 'nama_item' => 'Sirup GC', 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'is_active' => true]);
        $owner = $this->buatUser('owner');

        $this->actingAs($owner)->withSession(['active_cabang_id' => $b->id])->get(route('master.bahan-baku.edit', $sirup))->assertOk()
            ->assertSee('Data ini milik')->assertSee('GC Outlet A')->assertSee('bukan cabang yang sedang dipilih');

        $this->actingAs($owner)->withSession(['active_cabang_id' => $a->id])->get(route('master.bahan-baku.edit', $sirup))->assertOk()
            ->assertDontSee('Data ini milik');
    }
}
