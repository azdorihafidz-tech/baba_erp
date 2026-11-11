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
 * 4.46 Tahap 3 — Laporan Penjualan Kategori di menu Penjualan: angka per
 * kategori = jumlah produknya (sama dengan Laporan Produk), produk di dalam
 * kategori, porsi, keuntungan tersembunyi untuk kasir, export.
 */
class LaporanKategoriKasirTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $c;
    private ItemCategory $katA;
    private ItemCategory $katB;
    private int $no = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 15:00:00');
        $this->c = Cabang::create(['nama_cabang' => 'Outlet Kat', 'kode_cabang' => 'LK' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true]);
        $this->katA = ItemCategory::create(['nama_kategori' => 'Dimsum Uji ' . uniqid(), 'kode_kategori' => 'UA' . rand(100, 999)]);
        $this->katB = ItemCategory::create(['nama_kategori' => 'Minuman Uji ' . uniqid(), 'kode_kategori' => 'UB' . rand(100, 999)]);

        $mentai = $this->item('Dimsum Mentai', $this->katA);
        $keju = $this->item('Dimsum Keju', $this->katA);
        $teh = $this->item('Es Teh', $this->katB);
        $tanpa = $this->item('Produk Lepas', null);

        // 100.000 total: mentai 60.000, keju 20.000, teh 20.000; diskon 10.000; service 5.000
        $this->order([[$mentai, 2, 30000, 20000], [$keju, 1, 20000, 8000], [$teh, 5, 4000, 4000]], 10000, 5000);
        $this->order([[$tanpa, 1, 15000, 5000]]);
        $this->order([[$teh, 9, 10000, 1]], 0, 0, 'dibatalkan');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function item(string $nama, ?ItemCategory $kat): Item
    {
        return Item::create(['cabang_id' => $this->c->id, 'item_category_id' => $kat?->id, 'kode_item' => 'UK-' . uniqid(), 'nama_item' => $nama,
            'tipe' => 'produk_jual', 'satuan' => 'porsi', 'harga_jual' => 10000, 'is_active' => true]);
    }

    private function order(array $items, float $diskon = 0, float $service = 0, string $status = 'selesai'): Order
    {
        $total = array_sum(array_map(fn ($i) => $i[1] * $i[2], $items));
        $o = Order::create([
            'cabang_id' => $this->c->id, 'nomor_order' => 'LK-' . uniqid() . '-' . (++$this->no), 'tanggal_order' => '2026-11-04',
            'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway', 'total_harga' => $total, 'diskon' => $diskon,
            'service_charge' => $service, 'total_bayar' => $total - $diskon + $service, 'jumlah_bayar' => $total,
            'kembalian' => 0, 'tipe_pembayaran' => 'tunai', 'status' => $status,
        ]);
        foreach ($items as [$item, $qty, $harga, $hpp]) {
            OrderItem::create(['order_id' => $o->id, 'item_id' => $item->id, 'nama_item' => $item->nama_item, 'qty' => $qty,
                'satuan' => 'porsi', 'harga_satuan' => $harga, 'total_harga' => $qty * $harga, 'hpp' => $hpp]);
        }

        return $o;
    }

    private function sebagai(string $role)
    {
        return $this->actingAs($this->buatUser($role, $this->c->id))->withSession(['active_cabang_id' => $this->c->id]);
    }

    private function url(array $p = []): string
    {
        return route('penjualan.laporan.kategori', $p);
    }

    public function test_angka_per_kategori_dan_produk_di_dalamnya(): void
    {
        $res = $this->sebagai('admin_pusat')->get($this->url())->assertOk();
        $baris = $res->viewData('baris')->keyBy('kategori');
        $this->assertCount(3, $baris);

        $a = $baris[$this->katA->nama_kategori];
        $this->assertSame(2, $a['jumlah_produk']);
        $this->assertSame(3.0, $a['qty']);
        $this->assertSame(80000.0, $a['penjualan']);
        $this->assertSame(8000.0, $a['diskon']);
        $this->assertSame(72000.0, $a['pendapatan']);
        $this->assertSame(72000.0 - 28000.0, $a['keuntungan']);
        $this->assertSame(1, $a['transaksi']);
        $this->assertSame(['Dimsum Mentai', 'Dimsum Keju'], $a['produk']->pluck('nama')->all());

        $this->assertSame(18000.0, $baris[$this->katB->nama_kategori]['pendapatan']);
        $this->assertSame(15000.0, $baris['Tanpa kategori']['pendapatan']);
        $this->assertNull($baris['Tanpa kategori']['kategori_id']);

        $this->assertSame(105000.0, $res->viewData('total')['pendapatan']);
        $this->assertSame(5000.0, $res->viewData('biayaTambahan'));
        $this->assertSame('pendapatan', $res->viewData('urut'));
        $this->assertSame($this->katA->nama_kategori, $res->viewData('baris')->first()['kategori']);
    }

    public function test_sama_dengan_laporan_produk_dan_transaksi(): void
    {
        $kat = $this->sebagai('admin_pusat')->get($this->url());
        $prod = $this->sebagai('admin_pusat')->get(route('penjualan.laporan.produk'));
        $trx = $this->sebagai('admin_pusat')->get(route('penjualan.laporan.transaksi'));
        foreach (['qty', 'penjualan', 'diskon', 'pendapatan', 'hpp', 'keuntungan'] as $k) {
            $this->assertSame($prod->viewData('total')[$k], $kat->viewData('total')[$k], $k);
        }
        $this->assertSame($trx->viewData('total')['pendapatan'], $kat->viewData('total')['pendapatan'] + $kat->viewData('biayaTambahan'));
    }

    public function test_urut_dan_tautan_ke_laporan_produk(): void
    {
        $res = $this->sebagai('kasir')->get($this->url(['urut' => 'qty']))->assertOk();
        $this->assertSame([$this->katB->nama_kategori, $this->katA->nama_kategori, 'Tanpa kategori'], $res->viewData('baris')->pluck('kategori')->all());
        $this->assertSame('pendapatan', $this->sebagai('kasir')->get($this->url(['urut' => 'keuntungan']))->viewData('urut'));

        $res->assertSee(e(route('penjualan.laporan.produk', ['periode' => 'hari_ini', 'kategori' => $this->katA->id])), false)
            ->assertSee(e(route('penjualan.laporan.produk', ['periode' => 'hari_ini', 'kategori' => -1])), false);
        $prod = $this->sebagai('kasir')->get(route('penjualan.laporan.produk', ['kategori' => -1]))->assertOk();
        $this->assertSame(['Produk Lepas'], $prod->viewData('baris')->pluck('nama')->all());
    }

    public function test_kasir_tidak_melihat_keuntungan(): void
    {
        $res = $this->sebagai('kasir')->get($this->url())->assertOk();
        $this->assertArrayNotHasKey('keuntungan', $res->viewData('total'));
        foreach ($res->viewData('baris') as $r) {
            $this->assertArrayNotHasKey('keuntungan', $r);
            foreach ($r['produk'] as $p) {
                $this->assertArrayNotHasKey('keuntungan', $p);
                $this->assertArrayNotHasKey('hpp', $p);
            }
        }
        $res->assertDontSee('kotakKeuntungan')->assertDontSee("keuntungan: 'Keuntungan'", false)
            ->assertDontSee('data-metrik="keuntungan"', false)->assertDontSee('>Modal<', false);

        $this->sebagai('manajer_cabang')->get($this->url())->assertOk()->assertSee('kotakKeuntungan')->assertSee('data-metrik="keuntungan"', false);
    }

    public function test_tanpa_izin_menu_dan_export(): void
    {
        $this->sebagai('helper')->get($this->url())->assertForbidden();
        $this->sebagai('kasir')->get(route('penjualan.index'))->assertOk()->assertSee('Laporan Kategori');

        foreach (['kasir', 'admin_pusat'] as $role) {
            $this->sebagai($role)->get($this->url(['export' => 'excel']))->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $this->sebagai($role)->get($this->url(['periode' => 'bulan_ini', 'export' => 'pdf']))->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }

        $this->actingAs($this->buatUser('kasir', $this->c->id));
        $svc = app(\App\Services\LaporanBarangKasirService::class);
        $baris = $svc->kategori(today(), today(), $this->c->id);
        $rows = (new \App\Exports\LaporanBarangKasirExport('kategori', 'Laporan', ['Periode' => 'x'], $baris, \App\Services\LaporanBarangKasirService::total($baris), 5000, false))->array();
        $this->assertSame(['No', 'Kategori', 'Jumlah Produk', 'Qty', 'Penjualan (sebelum diskon)', 'Diskon', 'Pendapatan'], $rows[4]);
        $this->assertStringContainsString('• Dimsum Mentai', json_encode($rows, JSON_UNESCAPED_UNICODE));
    }

    public function test_panduan_dan_tooltip_terpasang(): void
    {
        (new \Database\Seeders\LaporanPenjualanKasirSeeder())->run();
        foreach (['laporan-transaksi-kasir', 'laporan-produk-kasir', 'laporan-kategori-kasir'] as $slug) {
            $this->assertNotNull(\App\Models\Panduan::getBySlug($slug), $slug);
        }
        $this->sebagai('kasir')->get($this->url())->assertOk()->assertSee('panduanModal-laporan-kategori-kasir', false);
        $this->sebagai('kasir')->get(route('penjualan.laporan.produk'))->assertOk()->assertSee('panduanModal-laporan-produk-kasir', false);
        $this->assertDatabaseHas('tooltips', ['key' => 'laporan_kategori_kasir.rincian']);
    }
}
