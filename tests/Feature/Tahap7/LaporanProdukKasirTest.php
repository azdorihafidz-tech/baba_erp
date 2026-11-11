<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * 4.46 Tahap 2 — Laporan Penjualan Produk di menu Penjualan (bisa dibuka
 * kasir): qty/penjualan/diskon (dibagi per porsi harga)/pendapatan per produk,
 * varian, filter, keuntungan tersembunyi untuk kasir, cocok dgn Laporan Transaksi.
 */
class LaporanProdukKasirTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $c;
    private Cabang $lain;
    private Item $dimsum;
    private Item $gyoza;
    private Item $dimsumLain;
    private ItemCategory $katA;
    private ItemCategory $katB;
    private int $no = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 15:00:00');
        $this->c = Cabang::create(['nama_cabang' => 'Outlet Prod', 'kode_cabang' => 'LP' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true]);
        $this->lain = Cabang::create(['nama_cabang' => 'Outlet Prod 2', 'kode_cabang' => 'LQ' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true]);
        $this->katA = ItemCategory::create(['nama_kategori' => 'Kat Dimsum ' . uniqid(), 'kode_kategori' => 'KA' . rand(100, 999)]);
        $this->katB = ItemCategory::create(['nama_kategori' => 'Kat Gyoza ' . uniqid(), 'kode_kategori' => 'KB' . rand(100, 999)]);
        $kode = 'DMS-' . uniqid();
        $this->dimsum = $this->item($this->c, $kode, 'Dimsum Mentai', $this->katA);
        $this->gyoza = $this->item($this->c, 'GYZ-' . uniqid(), 'Gyoza Panggang', $this->katB);
        $this->dimsumLain = $this->item($this->lain, $kode, 'Dimsum Mentai', $this->katA); // salinan cabang lain, kode sama

        // Order 1 (hari ini): dimsum 2 × 30.000 + gyoza 1 × 20.000 = 80.000, diskon 8.000, service 4.000 → bayar 76.000
        $this->order($this->c, '2026-11-04', [[$this->dimsum, 2, 30000, 24000], [$this->gyoza, 1, 20000, 9000]], 8000, 4000);
        // Order 2 (hari ini): dimsum 1 × 30.000, tanpa diskon
        $this->order($this->c, '2026-11-04', [[$this->dimsum, 1, 30000, 12000]]);
        // Order 3 (bulan lalu): gyoza 3 × 20.000
        $this->order($this->c, '2026-10-10', [[$this->gyoza, 3, 20000, 27000]]);
        // Tidak dihitung
        $this->order($this->c, '2026-11-04', [[$this->dimsum, 9, 30000, 1]], 0, 0, 'dibatalkan');
        $this->order($this->c, '2026-11-04', [[$this->dimsum, 9, 30000, 1]], 0, 0, 'pending');
        $this->order($this->c, '2026-11-04', [[$this->dimsum, 9, 30000, 1]])->delete();
        // Cabang lain (produk kode sama)
        $this->order($this->lain, '2026-11-04', [[$this->dimsumLain, 4, 30000, 48000]]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function item(Cabang $c, string $kode, string $nama, ItemCategory $kat): Item
    {
        return Item::create(['cabang_id' => $c->id, 'item_category_id' => $kat->id, 'kode_item' => $kode, 'nama_item' => $nama,
            'tipe' => 'produk_jual', 'satuan' => 'porsi', 'harga_jual' => 30000, 'is_active' => true]);
    }

    private function order(Cabang $c, string $tgl, array $items, float $diskon = 0, float $service = 0, string $status = 'selesai'): Order
    {
        $totalHarga = array_sum(array_map(fn ($i) => $i[1] * $i[2], $items));
        $o = Order::create([
            'cabang_id' => $c->id, 'nomor_order' => 'LP-' . uniqid() . '-' . (++$this->no), 'tanggal_order' => $tgl,
            'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway', 'total_harga' => $totalHarga, 'diskon' => $diskon,
            'service_charge' => $service, 'total_bayar' => $totalHarga - $diskon + $service, 'jumlah_bayar' => $totalHarga,
            'kembalian' => 0, 'tipe_pembayaran' => 'tunai', 'status' => $status,
        ]);
        foreach ($items as [$item, $qty, $harga, $hpp]) {
            OrderItem::create(['order_id' => $o->id, 'item_id' => $item->id, 'nama_item' => $item->nama_item, 'qty' => $qty,
                'satuan' => 'porsi', 'harga_satuan' => $harga, 'total_harga' => $qty * $harga, 'hpp' => $hpp]);
        }

        return $o;
    }

    private function sebagai(string $role, ?int $cabang = null)
    {
        return $this->actingAs($this->buatUser($role, $this->c->id))->withSession(['active_cabang_id' => $cabang ?? $this->c->id]);
    }

    private function url(array $p = []): string
    {
        return route('penjualan.laporan.produk', $p);
    }

    public function test_hari_ini_per_produk_dengan_diskon_dibagi_porsi_harga(): void
    {
        $res = $this->sebagai('admin_pusat')->get($this->url())->assertOk();
        $baris = $res->viewData('baris')->keyBy('nama');
        $this->assertCount(2, $baris);

        $d = $baris['Dimsum Mentai'];
        $this->assertSame(3.0, $d['qty']);
        $this->assertSame(90000.0, $d['penjualan']);
        $this->assertSame(6000.0, $d['diskon'], '8.000 × 60.000/80.000');
        $this->assertSame(84000.0, $d['pendapatan']);
        $this->assertSame(84000.0 - 36000.0, $d['keuntungan']);
        $this->assertSame(2, $d['transaksi']);

        $g = $baris['Gyoza Panggang'];
        $this->assertSame(2000.0, $g['diskon'], '8.000 × 20.000/80.000');
        $this->assertSame(18000.0, $g['pendapatan']);

        $this->assertSame(4000.0, $res->viewData('biayaTambahan'));
        $this->assertSame(102000.0, $res->viewData('total')['pendapatan']);
        $this->assertSame('qty', $res->viewData('urut'));
        $this->assertSame('Dimsum Mentai', $res->viewData('baris')->first()['nama'], 'urut qty terbanyak');
    }

    public function test_cocok_dengan_laporan_transaksi(): void
    {
        $produk = $this->sebagai('admin_pusat')->get($this->url(['periode' => 'bulan_ini']));
        $trx = $this->sebagai('admin_pusat')->get(route('penjualan.laporan.transaksi', ['tahun' => 2026, 'bulan' => 11]));
        $this->assertSame(
            $trx->viewData('total')['pendapatan'],
            $produk->viewData('total')['pendapatan'] + $produk->viewData('biayaTambahan')
        );
        $this->assertSame(
            $trx->viewData('total')['keuntungan'],
            $produk->viewData('total')['keuntungan'] + $produk->viewData('biayaTambahan')
        );
    }

    public function test_periode_dan_rentang(): void
    {
        $this->assertSame(162000.0, $this->sebagai('kasir')->get($this->url(['periode' => 'semua']))->viewData('total')['pendapatan']);
        $res = $this->sebagai('kasir')->get($this->url(['dari' => '2026-10-01', 'sampai' => '2026-10-31']))->assertOk();
        $this->assertSame(['Gyoza Panggang'], $res->viewData('baris')->pluck('nama')->all());
        $this->assertSame(3.0, $res->viewData('baris')[0]['qty']);
        $this->assertSame('Oktober 2026', $res->viewData('periode')['label']);
        $this->sebagai('kasir')->get($this->url(['dari' => 'x', 'sampai' => '2026-10-31']))->assertNotFound();
    }

    public function test_filter_kategori_cari_dan_urut(): void
    {
        $res = $this->sebagai('kasir')->get($this->url(['kategori' => $this->katB->id]))->assertOk();
        $this->assertSame(['Gyoza Panggang'], $res->viewData('baris')->pluck('nama')->all());
        $this->assertSame(0.0, $res->viewData('biayaTambahan'), 'biaya tambahan tidak ditampilkan saat filter');
        $this->assertSame($this->katB->nama_kategori, $res->viewData('namaKategori'));

        $res = $this->sebagai('kasir')->get($this->url(['cari' => 'mentai']))->assertOk();
        $this->assertSame(['Dimsum Mentai'], $res->viewData('baris')->pluck('nama')->all());

        $res = $this->sebagai('kasir')->get($this->url(['urut' => 'nama']));
        $this->assertSame(['Dimsum Mentai', 'Gyoza Panggang'], $res->viewData('baris')->pluck('nama')->all());

        // Kasir tidak boleh urut keuntungan → kembali ke qty
        $this->assertSame('qty', $this->sebagai('kasir')->get($this->url(['urut' => 'keuntungan']))->viewData('urut'));
        $this->assertSame('keuntungan', $this->sebagai('manajer_cabang')->get($this->url(['urut' => 'keuntungan']))->viewData('urut'));
    }

    public function test_kasir_tidak_melihat_keuntungan(): void
    {
        $res = $this->sebagai('kasir')->get($this->url())->assertOk();
        $this->assertFalse($res->viewData('lihatUntung'));
        $this->assertArrayNotHasKey('keuntungan', $res->viewData('total'));
        foreach ($res->viewData('baris') as $r) {
            $this->assertArrayNotHasKey('keuntungan', $r);
            $this->assertArrayNotHasKey('hpp', $r);
            $this->assertArrayNotHasKey('margin', $r);
        }
        $res->assertDontSee('kotakKeuntungan')->assertDontSee("keuntungan: 'Keuntungan'", false)
            ->assertDontSee('data-metrik="keuntungan"', false)->assertDontSee('>Modal<', false);

        $res = $this->sebagai('manajer_cabang')->get($this->url())->assertOk();
        $res->assertSee('kotakKeuntungan')->assertSee('data-metrik="keuntungan"', false);
    }

    public function test_semua_cabang_gabung_per_kode_item(): void
    {
        $res = $this->actingAs($this->buatUser('owner'))->withSession(['active_cabang_id' => null])->get($this->url())->assertOk();
        $dimsum = $res->viewData('baris')->where('kode', $this->dimsum->kode_item);
        $this->assertCount(1, $dimsum, 'produk kode sama di 2 cabang = 1 baris');
        $this->assertSame(7.0, $dimsum->first()['qty']);

        // Kasir: cabangnya sendiri walau sesi menunjuk cabang lain
        $res = $this->sebagai('kasir', $this->lain->id)->get($this->url());
        $this->assertSame(3.0, $res->viewData('baris')->firstWhere('nama', 'Dimsum Mentai')['qty']);
    }

    public function test_varian_dirinci_kalau_lebih_dari_satu(): void
    {
        $varA = \App\Models\ItemVariant::create(['item_id' => $this->gyoza->id, 'is_active' => true]);
        $varB = \App\Models\ItemVariant::create(['item_id' => $this->gyoza->id, 'is_active' => true]);
        $o = $this->order($this->c, '2026-11-04', []);
        foreach ([[$varA, 'Gyoza Panggang - Pedas', 2], [$varB, 'Gyoza Panggang - Original', 1]] as [$v, $nama, $qty]) {
            OrderItem::create(['order_id' => $o->id, 'item_id' => $this->gyoza->id, 'item_variant_id' => $v->id, 'nama_item' => $nama,
                'qty' => $qty, 'satuan' => 'porsi', 'harga_satuan' => 20000, 'total_harga' => $qty * 20000, 'hpp' => 0]);
        }
        $o->update(['total_harga' => 60000, 'total_bayar' => 60000]);

        $res = $this->sebagai('kasir')->get($this->url())->assertOk();
        $gyoza = $res->viewData('baris')->firstWhere('nama', 'Gyoza Panggang');
        $this->assertSame(4.0, $gyoza['qty']);
        $this->assertSame(['Gyoza Panggang - Pedas', 'Gyoza Panggang - Original'], $gyoza['varian']->pluck('nama')->all());
        $this->assertArrayNotHasKey('keuntungan', $gyoza['varian'][0]);
        $res->assertSee('2 varian');
        $this->assertTrue($res->viewData('baris')->firstWhere('nama', 'Dimsum Mentai')['varian']->isEmpty());
    }

    public function test_tanpa_izin_ditolak_dan_menu_sidebar(): void
    {
        $this->sebagai('helper')->get($this->url())->assertForbidden();
        $this->sebagai('kasir')->get(route('penjualan.index'))->assertOk()->assertSee('Laporan Produk');
    }

    public function test_export_excel_dan_pdf(): void
    {
        foreach (['kasir', 'admin_pusat'] as $role) {
            $this->sebagai($role)->get($this->url(['export' => 'excel']))->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $this->sebagai($role)->get($this->url(['periode' => 'semua', 'export' => 'pdf']))->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_isi_excel_kasir_tanpa_kolom_keuntungan_dan_baris_biaya(): void
    {
        $this->actingAs($this->buatUser('kasir', $this->c->id));
        $svc = app(\App\Services\LaporanBarangKasirService::class);
        $baris = $svc->produk(today(), today(), $this->c->id);
        [$tanpa, $totalTanpa] = \App\Http\Controllers\LaporanProdukKasirController::sembunyikanUntung($baris, \App\Services\LaporanBarangKasirService::total($baris));

        $rows = (new \App\Exports\LaporanBarangKasirExport('produk', 'Laporan', ['Periode' => 'x'], $tanpa, $totalTanpa, 4000, false))->array();
        $header = $rows[4];
        $this->assertSame(['No', 'Kode', 'Produk', 'Kategori', 'Qty', 'Satuan', 'Penjualan (sebelum diskon)', 'Diskon', 'Pendapatan'], $header);
        $teks = json_encode($rows);
        $this->assertStringContainsString('Service charge', $teks);
        $this->assertStringContainsString('sama dengan Laporan Transaksi', $teks);
        $akhir = collect($rows)->first(fn ($r) => ($r[0] ?? '') === 'Total pendapatan (sama dengan Laporan Transaksi)');
        $this->assertSame(106000.0, end($akhir));

        $rows = (new \App\Exports\LaporanBarangKasirExport('produk', 'Laporan', ['Periode' => 'x'], $baris, \App\Services\LaporanBarangKasirService::total($baris), 0, true))->array();
        $this->assertSame(['Modal (HPP)', 'Keuntungan', 'Margin %'], array_slice($rows[4], -3));
    }
}
