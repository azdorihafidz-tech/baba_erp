<?php

namespace Tests\Feature\Baba;

use App\Exceptions\PesananException;
use App\Http\Middleware\VerifikasiAplikasiMember;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\MemberAkun;
use App\Models\PenjualanMember;
use App\Models\ProdukOnline;
use App\Models\ProdukOnlineHarga;
use App\Models\RoCatatan;
use App\Models\StokMember;
use App\Services\KasirMemberService;
use App\Services\StokMemberService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/** ERP BABA Tahap 3 — kasir member (Toko Saya › Kasir) + laporan transaksi/produk/kategori. */
class KasirMemberTest extends TestCase
{
    use DatabaseTransactions;

    private const KUNCI = 'kunci-uji-kasir-member-0123456789abcdef';

    private Item $a;
    private Item $b;
    private MemberAkun $m;
    private KasirMemberService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        config(['baba.member_api.secret' => self::KUNCI]);
        Cache::flush();
        $g = Cabang::create(['nama_cabang' => 'Gudang KM ' . uniqid(), 'kode_cabang' => 'KM' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        $mk = fn ($nama) => Item::create(['cabang_id' => $g->id, 'kode_item' => 'KMU-' . strtoupper(Str::random(6)), 'nama_item' => $nama, 'tipe' => 'bahan_baku',
            'jenis' => 'bahan_baku', 'satuan' => 'pcs', 'track_stok' => true, 'is_active' => true]);
        $this->a = $mk('Oud Kasir EDP 50 ml');
        $this->b = $mk('Rose Kasir EDP 30 ml');
        $pa = ProdukOnline::create(['item_id' => $this->a->id, 'nama_tampil' => 'Oud Kasir EDP 50 ml', 'kategori' => 'Pria', 'aktif' => true]);
        ProdukOnlineHarga::create(['produk_online_id' => $pa->id, 'jenis' => 'tetap', 'harga' => 150000]);
        $this->m = MemberAkun::updateOrCreate(['member_id' => 930001], ['nama' => 'Kasir Uji', 'no_id' => 'B97-01', 'jabatan_id' => 21, 'jabatan' => 'DISTRIBUTOR', 'aktif' => true]);
        $sm = app(StokMemberService::class);
        $sm->masuk(930001, $this->a->id, 10, 100000, 'beli');
        $sm->masuk(930001, $this->b->id, 4, 60000, 'beli');
        $this->svc = app(KasirMemberService::class);
    }

    private function gagal(callable $fn, string $potongan): void
    {
        try {
            $fn();
            $this->fail("Seharusnya ditolak: {$potongan}");
        } catch (PesananException $e) {
            $this->assertStringContainsString($potongan, $e->getMessage());
        }
    }

    public function test_jual_stok_kembalian_harga_tersimpan_dan_tanpa_ro(): void
    {
        $p = collect($this->svc->produk($this->m))->keyBy('item_id');
        $this->assertSame([10, 150000.0], [$p[$this->a->id]['stok'], $p[$this->a->id]['harga']]); // harga tetap produk online
        $this->assertSame(60000.0, $p[$this->b->id]['harga']);                                       // tanpa produk online → modal

        $j = $this->svc->jual($this->m, ['bayar' => 'tunai', 'diterima' => 600000, 'pelanggan_nama' => 'Bu Sari', 'simpan_harga' => true,
            'item' => [['item_id' => $this->a->id, 'qty' => 3, 'harga' => 160000], ['item_id' => $this->b->id, 'qty' => 1, 'harga' => 90000]]]);
        $s = $this->svc->struk($j);
        $this->assertSame([570000.0, 30000.0, 'Tunai', true], [$s['total'], $s['kembali'], $s['bayar_label'], $s['boleh_batal']]);
        $this->assertEquals(360000, (float) $j->modal);
        $this->assertSame(7, app(StokMemberService::class)->qty(930001, $this->a->id));
        $this->assertSame('kasir', \App\Models\StokMemberMutasi::where('member_id', 930001)->orderByDesc('id')->value('jenis'));
        $this->assertSame(160000.0, collect($this->svc->produk($this->m))->firstWhere('item_id', $this->a->id)['harga']); // harga pilihan diingat
        $this->assertSame(0, RoCatatan::where('member_id', 930001)->count()); // penjualan kasir tidak menambah RO
    }

    public function test_validasi_dan_batal(): void
    {
        $this->gagal(fn () => $this->svc->jual($this->m, ['bayar' => 'tunai', 'item' => []]), 'kosong');
        $this->gagal(fn () => $this->svc->jual($this->m, ['bayar' => 'kartu', 'item' => [['item_id' => $this->a->id, 'qty' => 1, 'harga' => 1]]]), 'cara bayar');
        $this->gagal(fn () => $this->svc->jual($this->m, ['bayar' => 'tunai', 'item' => [['item_id' => $this->a->id, 'qty' => 11, 'harga' => 1]]]), 'tinggal 10');
        $this->gagal(fn () => $this->svc->jual($this->m, ['bayar' => 'tunai', 'item' => [['item_id' => $this->a->id, 'qty' => 1.5, 'harga' => 1]]]), 'angka bulat');
        $this->gagal(fn () => $this->svc->jual($this->m, ['bayar' => 'tunai', 'diterima' => 1000, 'item' => [['item_id' => $this->a->id, 'qty' => 1, 'harga' => 150000]]]), 'kurang dari total');
        $lain = MemberAkun::updateOrCreate(['member_id' => 930002], ['nama' => 'Lain', 'jabatan_id' => 24, 'aktif' => true]);
        $this->gagal(fn () => $this->svc->jual($lain, ['bayar' => 'qris', 'item' => [['item_id' => $this->a->id, 'qty' => 1, 'harga' => 1]]]), 'tidak ada di stok');

        $j = $this->svc->jual($this->m, ['bayar' => 'qris', 'item' => [['item_id' => $this->a->id, 'qty' => 2, 'harga' => 150000]]]);
        $this->gagal(fn () => $this->svc->batal($j, $lain, null), 'tidak ditemukan');
        $this->svc->batal($j, $this->m, 'salah input');
        $this->assertSame(['batal', 10], [$j->fresh()->status, app(StokMemberService::class)->qty(930001, $this->a->id)]);
        $this->gagal(fn () => $this->svc->batal($j->fresh(), $this->m, null), 'sudah dibatalkan');
        $lama = $this->svc->jual($this->m, ['bayar' => 'tunai', 'item' => [['item_id' => $this->a->id, 'qty' => 1, 'harga' => 150000]]]);
        PenjualanMember::whereKey($lama->id)->update(['created_at' => now()->subDay()]);
        $this->gagal(fn () => $this->svc->batal($lama->fresh(), $this->m, null), 'hari ini');
    }

    public function test_laporan_transaksi_produk_kategori(): void
    {
        $this->svc->jual($this->m, ['bayar' => 'tunai', 'item' => [['item_id' => $this->a->id, 'qty' => 2, 'harga' => 150000], ['item_id' => $this->b->id, 'qty' => 1, 'harga' => 80000]]]);
        $this->svc->jual($this->m, ['bayar' => 'transfer', 'item' => [['item_id' => $this->a->id, 'qty' => 1, 'harga' => 150000]]]);
        $batal = $this->svc->jual($this->m, ['bayar' => 'tunai', 'item' => [['item_id' => $this->a->id, 'qty' => 1, 'harga' => 150000]]]);
        $this->svc->batal($batal, $this->m, null);

        $t = $this->svc->laporan($this->m, 'transaksi', null, null);
        $this->assertSame([530000.0, 2, 4], [$t['ringkas']['penjualan'], $t['ringkas']['transaksi'], $t['ringkas']['botol_terjual']]);
        $this->assertEquals(530000 - 360000, $t['ringkas']['laba']);
        $this->assertCount(2, $t['data']); // transaksi batal tidak dihitung
        $p = collect($this->svc->laporan($this->m, 'produk', null, null)['data'])->keyBy('item_id');
        $this->assertSame([3, 450000.0, 7], [$p[$this->a->id]['terjual'], (float) $p[$this->a->id]['omzet'], $p[$this->a->id]['stok']]);
        $k = collect($this->svc->laporan($this->m, 'kategori', null, null)['data'])->keyBy('kategori');
        $this->assertSame([3, 1], [$k['Pria']['terjual'], $k['Tanpa kategori']['terjual']]);
        $this->gagal(fn () => $this->svc->laporan($this->m, 'transaksi', '2026-12-31', '2026-01-01'), 'tidak valid');
        $this->assertCount(0, $this->svc->laporan($this->m, 'transaksi', '2020-01-01', '2020-01-31')['data']);
    }

    private function api(string $method, string $path, int $member, ?array $data = null)
    {
        [$pathSaja, $query] = array_pad(explode('?', $path, 2), 2, '');
        $profil = base64_encode(json_encode(['id' => $member, 'nama' => 'M' . $member, 'jabatan_id' => 21, 'aktif' => true]));
        $body = $data === null ? '' : json_encode($data);
        $waktu = (string) time();
        $nonce = Str::random(32);
        $tanda = hash_hmac('sha256', VerifikasiAplikasiMember::kanonik($method, $pathSaja, $query, $waktu, $nonce, (string) $member, $profil, $body), self::KUNCI);

        return $this->call($method, $path, [], [], [], ['HTTP_X-Baba-Waktu' => $waktu, 'HTTP_X-Baba-Nonce' => $nonce, 'HTTP_X-Baba-Member' => (string) $member,
            'HTTP_X-Baba-Profil' => $profil, 'HTTP_X-Baba-Tanda' => $tanda, 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], $body === '' ? null : $body);
    }

    public function test_api_kasir_dan_laporan(): void
    {
        $this->assertCount(2, $this->api('GET', '/api/member/kasir/produk', 930001)->assertOk()->json('data'));
        $this->assertCount(0, $this->api('GET', '/api/member/kasir/produk', 930002)->assertOk()->json('data'));
        $j = $this->api('POST', '/api/member/kasir', 930001, ['bayar' => 'transfer', 'item' => [['item_id' => $this->a->id, 'qty' => 2, 'harga' => 155000]]])->assertStatus(201);
        $nomor = $j->json('data.nomor');
        $this->assertMatchesRegularExpression('/^KSR-\d{6}-\d{4}$/', $nomor);
        $this->api('POST', '/api/member/kasir', 930001, ['bayar' => 'tunai', 'item' => [['item_id' => $this->a->id, 'qty' => 99, 'harga' => 1]]])->assertStatus(422);
        $this->assertSame($nomor, $this->api('GET', '/api/member/kasir', 930001)->json('data.0.nomor'));
        $this->api('GET', "/api/member/kasir/{$nomor}", 930002)->assertStatus(404);
        $this->assertSame(310000.0, (float) $this->api('GET', "/api/member/kasir/{$nomor}", 930001)->json('data.total'));
        $this->assertSame(310000.0, (float) $this->api('GET', '/api/member/laporan?jenis=transaksi', 930001)->json('ringkas.penjualan'));
        $this->assertSame('produk', $this->api('GET', '/api/member/laporan?jenis=produk', 930001)->json('jenis'));
        $this->api('POST', "/api/member/kasir/{$nomor}/batal", 930001, ['alasan' => 'uji'])->assertOk()->assertJsonPath('data.status', 'batal');
        $this->assertSame(10, StokMember::where('member_id', 930001)->where('item_id', $this->a->id)->value('qty'));
    }
}
