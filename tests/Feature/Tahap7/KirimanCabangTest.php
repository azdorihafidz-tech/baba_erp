<?php

namespace Tests\Feature\Tahap7;

use App\Enums\StatusOrder;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\ResepBumbu;
use App\Models\ResepBumbuItem;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\TransaksiKeuangan;
use App\Services\ClosingArsipService;
use App\Services\ClosingService;
use App\Services\LaporanTransaksiKasirService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * 4.49 Tahap B2 — Kirim ke Cabang (porting coffeeshop 40bd55e). Gudang Pusat
 * menjual "Isian Ayam (kirim)" (komposisi 1 kg Isian Ayam milik gudang) ke
 * cabang. Barang masuk ke barang MILIK cabang (padanan Kode Item, 4.39).
 */
class KirimanCabangTest extends TestCase
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
        $this->gudang = Cabang::create(['nama_cabang' => "Gudang Uji {$u}", 'kode_cabang' => 'GK' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true, 'kirim_cabang_aktif' => true, 'antrian_produksi_aktif' => true]);
        $this->outlet = Cabang::create(['nama_cabang' => "Outlet Uji {$u}", 'kode_cabang' => 'OK' . rand(10000, 99999), 'tipe' => 'cabang', 'is_active' => true]);
        $kas = fn ($c, $jenis, $saldo) => Kas::create(['cabang_id' => $c->id, 'default_untuk' => $jenis, 'nama_kas' => "Kas {$jenis} {$c->nama_cabang}",
            'tipe_kas' => $jenis === 'tunai' ? 'tunai' : 'bank', 'saldo_awal' => $saldo, 'saldo_sekarang' => $saldo, 'is_active' => true]);
        $this->kasGudang = $kas($this->gudang, 'tunai', 100000);
        $this->kasOutlet = $kas($this->outlet, 'tunai', 500000);
        $this->kasOutletTransfer = $kas($this->outlet, 'transfer', 200000);

        $this->isian = Item::create(['cabang_id' => $this->gudang->id, 'kode_item' => 'ISI-' . $u, 'nama_item' => 'Isian Ayam Uji', 'tipe' => 'bahan_baku',
            'satuan' => 'kg', 'harga_beli_terakhir' => 15000, 'is_active' => true, 'track_stok' => true]);
        $this->produkKirim = Item::create(['cabang_id' => $this->gudang->id, 'kode_item' => 'KRM-' . $u, 'nama_item' => 'Isian Ayam (kirim) Uji', 'tipe' => 'produk_jual',
            'satuan' => 'kg', 'harga_jual' => 20000, 'is_active' => true, 'track_stok' => false]);
        $r = ResepBumbu::create(['cabang_id' => $this->gudang->id, 'nama' => 'Isian kirim', 'kode' => 'R-' . $u, 'item_id' => $this->produkKirim->id, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $r->id, 'item_id' => $this->isian->id, 'qty_per_unit' => 1, 'satuan' => 'kg', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 0]);

        $this->actingAs($this->buatUser('owner'));
        app(StokService::class)->masuk($this->isian->id, $this->gudang->id, 10, 'uji', 'uji', null, 15000);
    }

    private function stok(int $itemId, Cabang $c): float
    {
        return (float) Stock::where('item_id', $itemId)->where('lokasi_id', $c->id)->value('qty');
    }

    private function kirim(float $qty = 2, array $ubah = [], ?\App\Models\User $user = null)
    {
        $user ??= $this->buatUser('kasir', $this->gudang->id);

        return $this->actingAs($user)->withSession(['active_cabang_id' => $this->gudang->id])
            ->postJson(route('penjualan.store'), array_merge([
                'customer_type' => 'internal', 'outlet_tujuan_id' => $this->outlet->id,
                'tipe_transaksi' => 'takeaway', 'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => 20000 * $qty,
                'payments' => [['metode' => 'tunai', 'jumlah' => 20000 * $qty]],
                'items' => [['item_id' => $this->produkKirim->id, 'nama_item' => $this->produkKirim->nama_item, 'qty' => $qty, 'satuan' => 'kg', 'harga_satuan' => 20000]],
            ], $ubah));
    }

    private function kirimanTerakhir(): Order
    {
        return Order::where('cabang_id', $this->gudang->id)->where('customer_type', 'internal')->latest('id')->firstOrFail();
    }

    public function test_kirim_memotong_stok_gudang_tanpa_uang_dan_tidak_masuk_laporan(): void
    {
        $this->kirim(2)->assertOk()->assertJson(['success' => true])->assertJsonPath('message', fn ($m) => str_contains($m, 'menunggu diterima'));
        $o = $this->kirimanTerakhir();

        $this->assertSame(StatusOrder::Proses, $o->status);
        $this->assertSame('menunggu', $o->status_kirim);
        $this->assertSame($this->outlet->id, (int) $o->outlet_tujuan_id);
        $this->assertNull($o->nomor_antrian);                 // kiriman tidak masuk antrian
        $this->assertEquals(0, (float) $o->service_charge);
        $this->assertEqualsWithDelta(8.0, $this->stok($this->isian->id, $this->gudang), 0.0001);
        $this->assertCount(1, $o->kirimanItems);
        $this->assertEqualsWithDelta(30000, (float) $o->kirimanItems->first()->hpp_kirim, 0.01);   // 2 kg × modal 15.000
        $this->assertSame(0, OrderPayment::where('order_id', $o->id)->count());
        $this->assertSame(0, TransaksiKeuangan::where('referensi_id', $o->id)->whereIn('referensi_type', ['order', 'order_internal'])->count());
        $this->assertEquals(100000, (float) $this->kasGudang->fresh()->saldo_sekarang);
        // Belum diterima → tidak dihitung laporan mana pun
        $this->assertFalse(Order::penjualanSah()->whereKey($o->id)->exists());
    }

    public function test_terima_semua_stok_masuk_barang_milik_cabang_kas_pindah_dan_laporan(): void
    {
        $this->kirim(2);
        $o = $this->kirimanTerakhir();
        $kasir = $this->buatUser('kasir', $this->outlet->id);

        $this->actingAs($kasir)->withSession(['active_cabang_id' => $this->outlet->id])
            ->get(route('kiriman.show', $o))->assertOk()->assertSee('Barang baru akan dibuat di cabang ini')->assertSee($this->isian->kode_item);
        $this->post(route('kiriman.terima', $o), [])->assertRedirect(route('kiriman.show', $o))->assertSessionHas('success');

        $o->refresh();
        $this->assertSame(StatusOrder::Selesai, $o->status);
        $this->assertSame('diterima', $o->status_kirim);
        $this->assertSame(today()->toDateString(), $o->tanggal_order->toDateString());

        // Padanan dibuat otomatis: kode sama, milik cabang tujuan
        $padanan = Item::where('cabang_id', $this->outlet->id)->where('kode_item', $this->isian->kode_item)->firstOrFail();
        $this->assertSame($padanan->id, (int) $o->kirimanItems->first()->item_tujuan_id);
        $this->assertEqualsWithDelta(2.0, $this->stok($padanan->id, $this->outlet), 0.0001);
        $this->assertEqualsWithDelta(0.0, $this->stok($this->isian->id, $this->outlet), 0.0001); // bukan barang gudang
        $batch = StockBatch::where('item_id', $padanan->id)->where('lokasi_id', $this->outlet->id)->firstOrFail();
        $this->assertEqualsWithDelta(20000, (float) $batch->harga_beli_per_unit, 0.01);

        // Uang: kas tunai cabang −40.000 (Pembelian Bahan), kas tunai gudang +40.000 (Penjualan)
        $this->assertEquals(460000, (float) $this->kasOutlet->fresh()->saldo_sekarang);
        $this->assertEquals(140000, (float) $this->kasGudang->fresh()->saldo_sekarang);
        $trx = TransaksiKeuangan::where('referensi_type', 'order_internal')->where('referensi_id', $o->id)->firstOrFail();
        $this->assertSame('pembelian_bahan', $trx->kategori->value);
        $this->assertSame($this->outlet->id, (int) $trx->cabang_id);

        // Laporan: Gudang menghitung 40.000 (untung 10.000); Semua Cabang tidak (eliminasi)
        $svc = app(LaporanTransaksiKasirService::class);
        $this->assertEquals(40000, (float) $svc->base($this->gudang->id)->whereKey($o->id)->sum('orders.total_bayar'));
        $this->assertFalse($svc->base(null)->whereKey($o->id)->exists());
        $info = LaporanTransaksiKasirService::internalTidakDihitung(null, today(), today());
        $this->assertNotNull($info);
        $this->assertGreaterThanOrEqual(40000, $info['pendapatan']);
        $this->assertNull(LaporanTransaksiKasirService::internalTidakDihitung($this->gudang->id, today(), today()));

        // Tutup Buku: gudang omzet 40.000; Keseluruhan mengeliminasi kiriman ini
        $closing = app(ClosingService::class);
        $g = $closing->hitung(today(), today(), $this->gudang->id);
        $this->assertEquals(40000, $g['laba_rugi']['omzet']);
        $this->assertEquals(0, $g['laba_rugi']['eliminasi_omzet']);
        $all = $closing->hitung(today(), today(), null);
        $this->assertGreaterThanOrEqual(40000, $all['laba_rugi']['eliminasi_omzet']);
        $this->assertEquals($all['penjualan']['internal']['hpp'], $all['laba_rugi']['eliminasi_hpp']);
        $this->assertEquals($all['penjualan']['omzet_bersih'] - $all['laba_rugi']['eliminasi_omzet'], $all['laba_rugi']['omzet']);

        // Sudah diterima → tidak bisa dibatalkan / diedit
        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->post(route('kiriman.batalkan', $o), ['alasan' => 'coba'])->assertSessionHas('error');
        $this->get(route('penjualan.edit', $o))->assertRedirect(route('penjualan.show', $o));
    }

    public function test_terima_sebagian_dengan_kas_transfer_dan_padanan_yang_sudah_ada(): void
    {
        $ada = Item::create(['cabang_id' => $this->outlet->id, 'kode_item' => $this->isian->kode_item, 'nama_item' => 'Isian Ayam (outlet)', 'tipe' => 'bahan_baku',
            'satuan' => 'kg', 'is_active' => true, 'track_stok' => true]);
        $this->kirim(2);
        $o = $this->kirimanTerakhir();
        $oi = $o->items->first();

        $this->actingAs($this->buatUser('manajer_cabang', $this->outlet->id))->withSession(['active_cabang_id' => $this->outlet->id]);
        $this->get(route('kiriman.show', $o))->assertOk()->assertDontSee('Barang baru akan dibuat di cabang ini');
        $this->post(route('kiriman.terima', $o), ['qty_diterima' => [$oi->id => 1.5], 'kas_id' => $this->kasOutletTransfer->id])->assertSessionHas('success');

        $o->refresh();
        $this->assertEqualsWithDelta(1.5, $this->stok($ada->id, $this->outlet), 0.0001);   // masuk ke barang yang sudah ada
        $this->assertEqualsWithDelta(8.5, $this->stok($this->isian->id, $this->gudang), 0.0001); // 0,5 kembali
        $this->assertEquals(30000, (float) $o->total_bayar);
        $this->assertEqualsWithDelta(1.5, (float) $o->items->first()->qty, 0.0001);
        $this->assertEquals(170000, (float) $this->kasOutletTransfer->fresh()->saldo_sekarang);
        $this->assertEquals(500000, (float) $this->kasOutlet->fresh()->saldo_sekarang);
        $this->assertEquals(130000, (float) $this->kasGudang->fresh()->saldo_sekarang);

        // Kas cabang lain tidak boleh dipakai
        $this->kirim(1);
        $o2 = $this->kirimanTerakhir();
        $this->actingAs($this->buatUser('owner'))->post(route('kiriman.terima', $o2), ['kas_id' => $this->kasGudang->id])->assertSessionHas('error');
        $this->assertSame('menunggu', $o2->fresh()->status_kirim);
    }

    public function test_tolak_dan_batalkan_mengembalikan_stok_tanpa_uang(): void
    {
        $this->kirim(2);
        $o = $this->kirimanTerakhir();
        $this->actingAs($this->buatUser('kasir', $this->outlet->id))->post(route('kiriman.tolak', $o), ['alasan' => 'salah kirim'])->assertSessionHas('success');
        $o->refresh();
        $this->assertSame(StatusOrder::Dibatalkan, $o->status);
        $this->assertSame('ditolak', $o->status_kirim);
        $this->assertEqualsWithDelta(10.0, $this->stok($this->isian->id, $this->gudang), 0.0001);
        $nilai = StockBatch::where('item_id', $this->isian->id)->where('lokasi_id', $this->gudang->id)->get()->sum(fn ($b) => $b->qty_sisa * $b->harga_beli_per_unit);
        $this->assertEqualsWithDelta(150000, $nilai, 0.5);   // modal semula
        $this->assertEquals(500000, (float) $this->kasOutlet->fresh()->saldo_sekarang);

        $this->kirim(3);
        $o2 = $this->kirimanTerakhir();
        $this->actingAs($this->buatUser('admin_pusat'))->post(route('kiriman.batalkan', $o2), ['alasan' => 'batal produksi'])->assertSessionHas('success');
        $this->assertSame('dibatalkan', $o2->fresh()->status_kirim);
        $this->assertEqualsWithDelta(10.0, $this->stok($this->isian->id, $this->gudang), 0.0001);
        // Kiriman batal tidak bisa "dikembalikan" jadi penjualan
        $this->actingAs($this->buatUser('owner'))->post(route('penjualan.kembalikan', $o2))->assertSessionHas('error');
    }

    public function test_aturan_kirim_dan_hak_akses(): void
    {
        // Save Bill untuk kiriman ditolak
        $this->actingAs($this->buatUser('kasir', $this->gudang->id))->withSession(['active_cabang_id' => $this->gudang->id])
            ->postJson(route('penjualan.simpan-bill'), ['customer_type' => 'internal', 'outlet_tujuan_id' => $this->outlet->id, 'tipe_transaksi' => 'takeaway',
                'items' => [['item_id' => $this->produkKirim->id, 'nama_item' => 'x', 'qty' => 1, 'harga_satuan' => 20000]]])->assertStatus(422);
        // Tujuan = gudang sendiri ditolak
        $this->kirim(1, ['outlet_tujuan_id' => $this->gudang->id])->assertStatus(422);
        // Dari POS cabang (bukan gudang) ditolak
        $this->actingAs($this->buatUser('owner'))->withSession(['active_cabang_id' => $this->outlet->id])
            ->postJson(route('penjualan.store'), ['customer_type' => 'internal', 'outlet_tujuan_id' => $this->gudang->id, 'tipe_transaksi' => 'takeaway',
                'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => 1, 'payments' => [['metode' => 'tunai', 'jumlah' => 1]],
                'items' => [['item_id' => $this->produkKirim->id, 'nama_item' => 'x', 'qty' => 1, 'harga_satuan' => 20000]]])->assertStatus(422);
        // Produk tanpa komposisi (Produk Jual Dimsum tidak menyimpan stok sendiri, 4.34) ditolak
        $tanpa = Item::create(['cabang_id' => $this->gudang->id, 'kode_item' => 'TNP-' . uniqid(), 'nama_item' => 'Tanpa Komposisi', 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => 1000, 'is_active' => true, 'track_stok' => false]);
        $this->kirim(1, ['items' => [['item_id' => $tanpa->id, 'nama_item' => 'Tanpa Komposisi', 'qty' => 1, 'harga_satuan' => 1000]]])->assertStatus(422);
        $this->assertSame(0, Order::where('cabang_id', $this->gudang->id)->where('customer_type', 'internal')->count());

        $this->kirim(1)->assertOk();
        $o = $this->kirimanTerakhir();
        // Kasir cabang lain / gudang tidak bisa Terima
        $lain = Cabang::where('tipe', 'cabang')->where('is_active', true)->where('id', '!=', $this->outlet->id)->firstOrFail();
        $this->actingAs($this->buatUser('kasir', $lain->id))->post(route('kiriman.terima', $o))->assertForbidden();
        $this->actingAs($this->buatUser('admin_gudang', $this->gudang->id))->post(route('kiriman.terima', $o))->assertForbidden();
        // Helper tidak punya izin Kiriman
        $this->actingAs($this->buatUser('helper', $this->outlet->id))->get(route('kiriman.index'))->assertForbidden();
        // Kasir tujuan melihat badge & daftar
        $this->actingAs($this->buatUser('kasir', $this->outlet->id))->withSession(['active_cabang_id' => $this->outlet->id])
            ->get(route('kiriman.index'))->assertOk()->assertSee($o->nomor_order)->assertSee('Cek &amp; Terima', false);
    }

    public function test_pos_gudang_punya_pilihan_kirim_pos_cabang_tidak(): void
    {
        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->withSession(['active_cabang_id' => $this->gudang->id])->get(route('penjualan.pos'))->assertOk()
            ->assertSee('id="selectCustomerType"', false)->assertSee($this->outlet->nama_cabang)->assertSee('function kirimKeCabang', false);
        $this->actingAs($owner)->withSession(['active_cabang_id' => $this->outlet->id])->get(route('penjualan.pos'))->assertOk()
            ->assertDontSee('id="selectCustomerType"', false);
    }

    /** Permintaan Owner: saklar per Gudang di Cabang → Edit, bawaan MATI. */
    public function test_saklar_kirim_ke_cabang_bawaan_mati(): void
    {
        $baru = Cabang::create(['nama_cabang' => 'Gudang Baru Uji', 'kode_cabang' => 'GB' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        $this->assertFalse($baru->fresh()->kirim_cabang_aktif);
        $this->assertTrue(Cabang::where('tipe', 'gudang_pusat')->whereKeyNot($this->gudang->id)->get()->every(fn ($c) => ! $c->kirim_cabang_aktif));

        // Saklar mati → POS gudang tanpa pilihan Kirim, server menolak kiriman
        $this->gudang->update(['kirim_cabang_aktif' => false]);
        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->withSession(['active_cabang_id' => $this->gudang->id])->get(route('penjualan.pos'))->assertOk()
            ->assertDontSee('id="selectCustomerType"', false);
        $this->kirim(1)->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'belum dinyalakan'));
        $this->assertSame(0, Order::where('cabang_id', $this->gudang->id)->where('customer_type', 'internal')->count());

        // Saklar hanya tampil di form Gudang Pusat, dan bisa dinyalakan
        $this->actingAs($owner)->get(route('cabang.edit', $this->outlet))->assertOk()->assertDontSee('name="kirim_cabang_aktif"', false);
        $this->actingAs($owner)->get(route('cabang.edit', $this->gudang))->assertOk()->assertSee('name="kirim_cabang_aktif"', false);
        $this->actingAs($owner)->put(route('cabang.update', $this->gudang), [
            'nama_cabang' => $this->gudang->nama_cabang, 'kode_cabang' => $this->gudang->kode_cabang,
            'tipe' => 'gudang_pusat', 'is_active' => 1, 'kirim_cabang_aktif' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertTrue($this->gudang->fresh()->kirim_cabang_aktif);
        $this->kirim(1)->assertOk();
    }

    public function test_terima_tetap_bisa_walau_tanggal_kirim_sudah_closing(): void
    {
        $this->kirim(2);
        $o = $this->kirimanTerakhir();
        // Kiriman dibuat kemarin, lalu gudang closing harian kemarin
        Order::whereKey($o->id)->update(['tanggal_order' => today()->subDay()]);
        app(ClosingArsipService::class)->simpanHarian($this->gudang->id, today()->subDay(), $this->buatUser('owner')->id, [$this->kasGudang->id => 100000]);

        $this->actingAs($this->buatUser('kasir', $this->outlet->id))->post(route('kiriman.terima', $o))->assertSessionHas('success');
        $this->assertSame('diterima', $o->fresh()->status_kirim);
        $this->assertSame(today()->toDateString(), $o->fresh()->tanggal_order->toDateString());
    }
}
