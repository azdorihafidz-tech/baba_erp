<?php

namespace Tests\Feature\Baba;

use App\Models\AturanSumber;
use App\Models\AturanTagihan;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use App\Models\MemberAkun;
use App\Models\PesananOnline;
use App\Models\ProdukOnline;
use App\Models\ProdukOnlineHarga;
use App\Models\TokoOnlinePengaturan;
use App\Models\TokoOnlineRekening;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use App\Services\JurnalOnlineService;
use App\Services\KasBawaanService;
use App\Services\LabaRugiFormalService;
use App\Services\NeracaService;
use App\Services\PesananOnlineService;
use App\Services\ReturOnlineService;
use App\Services\StokMemberService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** ERP BABA Tahap 4 — keuangan Pesanan Online gudang (kas, pendapatan, HPP, retur, batal, tagihan) + toko mitra di luar keuangan BABA. */
class KeuanganOnlineTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $gudang;
    private Item $botol;
    private ProdukOnline $produk;
    private User $admin;
    private PesananOnlineService $svc;
    private MemberAkun $upline;
    private MemberAkun $pembeli;

    protected function setUp(): void
    {
        parent::setUp();
        config(['baba.member_api.secret' => 'kunci-uji-keuangan-0123456789abcdef01234', 'baba.member_app.url' => 'http://member.uji']);
        Cache::flush();
        Storage::fake('local');
        Http::fake(['member.uji/*' => Http::response(['ok' => true])]);
        TokoOnlinePengaturan::ambil()->update(['status_toko' => 'buka', 'hari_terima_otomatis' => 7, 'jam_batas_bayar' => 48, 'hari_batas_retur' => 7]);
        AturanTagihan::query()->update(['aktif' => false]);
        AturanTagihan::create(['cakupan' => 'semua', 'batas_nominal' => 5000000, 'tempo_hari' => 14, 'aktif' => true]);
        AturanSumber::query()->update(['aktif' => false]);
        AturanSumber::create(['cakupan' => 'semua', 'boleh_gudang' => true, 'boleh_upline' => true, 'aktif' => true]);

        $this->gudang = Cabang::create(['nama_cabang' => 'Gudang KU ' . uniqid(), 'kode_cabang' => 'KU' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        TokoOnlineRekening::create(['cabang_id' => $this->gudang->id, 'bank' => 'BCA', 'nomor' => '111', 'atas_nama' => 'BABA', 'aktif' => true]);
        $this->botol = Item::create(['kode_item' => 'KU-' . strtoupper(Str::random(6)), 'nama_item' => 'Amber Uang EDP 50 ml', 'tipe' => 'bahan_baku',
            'satuan' => 'pcs', 'harga_jual' => 0, 'is_active' => true, 'cabang_id' => $this->gudang->id]);
        $this->produk = ProdukOnline::create(['item_id' => $this->botol->id, 'nama_tampil' => 'Amber Uang EDP 50 ml', 'aktif' => true]);
        ProdukOnlineHarga::create(['produk_online_id' => $this->produk->id, 'jenis' => 'tetap', 'harga' => 100000]);
        $this->admin = $this->buatUser('owner');
        $this->actingAs($this->admin);
        app(StokService::class)->masuk($this->botol->id, $this->gudang->id, 30, 'uji', 'uji', null, 27000);

        $this->svc = app(PesananOnlineService::class);
        $this->upline = MemberAkun::updateOrCreate(['member_id' => 970001], ['nama' => 'Upline Uang', 'no_id' => 'B97-01', 'jabatan_id' => 27, 'jabatan' => 'SUPPLIER', 'aktif' => true]);
        $this->pembeli = MemberAkun::updateOrCreate(['member_id' => 970002], ['nama' => 'Pembeli Uang', 'no_id' => 'B97-01-01', 'jabatan_id' => 21, 'jabatan' => 'DISTRIBUTOR', 'aktif' => true, 'upline_member_id' => 970001]);
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==') . uniqid('', true);
    }

    private function pesan(int $qty, string $bayar = 'transfer', string $sumber = 'gudang'): PesananOnline
    {
        return $this->svc->buat($this->pembeli, ['sumber' => $sumber, 'cara_ambil' => 'ambil', 'cara_bayar' => $bayar, 'item' => [['produk_online_id' => $this->produk->id, 'qty' => $qty]]]);
    }

    private function bayar(PesananOnline $p): void
    {
        $this->svc->terimaBukti($this->svc->unggahBukti($p->fresh(), $this->pembeli, $this->png()), $this->admin);
    }

    private function kasTransfer(): Kas
    {
        return app(KasBawaanService::class)->pastikan($this->gudang)['transfer']->fresh();
    }

    private function trx(PesananOnline $p): \Illuminate\Support\Collection
    {
        return TransaksiKeuangan::where(fn ($q) => $q->where('nomor_transaksi', 'like', 'TRX-' . $p->nomor . '%')
            ->orWhereIn('referensi_id', \App\Models\ReturOnline::where('pesanan_online_id', $p->id)->pluck('id'))->where('referensi_type', 'retur_online'))->get();
    }

    private function lr(): array
    {
        return app(LabaRugiFormalService::class)->hitungLabaRugi(today(), today(), $this->gudang->id);
    }

    public function test_transfer_lunas_kas_pendapatan_hpp_dan_retur(): void
    {
        $selisihAwal = app(NeracaService::class)->hitungNeraca(today(), $this->gudang->id)['selisih'];
        $p = $this->pesan(3);
        $this->assertCount(0, $this->trx($p), 'belum dibayar = belum ada pendapatan');
        $this->bayar($p);
        $t = $this->trx($p);
        $this->assertCount(1, $t);
        $this->assertSame(['pemasukan', 'penjualan', 300000.0, $this->gudang->id], [$t[0]->tipe->value, $t[0]->kategori->value, (float) $t[0]->jumlah, (int) $t[0]->cabang_id]);
        $this->assertSame(300000.0, (float) $this->kasTransfer()->saldo_sekarang);
        $this->assertSame((int) $this->kasTransfer()->id, (int) $t[0]->kas_id);

        // dipanggil ulang → tidak tercatat dua kali
        app(JurnalOnlineService::class)->bayarDiterima($p->fresh(), $p->bayar()->first(), $this->admin->id);
        $this->assertCount(1, $this->trx($p));

        $this->svc->keluarkan($p->fresh(), $this->admin);
        $this->assertSame(81000.0, app(JurnalOnlineService::class)->hpp(today(), today(), $this->gudang->id));
        $lr = $this->lr();
        $this->assertSame(300000.0, (float) $lr['pendapatan']['total']);
        $this->assertSame(81000.0, (float) $lr['hpp']['total']);
        $this->assertSame(219000.0, (float) $lr['laba_kotor']);
        $this->assertEqualsWithDelta($selisihAwal, app(NeracaService::class)->hitungNeraca(today(), $this->gudang->id)['selisih'], 1, 'Neraca tetap seimbang');

        // retur 1 botol, lunas → dana dikembalikan lewat transfer
        $this->svc->terimaMember($p->fresh(), $this->pembeli);
        $r = app(ReturOnlineService::class)->ajukan($p->fresh(), $this->pembeli, ['alasan' => 'Botol bocor', 'item' => [['id' => $p->item->first()->id, 'qty' => 1]]]);
        app(ReturOnlineService::class)->setujui($r, $this->admin, 'transfer');
        $this->assertSame(200000.0, (float) $this->kasTransfer()->saldo_sekarang);
        $this->assertSame(-100000.0, (float) TransaksiKeuangan::where('referensi_type', 'retur_online')->where('referensi_id', $r->id)->value('jumlah'));
        $lr = $this->lr();
        $this->assertSame(200000.0, (float) $lr['pendapatan']['total']);
        $this->assertSame(54000.0, (float) $lr['hpp']['total'], 'HPP berkurang sebesar barang yang kembali ke gudang');
        $this->assertEqualsWithDelta($selisihAwal, app(NeracaService::class)->hitungNeraca(today(), $this->gudang->id)['selisih'], 1, 'Neraca tetap seimbang setelah retur');
    }

    public function test_retur_tunai_memakai_kas_tunai(): void
    {
        $p = $this->pesan(2);
        $this->bayar($p);
        $this->svc->keluarkan($p->fresh(), $this->admin);
        $this->svc->terimaMember($p->fresh(), $this->pembeli);
        $r = app(ReturOnlineService::class)->ajukan($p->fresh(), $this->pembeli, ['alasan' => 'Salah aroma', 'item' => [['id' => $p->item->first()->id, 'qty' => 2]]]);
        app(ReturOnlineService::class)->setujui($r, $this->admin, 'tunai');
        $kasTunai = app(KasBawaanService::class)->pastikan($this->gudang)['tunai']->fresh();
        $this->assertSame(-200000.0, (float) $kasTunai->saldo_sekarang);
        $this->assertSame(200000.0, (float) $this->kasTransfer()->saldo_sekarang);
        $this->assertSame(0.0, (float) $this->lr()['pendapatan']['total']);
    }

    public function test_batal_setelah_lunas_mengembalikan_dana(): void
    {
        $p = $this->pesan(1);
        $this->bayar($p);
        $this->svc->batalAdmin($p->fresh(), 'Stok rusak, dibatalkan', $this->admin);
        $this->assertSame([100000.0, -100000.0], $this->trx($p)->sortByDesc('jumlah')->pluck('jumlah')->map(fn ($v) => (float) $v)->values()->all());
        $this->assertSame(0.0, (float) $this->kasTransfer()->saldo_sekarang);
        $this->assertSame(0.0, (float) $this->lr()['pendapatan']['total']);
    }

    public function test_tagihan_jadi_piutang_lalu_pendapatan_saat_dibayar(): void
    {
        $j = app(JurnalOnlineService::class);
        $p = $this->pesan(2, 'tagihan');
        $this->assertSame(0.0, $j->piutang($this->gudang->id), 'belum keluar = belum piutang');
        $this->svc->keluarkan($p->fresh(), $this->admin);
        $this->assertSame(200000.0, $j->piutang($this->gudang->id));
        $this->assertCount(0, $this->trx($p));
        $this->assertSame(200000.0, (float) app(NeracaService::class)->hitungNeraca(today(), $this->gudang->id)['aset']['lancar']['tagihan_member']);
        $this->bayar($p);
        $this->assertSame(0.0, $j->piutang($this->gudang->id));
        $this->assertSame(200000.0, (float) $this->trx($p)->sum('jumlah'));
        $this->assertSame(200000.0, (float) $this->kasTransfer()->saldo_sekarang);
    }

    public function test_pesanan_ke_upline_tidak_masuk_keuangan_baba(): void
    {
        app(StokMemberService::class)->masuk(970001, $this->botol->id, 5, 27000, 'beli', 'uji', 1, 'uji');
        TokoOnlineRekening::create(['member_id' => 970001, 'bank' => 'BRI', 'nomor' => '222', 'atas_nama' => 'Upline', 'aktif' => true]);
        $p = $this->pesan(1, 'transfer', 'upline');
        $this->svc->terimaBukti($this->svc->unggahBukti($p->fresh(), $this->pembeli, $this->png()), $this->upline);
        $this->svc->keluarkan($p->fresh(), $this->upline);
        $this->assertCount(0, $this->trx($p));
        $this->assertSame(0.0, app(JurnalOnlineService::class)->hpp(today(), today(), $this->gudang->id));
        $this->assertSame(0.0, (float) $this->lr()['hpp']['total']);
        $this->assertSame(0.0, (float) $this->kasTransfer()->saldo_sekarang);
    }

    public function test_toko_mitra_di_luar_keuangan_gabungan(): void
    {
        $lrSvc = app(LabaRugiFormalService::class);
        $neraca = app(NeracaService::class);
        foreach (['kemitraan', 'toko_member'] as $jenis) {
            $mitra = Cabang::create(['nama_cabang' => "Toko {$jenis} KU " . uniqid(), 'kode_cabang' => 'MK' . rand(10000, 99999), 'tipe' => 'cabang', 'jenis_toko' => $jenis, 'is_active' => true]);
            $kas = app(KasBawaanService::class)->pastikan($mitra)['tunai'];
            $pendapatanAwal = (float) $lrSvc->hitungLabaRugi(today(), today())['pendapatan']['total'];
            $kasAwal = (float) $neraca->hitungNeraca(today())['aset']['lancar']['kas_setara'];
            $kas->increment('saldo_sekarang', 777000);
            TransaksiKeuangan::create(['cabang_id' => $mitra->id, 'kas_id' => $kas->id, 'nomor_transaksi' => 'TRX-MITRA-' . uniqid(), 'tanggal_transaksi' => today(),
                'tipe' => 'pemasukan', 'kategori' => 'penjualan', 'keterangan' => 'jual mitra', 'jumlah' => 777000, 'created_by' => $this->admin->id]);
            $this->assertSame(777000.0, (float) $lrSvc->hitungLabaRugi(today(), today(), $mitra->id)['pendapatan']['total'], "laporan {$jenis} sendiri tetap ada");
            $this->assertSame($pendapatanAwal, (float) $lrSvc->hitungLabaRugi(today(), today())['pendapatan']['total'], "{$jenis} tidak masuk laba rugi gabungan");
            $this->assertSame($kasAwal, (float) $neraca->hitungNeraca(today())['aset']['lancar']['kas_setara'], "{$jenis} tidak masuk kas neraca gabungan");
        }
        // toko BABA tetap masuk gabungan
        $baba = Cabang::create(['nama_cabang' => 'Toko BABA KU ' . uniqid(), 'kode_cabang' => 'BK' . rand(10000, 99999), 'tipe' => 'cabang', 'jenis_toko' => 'baba', 'is_active' => true]);
        $kasBaba = app(KasBawaanService::class)->pastikan($baba)['tunai'];
        $kasAwal = (float) $neraca->hitungNeraca(today())['aset']['lancar']['kas_setara'];
        $kasBaba->increment('saldo_sekarang', 5000);
        $this->assertSame($kasAwal + 5000, (float) $neraca->hitungNeraca(today())['aset']['lancar']['kas_setara']);
        $this->get(route('laporan.neraca.index'))->assertOk()->assertSee('Tagihan member');
        $this->get(route('laporan.laba-rugi-formal.index'))->assertOk();
    }
}
