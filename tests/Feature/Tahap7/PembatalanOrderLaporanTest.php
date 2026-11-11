<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use App\Models\Order;
use App\Models\Stock;
use App\Models\TransaksiKeuangan;
use App\Services\AnalisisPenjualanService;
use App\Services\ClosingService;
use App\Services\LabaRugiFormalService;
use App\Services\LaporanLabaRugiService;
use App\Services\SetoranHarianService;
use App\Services\SetoranKasirService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Laporan Owner 2026-11-04: order yang sudah dibatalkan (Riwayat = Dibatalkan)
 * dikira masih terhitung di Laporan Laba Rugi / pemasukan. Test ini memakai alur
 * HTTP asli (POS → Batalkan) dan memastikan SEMUA laporan kembali ke angka
 * sebelum transaksi.
 */
class PembatalanOrderLaporanTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $c;
    private Item $produk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->c = Cabang::create(['nama_cabang' => 'Outlet Batal', 'kode_cabang' => 'BT' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true,
            'dine_in_aktif' => false, 'takeaway_aktif' => true, 'frozen_aktif' => false, 'wajib_bukti_transfer' => false, 'wajib_bukti_qris' => false]);
        foreach (['tunai', 'transfer', 'qris'] as $jenis) {
            Kas::create(['cabang_id' => $this->c->id, 'default_untuk' => $jenis, 'nama_kas' => "Kas {$jenis} BT", 'tipe_kas' => $jenis === 'tunai' ? 'tunai' : 'bank', 'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true]);
        }
        $kategori = \App\Models\ItemCategory::where('kode_kategori', '!=', 'TMB')->value('id');
        $this->produk = Item::create(['cabang_id' => $this->c->id, 'item_category_id' => $kategori, 'kode_item' => 'BT-' . uniqid(), 'nama_item' => 'Gyoza Batal',
            'tipe' => 'produk_jual', 'satuan' => 'porsi', 'harga_jual' => 25000, 'harga_beli_terakhir' => 10000, 'is_active' => true]);
        Stock::create(['item_id' => $this->produk->id, 'lokasi_id' => $this->c->id, 'qty' => 50, 'qty_minimum' => 0]);
    }

    private function kasir()
    {
        return $this->actingAs($this->buatUser('owner', $this->c->id))->withSession(['active_cabang_id' => $this->c->id]);
    }

    /** Semua angka laporan untuk cabang uji, hari ini. */
    private function angka(): array
    {
        $hari = today();
        $closing = app(ClosingService::class)->hitung($hari->copy(), $hari->copy(), $this->c->id);
        $lr = app(LaporanLabaRugiService::class)->getRingkasan($hari->copy(), $hari->copy(), $this->c->id);
        $harian = app(SetoranHarianService::class);

        return [
            'laba_rugi_omzet'      => (float) ($lr['total_omzet'] ?? 0),
            'laba_rugi_hpp'        => (float) ($lr['total_hpp'] ?? 0),
            'laba_rugi_per_item'   => app(LaporanLabaRugiService::class)->getBreakdownPerItem($hari->copy(), $hari->copy(), $this->c->id)->count(),
            'pemasukan_transaksi'  => (float) TransaksiKeuangan::where('cabang_id', $this->c->id)->where('tipe', 'pemasukan')->sum('jumlah'),
            'saldo_kas'            => (float) Kas::where('cabang_id', $this->c->id)->sum('saldo_sekarang'),
            'setoran_kasir_sistem' => (float) collect(app(SetoranKasirService::class)->hitungOtomatis($this->c->id, $hari->toDateString()))->flatten()->filter(fn ($v) => is_numeric($v))->sum(),
            'setoran_harian'       => $harian->hitungPemasukan($hari->copy()->startOfDay(), $hari->copy()->endOfDay(), $this->c->id),
            'setoran_harian_order' => $harian->countOrder($hari->copy()->startOfDay(), $hari->copy()->endOfDay(), $this->c->id),
            'closing'              => json_encode($closing['penjualan'] ?? []) . json_encode($closing['laba_rugi'] ?? []),
            'laba_rugi_formal'     => json_encode(app(LabaRugiFormalService::class)->hitungLabaRugi($hari->copy(), $hari->copy(), $this->c->id)),
        ];
    }

    private function batalkan(Order $order): void
    {
        $this->kasir()->post(route('penjualan.batalkan', $order), ['alasan_pembatalan_kategori' => 'salah_input'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('dibatalkan', $order->fresh()->status->value);
    }

    public function test_order_tunai_dibatalkan_hilang_dari_semua_laporan(): void
    {
        $awal = $this->angka();

        $this->kasir()->post(route('penjualan.store'), [
            'tipe_transaksi' => 'takeaway', 'nama_pelanggan' => 'Walk-in',
            'items' => [['item_id' => $this->produk->id, 'qty' => 2, 'harga_satuan' => 25000, 'satuan' => 'porsi', 'nama_item' => 'Gyoza Batal']],
            'payments' => [['metode' => 'tunai', 'jumlah' => 100000]], 'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => 100000,
        ])->assertSessionHasNoErrors();
        $order = Order::where('cabang_id', $this->c->id)->latest('id')->firstOrFail();
        $this->assertNotEquals($awal, $this->angka(), 'order harus terhitung sebelum dibatalkan');

        $this->batalkan($order);
        $this->assertEquals($awal, $this->angka());
    }

    public function test_order_split_tunai_qris_dibatalkan_hilang_dari_semua_laporan(): void
    {
        $awal = $this->angka();

        $this->kasir()->post(route('penjualan.store'), [
            'tipe_transaksi' => 'takeaway', 'nama_pelanggan' => 'Split',
            'items' => [['item_id' => $this->produk->id, 'qty' => 2, 'harga_satuan' => 25000, 'satuan' => 'porsi', 'nama_item' => 'Gyoza Batal']],
            'payments' => [['metode' => 'tunai', 'jumlah' => 20000], ['metode' => 'qris', 'jumlah' => 30000]],
            'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => 50000,
        ])->assertSessionHasNoErrors();
        $order = Order::where('cabang_id', $this->c->id)->latest('id')->firstOrFail();

        $this->batalkan($order);
        $this->assertEquals($awal, $this->angka());
    }

    public function test_bill_tersimpan_dibayar_lalu_dibatalkan_hilang_dari_semua_laporan(): void
    {
        $awal = $this->angka();

        $res = $this->kasir()->postJson(route('penjualan.simpan-bill'), [
            'tipe_transaksi' => 'takeaway', 'nama_pelanggan' => 'Bill',
            'items' => [['item_id' => $this->produk->id, 'nama_item' => 'Gyoza Batal', 'qty' => 2, 'satuan' => 'porsi', 'harga_satuan' => 25000]],
        ])->assertOk()->json();
        $order = Order::findOrFail($res['order_id']);
        $this->kasir()->postJson(route('penjualan.charge-bill', $order), [
            'payments' => [['metode' => 'tunai', 'jumlah' => 50000]], 'jumlah_bayar' => 50000,
        ])->assertOk();
        $this->assertSame('selesai', $order->fresh()->status->value);

        $this->batalkan($order->fresh());
        $this->assertEquals($awal, $this->angka());
    }

    public function test_bill_tersimpan_belum_dibayar_tidak_masuk_laba_rugi(): void
    {
        $awal = $this->angka();

        $this->kasir()->postJson(route('penjualan.simpan-bill'), [
            'tipe_transaksi' => 'takeaway', 'nama_pelanggan' => 'Bill Pending',
            'items' => [['item_id' => $this->produk->id, 'nama_item' => 'Gyoza Batal', 'qty' => 2, 'satuan' => 'porsi', 'harga_satuan' => 25000]],
        ])->assertOk();

        $this->assertEquals($awal, $this->angka(), 'bill yang belum dibayar belum boleh jadi omzet/pemasukan');
    }

    private function jual(int $qty = 2): Order
    {
        $this->kasir()->post(route('penjualan.store'), [
            'tipe_transaksi' => 'takeaway', 'nama_pelanggan' => 'Walk-in',
            'items' => [['item_id' => $this->produk->id, 'qty' => $qty, 'harga_satuan' => 25000, 'satuan' => 'porsi', 'nama_item' => 'Gyoza Batal']],
            'payments' => [['metode' => 'tunai', 'jumlah' => 25000 * $qty]], 'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => 25000 * $qty,
        ])->assertSessionHasNoErrors();

        return Order::where('cabang_id', $this->c->id)->latest('id')->firstOrFail();
    }

    private function urlLabaRugi(): string
    {
        return route('laporan.laba-rugi.index', ['dari' => today()->toDateString(), 'sampai' => today()->toDateString(), 'cabang_id' => $this->c->id]);
    }

    public function test_halaman_laporan_laba_rugi_tidak_menampilkan_order_batal(): void
    {
        $this->batalkan($this->jual());

        $this->kasir()->get($this->urlLabaRugi())->assertOk()->assertDontSee('Gyoza Batal');
    }

    public function test_order_sudah_dibayar_tidak_bisa_dihapus_harus_dibatalkan_dulu(): void
    {
        $order = $this->jual();

        $this->kasir()
            ->delete(route('penjualan.destroy', $order))->assertSessionHas('error');
        $this->assertNotSoftDeleted('orders', ['id' => $order->id]);
        $this->assertSame('selesai', $order->fresh()->status->value);

        // Setelah dibatalkan (stok & kas kembali) baru boleh dihapus
        $awalStok = (float) Stock::where('item_id', $this->produk->id)->where('lokasi_id', $this->c->id)->value('qty');
        $this->batalkan($order);
        $this->kasir()
            ->delete(route('penjualan.destroy', $order))->assertSessionHas('success');
        $this->assertSoftDeleted('orders', ['id' => $order->id]);
        $this->assertSame(0.0, (float) Kas::where('cabang_id', $this->c->id)->sum('saldo_sekarang'));
        $this->assertGreaterThanOrEqual($awalStok, (float) Stock::where('item_id', $this->produk->id)->where('lokasi_id', $this->c->id)->value('qty'));
    }

    public function test_bill_belum_dibayar_boleh_dihapus(): void
    {
        $res = $this->kasir()->postJson(route('penjualan.simpan-bill'), [
            'tipe_transaksi' => 'takeaway', 'nama_pelanggan' => 'Bill Hapus',
            'items' => [['item_id' => $this->produk->id, 'nama_item' => 'Gyoza Batal', 'qty' => 1, 'satuan' => 'porsi', 'harga_satuan' => 25000]],
        ])->assertOk()->json();

        $this->kasir()
            ->delete(route('penjualan.destroy', $res['order_id']))->assertSessionHas('success');
        $this->assertSoftDeleted('orders', ['id' => $res['order_id']]);
    }

    /** Data lama: order selesai yang terlanjur dihapus (sebelum aturan ini) tidak boleh terhitung. */
    public function test_order_terhapus_lama_tidak_terhitung_di_laporan(): void
    {
        $awal = $this->angka();
        $order = $this->jual();
        app(\App\Services\CascadeDeleteService::class)->deleteOrderCascade($order);

        $sekarang = $this->angka();
        foreach (['laba_rugi_omzet', 'laba_rugi_hpp', 'laba_rugi_per_item', 'pemasukan_transaksi', 'setoran_kasir_sistem',
                  'setoran_harian', 'setoran_harian_order', 'closing', 'laba_rugi_formal'] as $k) {
            $this->assertEquals($awal[$k], $sekarang[$k], "{$k} masih menghitung order yang dihapus");
        }
        $this->kasir()->get($this->urlLabaRugi())->assertOk()->assertDontSee('Gyoza Batal');
        $this->kasir()->get(route('laporan.penjualan', ['dari' => today()->toDateString(), 'sampai' => today()->toDateString(), 'cabang_id' => $this->c->id]))
            ->assertOk()->assertDontSee($order->nomor_order);
    }

    public function test_dipulihkan_dari_data_terhapus_lalu_dibatalkan_stok_dan_kas_kembali(): void
    {
        $stokAwal = (float) Stock::where('item_id', $this->produk->id)->where('lokasi_id', $this->c->id)->value('qty');
        $order = $this->jual();
        app(\App\Services\CascadeDeleteService::class)->deleteOrderCascade($order);
        $this->assertSame(50000.0, (float) Kas::where('cabang_id', $this->c->id)->sum('saldo_sekarang'), 'hapus lama tidak mengembalikan kas');

        $this->kasir()->post(route('trash.restore', ['model' => 'orders', 'id' => $order->id]))->assertSessionHasNoErrors();
        $this->assertNotSoftDeleted('orders', ['id' => $order->id]);
        $this->batalkan($order->fresh());

        $this->assertSame(0.0, (float) Kas::where('cabang_id', $this->c->id)->sum('saldo_sekarang'));
        $this->assertSame($stokAwal, (float) Stock::where('item_id', $this->produk->id)->where('lokasi_id', $this->c->id)->value('qty'));
    }

    public function test_riwayat_menampilkan_batalkan_untuk_order_dibayar_dan_hapus_hanya_untuk_yang_dibatalkan(): void
    {
        $dibayar = $this->jual();
        $batal   = $this->jual(1);
        $this->batalkan($batal);

        $html = $this->kasir()
            ->get(route('penjualan.index'))->assertOk()
            ->assertSee('id="modalBatalkanList"', false)
            ->assertSee('Pelanggan Batal Datang')
            ->getContent();

        $this->assertStringContainsString(route('penjualan.batalkan', $dibayar->id), $html);
        $this->assertStringNotContainsString(route('penjualan.destroy', $dibayar->id) . "'", $html);
        $this->assertStringContainsString(route('penjualan.destroy', $batal->id) . "'", $html);
        $this->assertStringNotContainsString(route('penjualan.batalkan', $batal->id) . "'", $html);
    }

    public function test_panduan_riwayat_menjelaskan_batalkan_vs_hapus_idempotent(): void
    {
        (new \Database\Seeders\Perbaikan438PanduanSeeder())->run();
        (new \Database\Seeders\Perbaikan438PanduanSeeder())->run();

        $konten = (string) \App\Models\Panduan::where('slug', 'riwayat-order')->value('konten');
        $this->assertStringContainsString('Batalkan vs Hapus', $konten);
        $this->assertSame(1, substr_count($konten, '<!-- BATAL-HAPUS-445 -->'));
    }
}
