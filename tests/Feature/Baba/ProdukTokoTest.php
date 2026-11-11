<?php

namespace Tests\Feature\Baba;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Kas;
use App\Services\KetersediaanProdukService;
use App\Services\PenjualanService;
use App\Services\ProdukTokoService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** ERP BABA Tahap 3 — produk kasir parfum toko (botol & isi ulang per ml) + penjualan kasir memotong stok yang benar. */
class ProdukTokoTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $gudang;
    private Cabang $toko;
    private Item $botol;
    private Item $cairan;
    private ProdukTokoService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->buatUser('owner'));
        $this->gudang = Cabang::create(['nama_cabang' => 'Gudang PT ' . uniqid(), 'kode_cabang' => 'PT' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        $this->toko = Cabang::create(['nama_cabang' => 'Toko BABA Uji ' . uniqid(), 'kode_cabang' => 'TB' . rand(10000, 99999), 'tipe' => 'cabang', 'jenis_toko' => 'baba', 'is_active' => true,
            'dine_in_aktif' => false, 'takeaway_aktif' => true, 'frozen_aktif' => false, 'pilih_tipe_transaksi' => false, 'tipe_transaksi_default' => 'takeaway']);
        $pfm = ItemCategory::firstOrCreate(['kode_kategori' => 'PFM'], ['nama_kategori' => 'Parfum Botol']);
        $crn = ItemCategory::firstOrCreate(['kode_kategori' => 'CRN'], ['nama_kategori' => 'Cairan Parfum']);
        $k = strtoupper(Str::random(5));
        $this->botol = Item::create(['cabang_id' => $this->gudang->id, 'kode_item' => "PB{$k}", 'nama_item' => "Amber {$k} EDP 50 ml", 'item_category_id' => $pfm->id,
            'tipe' => 'bahan_baku', 'jenis' => 'bahan_baku', 'satuan' => 'pcs', 'track_stok' => true, 'is_active' => true]);
        $this->cairan = Item::create(['cabang_id' => $this->gudang->id, 'kode_item' => "PC{$k}", 'nama_item' => "Cairan Amber {$k} EDP", 'item_category_id' => $crn->id,
            'tipe' => 'bahan_baku', 'jenis' => 'bahan_baku', 'satuan' => 'ml', 'track_stok' => true, 'is_active' => true]);
        $this->svc = app(ProdukTokoService::class);
    }

    private function pilih(array $botol, array $cairan): array
    {
        return [$this->botol->id => $botol, $this->cairan->id => $cairan];
    }

    public function test_buat_produk_kasir_dan_jual_memotong_stok_yang_benar(): void
    {
        $r = $this->svc->rencana($this->toko);
        $this->assertSame(1, collect($r['botol'])->where('sumber.id', $this->botol->id)->count());
        $this->assertSame("Isi Ulang Amber " . substr($this->cairan->kode_item, 2) . " EDP (per ml)", collect($r['cairan'])->firstWhere('sumber.id', $this->cairan->id)['nama']);

        $h = $this->svc->simpan($this->toko, $this->pilih(['aktif' => '1', 'harga' => 150000], ['aktif' => '1', 'harga' => 2500]), auth()->id());
        $this->assertSame(['dibuat' => 2, 'diubah' => 0, 'dimatikan' => 0], $h);
        $jual = Item::where('cabang_id', $this->toko->id)->where('kode_item', 'JL-' . $this->botol->kode_item)->firstOrFail();
        $refill = Item::where('cabang_id', $this->toko->id)->where('kode_item', 'RF-' . $this->cairan->kode_item)->firstOrFail();
        $this->assertSame(['produk_jual', 'pcs', 150000.0], [$jual->tipe, $jual->satuan, (float) $jual->harga_jual]);
        $this->assertSame(['ml', 2500.0], [$refill->satuan, (float) $refill->harga_jual]);
        // bahan padanan di toko (kode sama dengan gudang) — stok masuk lewat transfer
        $bBotol = Item::where('cabang_id', $this->toko->id)->where('kode_item', $this->botol->kode_item)->firstOrFail();
        $bCairan = Item::where('cabang_id', $this->toko->id)->where('kode_item', $this->cairan->kode_item)->firstOrFail();
        $stok = app(StokService::class);
        $stok->masuk($bBotol->id, $this->toko->id, 10, 'uji transfer', 'uji', null, 30000);
        $stok->masuk($bCairan->id, $this->toko->id, 500, 'uji transfer', 'uji', null, 400);

        $sedia = app(KetersediaanProdukService::class)->untuk(collect([$jual, $refill]), $this->toko->id);
        $this->assertEquals(10, $sedia[$jual->id]['jumlah']);
        $this->assertEquals(500, $sedia[$refill->id]['jumlah']); // 500 ml, bukan 500.000

        $kas = app(\App\Services\KasBawaanService::class)->pastikan($this->toko)['tunai'];
        $total = 2 * 150000 + 30 * 2500;
        $order = app(PenjualanService::class)->buatOrder([
            'tipe_transaksi' => 'takeaway', 'payments' => [['metode' => 'tunai', 'jumlah' => $total]], 'jumlah_bayar' => $total, 'kas_id' => $kas->id,
            'items' => [
                ['tipe' => 'produk_jadi', 'item_id' => $jual->id, 'nama_item' => $jual->nama_item, 'qty' => 2, 'harga_satuan' => 150000, 'satuan' => 'pcs'],
                ['tipe' => 'produk_jadi', 'item_id' => $refill->id, 'nama_item' => $refill->nama_item, 'qty' => 30, 'harga_satuan' => 2500, 'satuan' => 'ml'],
            ],
        ], $this->toko->id);
        $this->assertEquals($total, (float) $order->total_bayar);
        $this->assertEquals(8, $stok->getStok($bBotol->id, $this->toko->id));
        $this->assertEquals(470, $stok->getStok($bCairan->id, $this->toko->id)); // 30 ml terpotong tepat
        $this->assertEquals(500, $stok->getStok($this->cairan->id, $this->gudang->id) + 500); // stok gudang tidak tersentuh

        // stok kurang → kasir menolak
        $this->expectException(\Exception::class);
        app(PenjualanService::class)->buatOrder([
            'tipe_transaksi' => 'takeaway', 'payments' => [['metode' => 'tunai', 'jumlah' => 2500 * 471]], 'jumlah_bayar' => 2500 * 471, 'kas_id' => $kas->id,
            'items' => [['tipe' => 'produk_jadi', 'item_id' => $refill->id, 'nama_item' => $refill->nama_item, 'qty' => 471, 'harga_satuan' => 2500, 'satuan' => 'ml']],
        ], $this->toko->id);
    }

    public function test_ubah_harga_matikan_dan_validasi(): void
    {
        $this->svc->simpan($this->toko, $this->pilih(['aktif' => '1', 'harga' => 150000], ['aktif' => '1', 'harga' => 2500]), auth()->id());
        $h = $this->svc->simpan($this->toko, $this->pilih(['aktif' => '1', 'harga' => 160000], ['aktif' => '0']), auth()->id());
        $this->assertSame(['dibuat' => 0, 'diubah' => 1, 'dimatikan' => 1], $h);
        $this->assertEquals(160000, (float) Item::where('cabang_id', $this->toko->id)->where('kode_item', 'JL-' . $this->botol->kode_item)->value('harga_jual'));
        $this->assertFalse((bool) Item::where('cabang_id', $this->toko->id)->where('kode_item', 'RF-' . $this->cairan->kode_item)->value('is_active'));
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->simpan($this->toko, $this->pilih(['aktif' => '1', 'harga' => ''], []), auth()->id());
    }

    public function test_kasir_karyawan_toko_melihat_produk_parfum_di_pos(): void
    {
        $this->svc->simpan($this->toko, $this->pilih(['aktif' => '1', 'harga' => 150000], ['aktif' => '1', 'harga' => 2500]), auth()->id());
        $kasir = $this->buatUser('kasir', $this->toko->id);
        $this->actingAs($kasir)->withSession(['cabang_aktif_id' => $this->toko->id, 'active_cabang_id' => $this->toko->id])
            ->get(route('penjualan.pos'))->assertOk()
            ->assertSee($this->botol->nama_item)->assertSee('Isi Ulang Amber');
    }

    public function test_halaman_dan_izin(): void
    {
        $this->get(route('produk-toko.index', ['toko' => $this->toko->id]))->assertOk()->assertSee('Produk Parfum Toko')->assertSee($this->botol->nama_item);
        $this->post(route('produk-toko.simpan'), ['toko' => $this->toko->id, 'produk' => $this->pilih(['aktif' => '1', 'harga' => ''], [])])->assertSessionHas('error');
        $this->post(route('produk-toko.simpan'), ['toko' => $this->toko->id, 'produk' => $this->pilih(['aktif' => '1', 'harga' => 155000], ['aktif' => '1', 'harga' => 2600])])->assertSessionHas('success');
        $this->get(route('produk-toko.index', ['toko' => $this->toko->id]))->assertSee('JL-' . $this->botol->kode_item)->assertSee('2600');
        $this->actingAs($this->buatUser('kasir'))->get(route('produk-toko.index'))->assertForbidden();
        // cabang baru berjenis toko: kasir tanpa pilihan dine-in/frozen
        $this->actingAs($this->buatUser('owner'))->post(route('cabang.store'), ['nama_cabang' => 'Toko Kemitraan Uji', 'kode_cabang' => 'KM' . rand(1000, 9999), 'tipe' => 'cabang', 'jenis_toko' => 'kemitraan']);
        $c = Cabang::where('nama_cabang', 'Toko Kemitraan Uji')->first();
        $this->assertNotNull($c);
        $this->assertSame(['kemitraan', false, false], [$c->jenis_toko, (bool) $c->dine_in_aktif, (bool) $c->pilih_tipe_transaksi]);
        $this->assertSame(['qris', 'transfer', 'tunai'], Kas::where('cabang_id', $c->id)->pluck('default_untuk')->map(fn ($v) => is_object($v) ? $v->value : $v)->sort()->values()->all());
    }
}
