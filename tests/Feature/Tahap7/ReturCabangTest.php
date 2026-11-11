<?php

namespace Tests\Feature\Tahap7;

use App\Enums\StatusOrder;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use App\Models\Order;
use App\Models\ResepBumbu;
use App\Models\ResepBumbuItem;
use App\Models\ResepProduksi;
use App\Models\ReturInternal;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\TransaksiKeuangan;
use App\Services\ClosingService;
use App\Services\LaporanTransaksiKasirService;
use App\Services\PenjualanService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * 4.49 Tahap B3 — Retur Cabang → Gudang (porting coffeeshop 40bd55e) + uji alur
 * lengkap: Produksi → Kirim → Terima → cabang menjual → Retur → buang.
 * Data barang per cabang (4.39): yang keluar dari cabang = barang milik cabang,
 * yang masuk ke Gudang = barang milik Gudang.
 */
class ReturCabangTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $gudang;
    private Cabang $outlet;
    private Item $isian;
    private Item $produkKirim;
    private Kas $kasGudang;
    private Kas $kasOutlet;
    private Kas $kasOutletTransfer;

    protected function setUp(): void
    {
        parent::setUp();
        $u = substr(uniqid(), -6);
        $this->gudang = Cabang::create(['nama_cabang' => "Gudang Retur {$u}", 'kode_cabang' => 'GR' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true, 'kirim_cabang_aktif' => true]);
        $this->outlet = Cabang::create(['nama_cabang' => "Outlet Retur {$u}", 'kode_cabang' => 'OR' . rand(10000, 99999), 'tipe' => 'cabang', 'is_active' => true,
            'wajib_bukti_transfer' => false, 'wajib_bukti_qris' => false]);
        $kas = fn ($c, $jenis, $saldo) => Kas::create(['cabang_id' => $c->id, 'default_untuk' => $jenis, 'nama_kas' => "Kas {$jenis} {$c->nama_cabang}",
            'tipe_kas' => $jenis === 'tunai' ? 'tunai' : 'bank', 'saldo_awal' => $saldo, 'saldo_sekarang' => $saldo, 'is_active' => true]);
        $this->kasGudang = $kas($this->gudang, 'tunai', 100000);
        $this->kasOutlet = $kas($this->outlet, 'tunai', 500000);
        $this->kasOutletTransfer = $kas($this->outlet, 'transfer', 200000);

        $this->isian = Item::create(['cabang_id' => $this->gudang->id, 'kode_item' => 'ISR-' . $u, 'nama_item' => 'Isian Retur Uji', 'tipe' => 'bahan_baku',
            'satuan' => 'kg', 'harga_beli_terakhir' => 15000, 'is_active' => true, 'track_stok' => true]);
        $this->produkKirim = $this->produkDenganKomposisi($this->gudang, 'Isian (kirim) Retur', 20000, $this->isian, 1, 'kg');

        $this->actingAs($this->buatUser('owner'));
        app(StokService::class)->masuk($this->isian->id, $this->gudang->id, 10, 'uji', 'uji', null, 15000);
    }

    private function produkDenganKomposisi(Cabang $c, string $nama, float $harga, Item $bahan, float $qty, string $satuan): Item
    {
        $p = Item::create(['cabang_id' => $c->id, 'kode_item' => 'PJ-' . substr(md5($nama . uniqid()), 0, 8), 'nama_item' => $nama, 'tipe' => 'produk_jual',
            'satuan' => 'porsi', 'harga_jual' => $harga, 'is_active' => true, 'track_stok' => false]);
        $r = ResepBumbu::create(['cabang_id' => $c->id, 'nama' => $nama, 'kode' => 'R-' . substr(md5($nama . uniqid()), 0, 12), 'item_id' => $p->id, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $r->id, 'item_id' => $bahan->id, 'qty_per_unit' => $qty, 'satuan' => $satuan, 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 0]);

        return $p;
    }

    private function stok(int $itemId, Cabang $c): float
    {
        return (float) Stock::where('item_id', $itemId)->where('lokasi_id', $c->id)->value('qty');
    }

    private function nilaiFifo(int $itemId, Cabang $c): float
    {
        return StockBatch::where('item_id', $itemId)->where('lokasi_id', $c->id)->get()->sum(fn ($b) => $b->qty_sisa * $b->harga_beli_per_unit);
    }

    /** Kirim $qty kg lalu cabang menerima (bayar dari $kas, bawaan tunai). */
    private function kirimDanTerima(float $qty, ?Kas $kas = null): Order
    {
        $this->actingAs($this->buatUser('kasir', $this->gudang->id))->withSession(['active_cabang_id' => $this->gudang->id])
            ->postJson(route('penjualan.store'), [
                'customer_type' => 'internal', 'outlet_tujuan_id' => $this->outlet->id, 'tipe_transaksi' => 'takeaway',
                'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => 1, 'payments' => [['metode' => 'tunai', 'jumlah' => 1]],
                'items' => [['item_id' => $this->produkKirim->id, 'nama_item' => $this->produkKirim->nama_item, 'qty' => $qty, 'satuan' => 'porsi', 'harga_satuan' => 20000]],
            ])->assertOk();
        $o = Order::where('cabang_id', $this->gudang->id)->where('customer_type', 'internal')->whereNull('retur_internal_id')->latest('id')->firstOrFail();
        $this->actingAs($this->buatUser('kasir', $this->outlet->id))->post(route('kiriman.terima', $o), ['kas_id' => $kas?->id])->assertSessionHas('success');

        return $o->fresh('kirimanItems');
    }

    private function padanan(): Item
    {
        return Item::where('cabang_id', $this->outlet->id)->where('kode_item', $this->isian->kode_item)->firstOrFail();
    }

    private function buatRetur(Order $kiriman, float $qty, bool $uangKembali): ReturInternal
    {
        $r = $kiriman->kirimanItems->first();
        $this->actingAs($this->buatUser('kasir', $this->outlet->id))->withSession(['active_cabang_id' => $this->outlet->id])
            ->post(route('retur-cabang.store', $kiriman), ['qty' => [$r->id => $qty], 'alasan' => 'basi', 'uang_kembali' => $uangKembali ? 1 : 0, 'catatan' => 'uji'])
            ->assertSessionHas('success');

        return ReturInternal::latest('id')->firstOrFail();
    }

    public function test_retur_uang_kembali_ke_kas_pembayar_dan_order_koreksi_minus(): void
    {
        $kiriman = $this->kirimDanTerima(4, $this->kasOutletTransfer);   // bayar 80.000 dari kas transfer
        $padanan = $this->padanan();
        $this->assertEquals(120000, (float) $this->kasOutletTransfer->fresh()->saldo_sekarang);

        $retur = $this->buatRetur($kiriman, 1.5, true);
        $this->assertSame('menunggu', $retur->status);
        $this->assertSame($padanan->id, (int) $retur->items->first()->item_id);           // barang milik cabang yang keluar
        $this->assertEqualsWithDelta(2.5, $this->stok($padanan->id, $this->outlet), 0.0001);
        $this->assertEquals(30000, (float) $retur->nilai);

        $this->actingAs($this->buatUser('admin_gudang', $this->gudang->id))->withSession(['active_cabang_id' => $this->gudang->id])
            ->get(route('retur-cabang.show', $retur))->assertOk();
        $this->post(route('retur-cabang.terima', $retur))->assertSessionHas('success');
        $retur->refresh();
        $this->assertSame('diterima', $retur->status);

        // Stok kembali ke barang Gudang dengan modal asli
        $this->assertEqualsWithDelta(7.5, $this->stok($this->isian->id, $this->gudang), 0.0001);
        $this->assertEqualsWithDelta(7.5 * 15000, $this->nilaiFifo($this->isian->id, $this->gudang), 0.5);
        // Uang: kas Gudang −30.000, kas cabang yang dulu membayar (transfer) +30.000
        $this->assertEquals(150000, (float) $this->kasGudang->fresh()->saldo_sekarang);   // 100.000 + 80.000 − 30.000
        $this->assertEquals(150000, (float) $this->kasOutletTransfer->fresh()->saldo_sekarang);
        $this->assertEquals(500000, (float) $this->kasOutlet->fresh()->saldo_sekarang);

        // Order koreksi minus di Gudang: pendapatan & HPP turun
        $koreksi = $retur->orderKoreksi;
        $this->assertNotNull($koreksi);
        $this->assertEquals(-30000, (float) $koreksi->total_bayar);
        $this->assertEqualsWithDelta(-22500, (float) $koreksi->items->sum('hpp'), 0.5);
        $this->assertSame('internal', $koreksi->customer_type);
        $this->assertEquals(-30000, (float) TransaksiKeuangan::where('referensi_type', 'order')->where('referensi_id', $koreksi->id)->sum('jumlah'));
        $g = app(ClosingService::class)->hitung(today(), today(), $this->gudang->id);
        $this->assertEquals(50000, $g['laba_rugi']['omzet']);                                // 80.000 − 30.000
        $this->assertEquals(37500, $g['laba_rugi']['hpp']);                                  // 60.000 − 22.500
        $this->assertEquals(50000, (float) app(LaporanTransaksiKasirService::class)->base($this->gudang->id)->whereDate('orders.tanggal_order', today())->sum('orders.total_bayar'));

        // Koreksi tidak bisa dibatalkan/diedit
        $owner = $this->buatUser('owner');
        $this->expectExceptionMessage('koreksi otomatis');
        app(PenjualanService::class)->batalkan($koreksi);
    }

    public function test_retur_tanpa_uang_kembali_stok_masuk_rp0_tanpa_uang(): void
    {
        $kiriman = $this->kirimDanTerima(2);
        $retur = $this->buatRetur($kiriman, 1, false);
        $this->actingAs($this->buatUser('admin_pusat'))->post(route('retur-cabang.terima', $retur))->assertSessionHas('success');

        $this->assertEqualsWithDelta(9.0, $this->stok($this->isian->id, $this->gudang), 0.0001);
        $this->assertEqualsWithDelta(8 * 15000, $this->nilaiFifo($this->isian->id, $this->gudang), 0.5);  // 1 kg masuk bernilai Rp0
        $this->assertEquals(460000, (float) $this->kasOutlet->fresh()->saldo_sekarang);   // tidak ada uang kembali
        $this->assertNull($retur->fresh()->orderKoreksi);
    }

    public function test_tolak_batal_dan_batas_jumlah(): void
    {
        $kiriman = $this->kirimDanTerima(2);
        $r = $kiriman->kirimanItems->first();
        $padanan = $this->padanan();

        // Melebihi yang diterima
        $this->actingAs($this->buatUser('kasir', $this->outlet->id))
            ->post(route('retur-cabang.store', $kiriman), ['qty' => [$r->id => 3], 'alasan' => 'rusak', 'uang_kembali' => 1])->assertSessionHas('error');

        $retur = $this->buatRetur($kiriman, 1, true);
        $this->actingAs($this->buatUser('admin_gudang', $this->gudang->id))->post(route('retur-cabang.tolak', $retur), ['alasan' => 'tidak sesuai'])->assertSessionHas('success');
        $this->assertSame('ditolak', $retur->fresh()->status);
        $this->assertEqualsWithDelta(2.0, $this->stok($padanan->id, $this->outlet), 0.0001);
        $this->assertEquals(0, (float) $r->fresh()->qty_retur);

        $retur2 = $this->buatRetur($kiriman->fresh('kirimanItems'), 2, true);
        $this->actingAs($this->buatUser('kasir', $this->outlet->id))->post(route('retur-cabang.batalkan', $retur2), ['alasan' => 'salah'])->assertSessionHas('success');
        $this->assertSame('dibatalkan', $retur2->fresh()->status);
        $this->assertEqualsWithDelta(2.0, $this->stok($padanan->id, $this->outlet), 0.0001);

        // Hak akses: kasir tidak bisa terima retur, cabang lain tidak bisa buat retur
        $retur3 = $this->buatRetur($kiriman->fresh('kirimanItems'), 1, true);
        $this->actingAs($this->buatUser('kasir', $this->outlet->id))->post(route('retur-cabang.terima', $retur3))->assertForbidden();
        $lain = Cabang::where('tipe', 'cabang')->where('is_active', true)->where('id', '!=', $this->outlet->id)->firstOrFail();
        $this->actingAs($this->buatUser('kasir', $lain->id))->get(route('retur-cabang.create', $kiriman))->assertForbidden();
        // Retur dari kiriman yang belum diterima tidak bisa
        $this->actingAs($this->buatUser('kasir', $this->gudang->id))->withSession(['active_cabang_id' => $this->gudang->id])
            ->postJson(route('penjualan.store'), [
                'customer_type' => 'internal', 'outlet_tujuan_id' => $this->outlet->id, 'tipe_transaksi' => 'takeaway',
                'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => 1, 'payments' => [['metode' => 'tunai', 'jumlah' => 1]],
                'items' => [['item_id' => $this->produkKirim->id, 'nama_item' => 'x', 'qty' => 1, 'harga_satuan' => 20000]],
            ])->assertOk();
        $menunggu = Order::where('cabang_id', $this->gudang->id)->where('status_kirim', 'menunggu')->latest('id')->firstOrFail();
        $this->actingAs($this->buatUser('kasir', $this->outlet->id))->get(route('retur-cabang.create', $menunggu))->assertRedirect(route('kiriman.show', $menunggu));
    }

    /** Alur Owner: produksi isian → kirim → cabang terima → cabang jual dimsum → retur basi → buang. */
    public function test_alur_lengkap_produksi_kirim_jual_retur(): void
    {
        $owner = $this->buatUser('owner');
        // 1. Produksi: 5 kg Isian Ayam dari daging (stok kg, takaran g)
        $daging = Item::create(['cabang_id' => $this->gudang->id, 'kode_item' => 'DAG-' . uniqid(), 'nama_item' => 'Daging Uji', 'tipe' => 'bahan_baku',
            'satuan' => 'kg', 'harga_beli_terakhir' => 40000, 'is_active' => true, 'track_stok' => true]);
        $isianProd = Item::create(['cabang_id' => $this->gudang->id, 'kode_item' => 'IAP-' . uniqid(), 'nama_item' => 'Isian Ayam Produksi', 'tipe' => 'bahan_baku',
            'satuan' => 'kg', 'is_active' => true, 'track_stok' => true]);
        $this->actingAs($owner);
        app(StokService::class)->masuk($daging->id, $this->gudang->id, 5, 'uji', 'uji', null, 40000);
        $this->post(route('produksi.resep.store'), ['cabang_id' => $this->gudang->id, 'item_id' => $isianProd->id, 'qty_hasil' => 1, 'is_active' => 1,
            'bahan' => [['item_id' => $daging->id, 'qty' => 800, 'satuan' => 'g']]])->assertSessionHas('success');
        $resep = ResepProduksi::where('item_id', $isianProd->id)->firstOrFail();
        $this->post(route('produksi.store'), ['cabang_id' => $this->gudang->id, 'resep_produksi_id' => $resep->id, 'tanggal' => today()->toDateString(),
            'qty_rencana' => 5, 'qty_hasil' => 5])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(5.0, $this->stok($isianProd->id, $this->gudang), 0.0001);   // modal 4 kg × 40.000 / 5 = 32.000/kg

        // 2. Kirim 3 kg @40.000 → cabang terima
        $this->produkKirim = $this->produkDenganKomposisi($this->gudang, 'Isian Ayam (kirim)', 40000, $isianProd, 1, 'kg');
        $this->actingAs($this->buatUser('kasir', $this->gudang->id))->withSession(['active_cabang_id' => $this->gudang->id])
            ->postJson(route('penjualan.store'), ['customer_type' => 'internal', 'outlet_tujuan_id' => $this->outlet->id, 'tipe_transaksi' => 'takeaway',
                'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => 1, 'payments' => [['metode' => 'tunai', 'jumlah' => 1]],
                'items' => [['item_id' => $this->produkKirim->id, 'nama_item' => 'Isian Ayam (kirim)', 'qty' => 3, 'harga_satuan' => 40000]]])->assertOk();
        $kiriman = Order::where('cabang_id', $this->gudang->id)->where('status_kirim', 'menunggu')->latest('id')->firstOrFail();
        $this->actingAs($this->buatUser('kasir', $this->outlet->id))->post(route('kiriman.terima', $kiriman))->assertSessionHas('success');
        $isianCabang = Item::where('cabang_id', $this->outlet->id)->where('kode_item', $isianProd->kode_item)->firstOrFail();
        $this->assertEqualsWithDelta(3.0, $this->stok($isianCabang->id, $this->outlet), 0.0001);

        // 3. Cabang jual 10 porsi Dimsum Ayam (100 g isian/porsi) @15.000 → HPP 10 × 0,1 × 40.000
        $dimsum = $this->produkDenganKomposisi($this->outlet, 'Dimsum Ayam Uji', 15000, $isianCabang, 100, 'g');
        $kasir = $this->buatUser('kasir', $this->outlet->id);
        $this->actingAs($kasir);
        $order = app(PenjualanService::class)->buatOrder(['kasir_id' => $kasir->id, 'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway',
            'items' => [['item_id' => $dimsum->id, 'qty' => 10, 'harga_satuan' => 15000, 'satuan' => 'porsi', 'nama_item' => 'Dimsum Ayam Uji']],
            'payments' => [['metode' => 'tunai', 'jumlah' => 150000]], 'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => 150000], $this->outlet->id);
        $this->assertEqualsWithDelta(40000, (float) $order->items->sum('hpp'), 0.5);
        $this->assertEqualsWithDelta(2.0, $this->stok($isianCabang->id, $this->outlet), 0.0001);

        // 4. Retur 0,5 kg basi, uang kembali → Gudang terima → buang lewat Penyesuaian Stok
        $retur = $this->buatRetur($kiriman->fresh('kirimanItems'), 0.5, true);
        $this->actingAs($owner)->post(route('retur-cabang.terima', $retur))->assertSessionHas('success');
        $this->assertEqualsWithDelta(2.5, $this->stok($isianProd->id, $this->gudang), 0.0001);   // 5 − 3 + 0,5
        app(StokService::class)->keluar($isianProd->id, $this->gudang->id, 0.5, 'buang basi', 'adjustment');
        $this->assertEqualsWithDelta(2.0, $this->stok($isianProd->id, $this->gudang), 0.0001);

        // 5. Laporan: Gudang 120.000 − 20.000 = 100.000; cabang 150.000; Semua Cabang tanpa kiriman/retur
        $svc = app(LaporanTransaksiKasirService::class);
        $hariIni = fn ($q) => $q->whereDate('orders.tanggal_order', today());
        $this->assertEquals(100000, (float) $hariIni($svc->base($this->gudang->id))->sum('orders.total_bayar'));
        $this->assertEquals(150000, (float) $hariIni($svc->base($this->outlet->id))->sum('orders.total_bayar'));
        $this->assertSame(0, $hariIni($svc->base(null))->whereIn('orders.cabang_id', [$this->gudang->id])->count());
        $all = app(ClosingService::class)->hitung(today(), today(), null);
        $this->assertEquals($all['penjualan']['internal']['omzet'], $all['laba_rugi']['eliminasi_omzet']);
        $this->assertSame(StatusOrder::Selesai, $kiriman->fresh()->status);
    }
}
