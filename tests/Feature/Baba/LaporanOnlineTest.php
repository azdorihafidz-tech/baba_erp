<?php

namespace Tests\Feature\Baba;

use App\Models\AturanSumber;
use App\Models\AturanTagihan;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\MemberAkun;
use App\Models\PesananOnline;
use App\Models\ProdukOnline;
use App\Models\ProdukOnlineHarga;
use App\Models\TokoOnlinePengaturan;
use App\Models\TokoOnlineRekening;
use App\Models\User;
use App\Services\LaporanOnlineService;
use App\Services\PesananOnlineService;
use App\Services\ReturOnlineService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** ERP BABA Tahap 4 — Laporan Belanja Online: per produk / cabang / jabatan / tim, RO per member & tim, tagihan, halaman & Excel. */
class LaporanOnlineTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $gudang;
    private ProdukOnline $a;
    private ProdukOnline $b;
    private User $admin;
    private PesananOnlineService $svc;
    private MemberAkun $dist;
    private MemberAkun $sell;
    private LaporanOnlineService $lap;

    protected function setUp(): void
    {
        parent::setUp();
        config(['baba.member_api.secret' => 'kunci-uji-laporan-0123456789abcdef012345', 'baba.member_app.url' => 'http://member.uji']);
        Cache::flush();
        Storage::fake('local');
        // jalur struktur dari aplikasi member: 980001 Distributor di bawah MVO M77, 980002 Reseller di bawah 980001 (MVO sama)
        Http::fake([
            'member.uji/api/erp/jalur' => Http::response(['ok' => true, 'data' => [
                '980001' => ['diri' => ['id' => 980001, 'no_id' => 'B77-01-01', 'nama' => 'Dist Lap', 'jabatan' => 'DISTRIBUTOR', 'level' => 6],
                    'jalur' => ['1' => ['id' => 7701, 'no_id' => 'M77', 'nama' => 'MVO Tujuh', 'jabatan' => 'MVO']]],
                '980002' => ['diri' => ['id' => 980002, 'no_id' => 'B77-01-01-01', 'nama' => 'Sell Lap', 'jabatan' => 'RESELLER', 'level' => 7],
                    'jalur' => ['1' => ['id' => 7701, 'no_id' => 'M77', 'nama' => 'MVO Tujuh', 'jabatan' => 'MVO'], '6' => ['id' => 980001, 'no_id' => 'B77-01-01', 'nama' => 'Dist Lap', 'jabatan' => 'DISTRIBUTOR']]],
            ]]),
            'member.uji/*' => Http::response(['ok' => true]),
        ]);
        TokoOnlinePengaturan::ambil()->update(['status_toko' => 'buka', 'hari_terima_otomatis' => 7, 'jam_batas_bayar' => 48, 'hari_batas_retur' => 7]);
        AturanTagihan::query()->update(['aktif' => false]);
        AturanTagihan::create(['cakupan' => 'semua', 'batas_nominal' => 5000000, 'tempo_hari' => 14, 'aktif' => true]);
        AturanSumber::query()->update(['aktif' => false]);
        AturanSumber::create(['cakupan' => 'semua', 'boleh_gudang' => true, 'boleh_upline' => false, 'aktif' => true]);

        $this->gudang = Cabang::create(['nama_cabang' => 'Gudang LO ' . uniqid(), 'kode_cabang' => 'LO' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        TokoOnlineRekening::create(['cabang_id' => $this->gudang->id, 'bank' => 'BCA', 'nomor' => '111', 'atas_nama' => 'BABA', 'aktif' => true]);
        $this->admin = $this->buatUser('owner');
        $this->actingAs($this->admin);
        [$this->a, $this->b] = [$this->produk('Lapor Satu EDP 50 ml', 100000, 27000, 'Pria'), $this->produk('Lapor Dua EDP 50 ml', 200000, 50000, 'Wanita')];
        $this->svc = app(PesananOnlineService::class);
        $this->lap = app(LaporanOnlineService::class);
        $this->dist = MemberAkun::updateOrCreate(['member_id' => 980001], ['nama' => 'Dist Lap', 'no_id' => 'B77-01-01', 'no_member' => 'BP-80001', 'jabatan_id' => 21, 'jabatan' => 'DISTRIBUTOR', 'cabang' => 'ACEH', 'aktif' => true]);
        $this->sell = MemberAkun::updateOrCreate(['member_id' => 980002], ['nama' => 'Sell Lap', 'no_id' => 'B77-01-01-01', 'no_member' => 'BP-80002', 'jabatan_id' => 24, 'jabatan' => 'RESELLER', 'cabang' => 'BALI', 'aktif' => true, 'upline_member_id' => 980001]);
    }

    private function produk(string $nama, int $harga, int $modal, string $kategori): ProdukOnline
    {
        $item = Item::create(['kode_item' => 'LO-' . strtoupper(Str::random(6)), 'nama_item' => $nama, 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'harga_jual' => 0, 'is_active' => true, 'cabang_id' => $this->gudang->id]);
        app(StokService::class)->masuk($item->id, $this->gudang->id, 50, 'uji', 'uji', null, $modal);
        $p = ProdukOnline::create(['item_id' => $item->id, 'nama_tampil' => $nama, 'kategori' => $kategori, 'aktif' => true]);
        ProdukOnlineHarga::create(['produk_online_id' => $p->id, 'jenis' => 'tetap', 'harga' => $harga]);

        return $p;
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==') . uniqid('', true);
    }

    private function terjual(MemberAkun $m, array $item, string $bayar = 'transfer'): PesananOnline
    {
        $p = $this->svc->buat($m, ['sumber' => 'gudang', 'cara_ambil' => 'ambil', 'cara_bayar' => $bayar, 'item' => $item]);
        if ($bayar === 'transfer') {
            $this->svc->terimaBukti($this->svc->unggahBukti($p, $m, $this->png()), $this->admin);
        }
        $this->svc->keluarkan($p->fresh(), $this->admin);

        return $p->fresh('item');
    }

    private function siapkanPenjualan(): void
    {
        $p1 = $this->terjual($this->dist, [['produk_online_id' => $this->a->id, 'qty' => 3], ['produk_online_id' => $this->b->id, 'qty' => 1]]);
        $this->terjual($this->sell, [['produk_online_id' => $this->a->id, 'qty' => 2]], 'tagihan');
        // retur 1 botol A dari pesanan Distributor
        $this->svc->terimaMember($p1->fresh(), $this->dist);
        $r = app(ReturOnlineService::class)->ajukan($p1->fresh(), $this->dist, ['alasan' => 'Botol bocor', 'item' => [['id' => $p1->item->firstWhere('produk_online_id', $this->a->id)->id, 'qty' => 1]]]);
        app(ReturOnlineService::class)->setujui($r, $this->admin, 'transfer');
        // pesanan yang belum keluar tidak dihitung
        $this->svc->buat($this->dist, ['sumber' => 'gudang', 'cara_ambil' => 'ambil', 'cara_bayar' => 'transfer', 'item' => [['produk_online_id' => $this->a->id, 'qty' => 9]]]);
    }

    public function test_penjualan_per_produk_cabang_jabatan_tim(): void
    {
        $this->siapkanPenjualan();
        $hari = today();
        $produk = $this->lap->perProduk($hari, $hari, $this->gudang->id)->keyBy('nama');
        // A: 3 − 1 retur + 2 = 4 botol × 100.000; HPP 4 × 27.000
        $this->assertSame([4, 400000.0, 108000.0, 292000.0], [$produk['Lapor Satu EDP 50 ml']['botol'], $produk['Lapor Satu EDP 50 ml']['omzet'], $produk['Lapor Satu EDP 50 ml']['hpp'], $produk['Lapor Satu EDP 50 ml']['laba']]);
        $this->assertSame([1, 200000.0, 50000.0], [$produk['Lapor Dua EDP 50 ml']['botol'], $produk['Lapor Dua EDP 50 ml']['omzet'], $produk['Lapor Dua EDP 50 ml']['hpp']]);
        $this->assertSame('Lapor Satu EDP 50 ml', $produk->keys()->first(), 'urut omzet terbesar');
        $r = $this->lap->ringkas($hari, $hari, $this->gudang->id);
        $this->assertSame([2, 5, 600000.0, 158000.0, 442000.0, 2], [$r['pesanan'], $r['botol'], $r['omzet'], $r['hpp'], $r['laba'], $r['pembeli']]);

        $cabang = $this->lap->perAkun($hari, $hari, 'cabang', $this->gudang->id)->keyBy('nama');
        $this->assertSame([400000.0, 200000.0], [$cabang['ACEH']['omzet'], $cabang['BALI']['omzet']]);
        $jabatan = $this->lap->perAkun($hari, $hari, 'jabatan', $this->gudang->id)->keyBy('nama');
        $this->assertSame([3, 2], [$jabatan['DISTRIBUTOR']['botol'], $jabatan['RESELLER']['botol']]);

        $mvo = $this->lap->perTim($hari, $hari, 1, $this->gudang->id);
        $this->assertCount(1, $mvo);
        $this->assertSame(['M77 — MVO Tujuh', 5, 600000.0], [$mvo[0]['nama'], $mvo[0]['botol'], $mvo[0]['omzet']]);
        $dist = $this->lap->perTim($hari, $hari, 6, $this->gudang->id)->keyBy('nama');
        $this->assertSame(600000.0, $dist['B77-01-01 — Dist Lap']['omzet'], 'Distributor = dirinya sendiri + Reseller di bawahnya');
        $gm = $this->lap->perTim($hari, $hari, 4, $this->gudang->id);
        $this->assertStringStartsWith('Tanpa GM', $gm[0]['nama']);

        // periode lain = kosong
        $this->assertCount(0, $this->lap->perProduk($hari->copy()->subDays(10), $hari->copy()->subDays(9), $this->gudang->id));
    }

    public function test_ro_per_member_tim_dan_tagihan(): void
    {
        $hari = today()->toDateString();
        foreach ([[980001, 5, 'terkirim'], [980001, -1, 'terkirim'], [980002, 3, 'menunggu']] as $n => [$id, $qty, $status]) {
            DB::table('ro_catatan')->insert(['ref' => 'ERP-RO-UJI-' . uniqid() . $n, 'member_id' => $id, 'qty' => $qty, 'jenis' => $qty > 0 ? 'pesanan' : 'retur',
                'tanggal' => $hari, 'mode' => 'lunas', 'perlu_approve' => false, 'status' => $status, 'percobaan' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
        $ro = $this->lap->roPerMember(today(), today())->keyBy('member_id');
        $this->assertSame([5, 1, 4, 0], [$ro[980001]['tambah'], $ro[980001]['kurang'], $ro[980001]['bersih'], $ro[980001]['tertunda']]);
        $this->assertSame([3, 1, 'BP-80002'], [$ro[980002]['bersih'], $ro[980002]['tertunda'], $ro[980002]['no_member']]);
        $tim = $this->lap->roPerTim(today(), today(), 1);
        $this->assertSame(['M77 — MVO Tujuh', 2, 7], [$tim[0]['nama'], $tim[0]['anggota'], $tim[0]['bersih']]);

        $p = $this->terjual($this->sell, [['produk_online_id' => $this->a->id, 'qty' => 2]], 'tagihan');
        PesananOnline::whereKey($p->id)->update(['jatuh_tempo' => today()->subDays(3)]);
        $t = $this->lap->tagihan($this->gudang->id);
        $this->assertCount(1, $t);
        $this->assertSame([$p->nomor, 200000.0, 3, true], [$t[0]['nomor'], (float) $t[0]['sisa'], $t[0]['telat'], $t[0]['barang_keluar']]);
        $this->svc->terimaBukti($this->svc->unggahBukti($p->fresh(), $this->sell, $this->png()), $this->admin);
        $this->assertCount(0, $this->lap->tagihan($this->gudang->id), 'lunas = hilang dari daftar tagihan');
    }

    public function test_halaman_excel_izin_dan_aplikasi_member_mati(): void
    {
        $this->siapkanPenjualan();
        foreach (array_keys(\App\Http\Controllers\LaporanOnlineController::JENIS) as $jenis) {
            $this->get(route('toko-online.laporan', ['jenis' => $jenis, 'gudang' => $this->gudang->id]))->assertOk()->assertSee('Laporan Belanja Online');
        }
        $this->get(route('toko-online.laporan', ['jenis' => 'produk', 'gudang' => $this->gudang->id]))->assertSee('Lapor Satu EDP 50 ml')->assertSee('Rp 292.000');
        $this->get(route('toko-online.laporan', ['jenis' => 'tagihan', 'gudang' => $this->gudang->id]))->assertSee('Rp 200.000');
        // tanggal salah / terbalik → bawaan bulan ini, tidak error
        $this->get(route('toko-online.laporan', ['dari' => 'kemarin', 'sampai' => '2026-01-01']))->assertOk();
        $this->get(route('toko-online.laporan', ['dari' => '2026-12-01', 'sampai' => '2026-01-01', 'jenis' => 'aneh']))->assertOk()->assertSee('Per Produk');
        $x = $this->get(route('toko-online.laporan', ['jenis' => 'produk', 'ekspor' => 'excel', 'gudang' => $this->gudang->id]));
        $x->assertOk();
        $this->assertStringContainsString('spreadsheetml', $x->headers->get('content-type'));
        $this->actingAs($this->buatUser('kasir'))->get(route('toko-online.laporan'))->assertForbidden();
        $this->actingAs($this->buatUser('admin_gudang'))->get(route('toko-online.laporan'))->assertForbidden();
        $this->actingAs($this->buatUser('admin_pusat'))->get(route('toko-online.laporan'))->assertOk();

        // aplikasi member tidak bisa dihubungi → laporan per tim menampilkan pesan, bukan error 500
        config(['baba.member_app.url' => 'http://mati.uji']);
        Http::fake(['mati.uji/*' => Http::response('mati', 500)]);
        $this->actingAs($this->admin)->get(route('toko-online.laporan', ['jenis' => 'tim']))->assertOk()->assertSee('alert-warning', false);
    }
}
