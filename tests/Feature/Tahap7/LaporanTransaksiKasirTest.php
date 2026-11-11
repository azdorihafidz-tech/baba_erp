<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * 4.46 Tahap 1 — Laporan Transaksi di menu Penjualan (bisa dibuka kasir):
 * bertingkat tahun → bulan → hari → transaksi, keuntungan tersembunyi untuk
 * kasir, ikut cabang aktif, order tidak sah tidak dihitung, export.
 */
class LaporanTransaksiKasirTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $c;
    private Cabang $lain;
    private int $no = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 15:00:00');
        $this->c = $this->buatCabang('Outlet Lap');
        $this->lain = $this->buatCabang('Outlet Lain');

        // 2025-12-20: 1 trx; 2026-10-15: 2 trx; 2026-11-04 (hari ini): 1 trx
        $this->order($this->c, '2025-12-20', 30000, 12000, '10:00');
        $this->order($this->c, '2026-10-15', 50000, 20000, '09:15');
        $this->order($this->c, '2026-10-15', 25000, 10000, '13:40');
        $this->order($this->c, '2026-11-04', 40000, 15000, '11:05');
        // tidak dihitung: dibatalkan, bill belum dibayar, dihapus, cabang lain
        $this->order($this->c, '2026-10-15', 99000, 1000, '14:00', 'dibatalkan');
        $this->order($this->c, '2026-10-15', 88000, 1000, '14:10', 'pending');
        $this->order($this->c, '2026-10-15', 77000, 1000, '14:20')->delete();
        $this->order($this->lain, '2026-10-15', 66000, 1000, '14:30');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function buatCabang(string $nama): Cabang
    {
        return Cabang::create(['nama_cabang' => $nama, 'kode_cabang' => 'LT' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true]);
    }

    private function order(Cabang $c, string $tgl, int $bayar, int $hpp, string $jam, string $status = 'selesai'): Order
    {
        $o = Order::create([
            'cabang_id' => $c->id, 'nomor_order' => 'LT-' . uniqid() . '-' . (++$this->no), 'tanggal_order' => $tgl,
            'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway', 'nama_pelanggan' => 'Budi ' . $this->no,
            'total_harga' => $bayar, 'diskon' => 0, 'total_bayar' => $bayar, 'jumlah_bayar' => $bayar, 'kembalian' => 0,
            'tipe_pembayaran' => 'tunai', 'status' => $status,
        ]);
        $o->forceFill(['created_at' => "{$tgl} {$jam}:00"])->saveQuietly();
        OrderItem::create(['order_id' => $o->id, 'nama_item' => 'Gyoza', 'qty' => 1, 'satuan' => 'porsi',
            'harga_satuan' => $bayar, 'total_harga' => $bayar, 'hpp' => $hpp]);

        return $o;
    }

    private function sebagai(string $role)
    {
        return $this->actingAs($this->buatUser($role, $this->c->id))->withSession(['active_cabang_id' => $this->c->id]);
    }

    private function url(array $p = []): string
    {
        return route('penjualan.laporan.transaksi', $p);
    }

    public function test_bawaan_hari_ini_daftar_transaksi(): void
    {
        $res = $this->sebagai('kasir')->get($this->url())->assertOk();
        $baris = $res->viewData('baris');
        $this->assertSame('transaksi', $res->viewData('posisi')['level']);
        $this->assertCount(1, $baris);
        $this->assertSame('11:05', $baris[0]['jam']);
        $this->assertSame(40000.0, $res->viewData('total')['pendapatan']);
        $this->assertSame('hari_ini', $res->viewData('cepat'));
    }

    public function test_selama_ini_per_tahun(): void
    {
        $res = $this->sebagai('admin_pusat')->get($this->url(['semua' => 1]))->assertOk();
        $baris = $res->viewData('baris')->keyBy('kunci');
        $this->assertSame([2025, 2026], $baris->keys()->all());
        $this->assertSame(1, $baris[2025]['transaksi']);
        $this->assertSame(30000.0, $baris[2025]['pendapatan']);
        $this->assertSame(3, $baris[2026]['transaksi']);
        $this->assertSame(115000.0, $baris[2026]['pendapatan']);
        $this->assertSame(115000.0 - 45000.0, $baris[2026]['keuntungan']);
        $this->assertSame(145000.0, $res->viewData('total')['pendapatan']);
    }

    public function test_tahun_per_bulan_sampai_bulan_berjalan(): void
    {
        $res = $this->sebagai('admin_pusat')->get($this->url(['tahun' => 2026]))->assertOk();
        $baris = $res->viewData('baris');
        $this->assertCount(11, $baris, 'tahun berjalan: Januari s.d. November');
        $okt = $baris->firstWhere('kunci', 10);
        $this->assertSame(2, $okt['transaksi']);
        $this->assertSame(75000.0, $okt['pendapatan']);
        $this->assertSame(['tahun' => 2026, 'bulan' => 10], $okt['params']);
        $this->assertSame(0, $baris->firstWhere('kunci', 1)['transaksi']);
        $this->assertSame('tahun_ini', $res->viewData('cepat'));

        $this->assertCount(12, $this->sebagai('admin_pusat')->get($this->url(['tahun' => 2025]))->viewData('baris'));
    }

    public function test_bulan_per_hari_dan_hari_daftar_transaksi(): void
    {
        $res = $this->sebagai('admin_pusat')->get($this->url(['tahun' => 2026, 'bulan' => 10]))->assertOk();
        $baris = $res->viewData('baris');
        $this->assertCount(31, $baris);
        $hari15 = $baris->firstWhere('kunci', 15);
        $this->assertSame(['tanggal' => '2026-10-15'], $hari15['params']);
        $this->assertSame(2, $hari15['transaksi']);

        $res = $this->sebagai('admin_pusat')->get($this->url(['tanggal' => '2026-10-15']))->assertOk();
        $trx = $res->viewData('baris');
        $this->assertSame(['09:15', '13:40'], $trx->pluck('jam')->all());
        $this->assertSame(75000.0, $res->viewData('total')['pendapatan']);
        $this->assertSame(45000.0, $res->viewData('total')['keuntungan']);
        $this->assertSame(['09:00', '10:00', '11:00', '12:00', '13:00'], $res->viewData('grafik')->pluck('label_pendek')->all());
        $res->assertSee('openStrukModal(' . $trx[0]['id'] . ')', false);

        // Bulan berjalan dipotong sampai hari ini
        $this->assertCount(4, $this->sebagai('admin_pusat')->get($this->url(['tahun' => 2026, 'bulan' => 11]))->viewData('baris'));
        // Bulan yang belum terjadi kosong
        $this->assertCount(0, $this->sebagai('admin_pusat')->get($this->url(['tahun' => 2026, 'bulan' => 12]))->viewData('baris'));
    }

    public function test_jejak_dan_tombol_kembali(): void
    {
        $res = $this->sebagai('kasir')->get($this->url(['tanggal' => '2026-10-15']))->assertOk();
        $jejak = $res->viewData('jejak');
        $this->assertSame(['Selama ini', '2026', 'Oktober', '15 Oktober'], array_column($jejak['jejak'], 'label'));
        $this->assertSame(['tahun' => 2026, 'bulan' => 10], $jejak['kembali']);
        $this->assertNull(end($jejak['jejak'])['params']);
        $res->assertSee(e($this->url(['tahun' => 2026, 'bulan' => 10])), false);

        $this->assertNull($this->sebagai('kasir')->get($this->url(['semua' => 1]))->viewData('jejak')['kembali']);
    }

    public function test_kasir_tidak_melihat_keuntungan(): void
    {
        foreach ([['semua' => 1], ['tahun' => 2026], ['tahun' => 2026, 'bulan' => 10], ['tanggal' => '2026-10-15']] as $p) {
            $res = $this->sebagai('kasir')->get($this->url($p))->assertOk();
            $this->assertFalse($res->viewData('lihatUntung'));
            $this->assertArrayNotHasKey('keuntungan', $res->viewData('total'));
            foreach ($res->viewData('baris') as $r) {
                $this->assertArrayNotHasKey('keuntungan', $r);
                $this->assertArrayNotHasKey('hpp', $r);
            }
            // Teks panduan (tombol Cara Pakai) boleh menjelaskan istilah keuntungan; angka/kolomnya tidak boleh ada
            $res->assertDontSee('kotakKeuntungan')->assertDontSee('Untung</th>', false)->assertDontSee("label: 'Keuntungan'", false);
        }

        $res = $this->sebagai('manajer_cabang')->get($this->url(['tanggal' => '2026-10-15']))->assertOk();
        $this->assertTrue($res->viewData('lihatUntung'));
        $res->assertSee('kotakKeuntungan')->assertSee('Untung</th>', false)->assertSee("label: 'Keuntungan'", false);
    }

    public function test_ikut_cabang_aktif_dan_semua_cabang(): void
    {
        $res = $this->actingAs($this->buatUser('admin_pusat', $this->c->id))
            ->withSession(['active_cabang_id' => $this->lain->id])->get($this->url(['tanggal' => '2026-10-15']));
        $this->assertSame([66000.0], $res->viewData('baris')->pluck('pendapatan')->all());

        // Semua Cabang = gabungan (user akses semua cabang tanpa cabang aktif)
        $owner = $this->buatUser('owner');
        $res = $this->actingAs($owner)->withSession(['active_cabang_id' => null])->get($this->url(['tanggal' => '2026-10-15']));
        $this->assertTrue($res->viewData('semuaCabang'));
        $semua = $res->viewData('baris')->pluck('pendapatan');
        $this->assertTrue($semua->contains(66000.0) && $semua->contains(50000.0));
        $this->assertFalse($semua->contains(99000.0) || $semua->contains(88000.0) || $semua->contains(77000.0));

        // Kasir tetap cabangnya sendiri walau sesi menunjuk cabang lain tidak ada
        $kasir = $this->buatUser('kasir', $this->c->id);
        $res = $this->actingAs($kasir)->get($this->url(['tanggal' => '2026-10-15']));
        $this->assertSame([50000.0, 25000.0], $res->viewData('baris')->pluck('pendapatan')->all());
    }

    public function test_tanpa_izin_ditolak_dan_parameter_salah_404(): void
    {
        $this->sebagai('helper')->get($this->url())->assertForbidden();
        $this->sebagai('kasir')->get($this->url(['tanggal' => 'kemarin']))->assertNotFound();
        $this->sebagai('kasir')->get($this->url(['tahun' => 2026, 'bulan' => 13]))->assertNotFound();
    }

    public function test_struk_bisa_dibuka_dari_laporan(): void
    {
        $order = Order::where('cabang_id', $this->c->id)->whereDate('tanggal_order', '2026-11-04')->firstOrFail();
        $this->sebagai('kasir')->get(route('penjualan.struk-modal', $order))->assertOk();
    }

    public function test_export_excel_dan_pdf(): void
    {
        foreach ([['tahun' => 2026], ['tanggal' => '2026-10-15']] as $p) {
            $this->sebagai('kasir')->get($this->url($p + ['export' => 'excel']))->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $this->sebagai('admin_pusat')->get($this->url($p + ['export' => 'pdf']))->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_export_excel_kasir_tanpa_kolom_keuntungan(): void
    {
        $this->actingAs($this->buatUser('kasir', $this->c->id))->withSession(['active_cabang_id' => $this->c->id]);
        $svc = app(\App\Services\LaporanTransaksiKasirService::class);
        $posisi = ['level' => 'bulan', 'tahun' => 2026, 'bulan' => null, 'tanggal' => null, 'rentang' => null, 'asal' => null];
        $baris = $svc->rekap($posisi, $this->c->id)->map(fn ($r) => array_diff_key($r, ['hpp' => 1, 'keuntungan' => 1]));
        $total = array_diff_key($svc->total($baris), ['hpp' => 1, 'keuntungan' => 1]);

        $rows = (new \App\Exports\LaporanTransaksiKasirExport('bulan', $baris, $total, 'Tahun 2026', 'Outlet Lap', false, false))->array();
        $this->assertSame(['Bulan', 'Jumlah Transaksi', 'Pendapatan'], $rows[3]);
        $this->assertSame(['TOTAL', 3, 115000.0], $rows[3 + 11 + 1]);

        $rows = (new \App\Exports\LaporanTransaksiKasirExport('bulan', $svc->rekap($posisi, $this->c->id), $svc->total($svc->rekap($posisi, $this->c->id)), 'Tahun 2026', 'Outlet Lap', true, false))->array();
        $this->assertSame(['Bulan', 'Jumlah Transaksi', 'Pendapatan', 'Modal (HPP)', 'Keuntungan'], $rows[3]);
    }

    public function test_rentang_pendek_per_hari_lalu_turun_ke_transaksi_dan_kembali(): void
    {
        $res = $this->sebagai('kasir')->get($this->url(['dari' => '2026-10-10', 'sampai' => '2026-10-20']))->assertOk();
        $this->assertSame('hari', $res->viewData('posisi')['level']);
        $baris = $res->viewData('baris');
        $this->assertCount(11, $baris);
        $this->assertSame(2, $res->viewData('total')['transaksi']);
        $this->assertSame(75000.0, $res->viewData('total')['pendapatan']);
        $this->assertSame('10 Okt – 20 Okt 2026 (per hari)', $res->viewData('judul'));
        $this->assertNull($res->viewData('cepat'));
        $hari15 = $baris->firstWhere('kunci', '2026-10-15');
        $this->assertSame(['tanggal' => '2026-10-15', 'dari' => '2026-10-10', 'sampai' => '2026-10-20'], $hari15['params']);
        $this->assertSame(['2026-10-10', '2026-10-20'], array_map(fn ($t) => $t->toDateString(), $res->viewData('isian')));

        $res = $this->sebagai('kasir')->get($this->url($hari15['params']))->assertOk();
        $this->assertCount(2, $res->viewData('baris'));
        $jejak = $res->viewData('jejak');
        $this->assertSame(['Rentang 10 Okt – 20 Okt 2026', '15 Oktober'], array_column($jejak['jejak'], 'label'));
        $this->assertSame(['dari' => '2026-10-10', 'sampai' => '2026-10-20'], $jejak['kembali']);
    }

    public function test_rentang_panjang_per_bulan_lalu_bulan_dipotong_rentang(): void
    {
        $res = $this->sebagai('admin_pusat')->get($this->url(['dari' => '2025-12-01', 'sampai' => '2026-11-04']))->assertOk();
        $this->assertSame('bulan', $res->viewData('posisi')['level']);
        $baris = $res->viewData('baris');
        $this->assertSame(['2025-12', '2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11'], $baris->pluck('kunci')->all());
        $this->assertSame(4, $res->viewData('total')['transaksi']);
        $this->assertSame(145000.0, $res->viewData('total')['pendapatan']);

        $okt = $baris->firstWhere('kunci', '2026-10');
        $this->assertSame(['dari' => '2026-10-01', 'sampai' => '2026-10-31', 'asal_dari' => '2025-12-01', 'asal_sampai' => '2026-11-04'], $okt['params']);

        $res = $this->sebagai('admin_pusat')->get($this->url($okt['params']))->assertOk();
        $this->assertSame('hari', $res->viewData('posisi')['level']);
        $this->assertSame('Oktober 2026 (per hari)', $res->viewData('judul'));
        $jejak = $res->viewData('jejak');
        $this->assertSame(['Rentang 1 Des 2025 – 4 Nov 2026', 'Oktober 2026'], array_column($jejak['jejak'], 'label'));
        $this->assertSame(['dari' => '2025-12-01', 'sampai' => '2026-11-04'], $jejak['kembali']);

        $hari = $res->viewData('baris')->firstWhere('kunci', '2026-10-15');
        $this->assertSame('2025-12-01', $hari['params']['asal_dari']);
        $jejak = $this->sebagai('admin_pusat')->get($this->url($hari['params']))->viewData('jejak');
        $this->assertSame(['Rentang 1 Des 2025 – 4 Nov 2026', 'Oktober 2026', '15 Oktober'], array_column($jejak['jejak'], 'label'));

        // Bulan pertama ikut dipotong tanggal awal rentang
        $res = $this->sebagai('admin_pusat')->get($this->url(['dari' => '2026-06-20', 'sampai' => '2026-11-04']))->assertOk();
        $this->assertSame(['dari' => '2026-06-20', 'sampai' => '2026-06-30', 'asal_dari' => '2026-06-20', 'asal_sampai' => '2026-11-04'],
            $res->viewData('baris')->first()['params']);
    }

    public function test_rentang_terbalik_dibalik_dan_satu_hari_jadi_daftar_transaksi(): void
    {
        $res = $this->sebagai('kasir')->get($this->url(['dari' => '2026-10-20', 'sampai' => '2026-10-10']))->assertOk();
        $this->assertSame(75000.0, $res->viewData('total')['pendapatan']);

        $res = $this->sebagai('kasir')->get($this->url(['dari' => '2026-10-15', 'sampai' => '2026-10-15']))->assertOk();
        $this->assertSame('transaksi', $res->viewData('posisi')['level']);
        $this->assertCount(2, $res->viewData('baris'));

        $this->sebagai('kasir')->get($this->url(['dari' => '2026-10-15', 'sampai' => 'besok']))->assertNotFound();
        $this->sebagai('kasir')->get($this->url(['dari' => '2026-10-10', 'sampai' => '2026-10-20', 'export' => 'excel']))->assertOk();
        $this->sebagai('admin_pusat')->get($this->url(['dari' => '2025-12-01', 'sampai' => '2026-11-04', 'export' => 'pdf']))->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_grafik_baru_dan_form_rentang_tampil(): void
    {
        $this->sebagai('kasir')->get($this->url(['tahun' => 2026]))->assertOk()
            ->assertSee('id="formRentang"', false)->assertSee('name="dari"', false)->assertSee('name="sampai"', false)
            ->assertSee('lt-jenis', false)->assertSee('Tertinggi:')->assertSee('Rata-rata:')
            ->assertSee('id="grafikTransaksi"', false);
        // Periode tanpa transaksi: tidak ada grafik kosong, tampil pesan
        $this->sebagai('kasir')->get($this->url(['tahun' => 2026, 'bulan' => 3]))->assertOk()
            ->assertSee('Belum ada transaksi di periode ini.')->assertDontSee('id="grafikTransaksi"', false);
    }

    public function test_hak_akses_bawaan_per_role_dan_tampil_di_halaman_role(): void
    {
        (new \Database\Seeders\LaporanPenjualanKasirSeeder())->run();
        \Illuminate\Support\Facades\Cache::flush();

        $punya = fn (string $role, string $izin) => \Illuminate\Support\Facades\DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role', $role)->where('permissions.name', $izin)->exists();

        foreach (['kasir', 'manajer_cabang', 'admin_pusat'] as $role) {
            $this->assertTrue($punya($role, 'penjualan.laporan_transaksi.view'), "{$role} lihat laporan");
        }
        $this->assertFalse($punya('kasir', 'penjualan.laporan.keuntungan'));
        $this->assertTrue($punya('manajer_cabang', 'penjualan.laporan.keuntungan'));
        $this->assertTrue($punya('admin_pusat', 'penjualan.laporan.keuntungan'));

        // Manajer cabang: lihat keuntungan, tapi hanya cabangnya sendiri
        $manajer = $this->buatUser('manajer_cabang', $this->c->id);
        $res = $this->actingAs($manajer)->withSession(['active_cabang_id' => $this->lain->id])->get($this->url(['tanggal' => '2026-10-15']))->assertOk();
        $this->assertTrue($res->viewData('lihatUntung'));
        $this->assertSame([50000.0, 25000.0], $res->viewData('baris')->pluck('pendapatan')->all());

        // Halaman Role & Hak Akses menampilkan 4 centangan baru
        $this->actingAs($this->buatUser('owner'))->get(route('role.index'))->assertOk()
            ->assertSee('Laporan Transaksi (menu Penjualan)')
            ->assertSee('Lihat Keuntungan di Laporan Penjualan (Transaksi/Produk/Kategori)');
    }

    public function test_menu_sidebar_tampil_untuk_kasir(): void
    {
        $this->sebagai('kasir')->get(route('penjualan.index'))->assertOk()
            ->assertSee($this->url(), false)->assertSee('Laporan Transaksi');
    }
}
