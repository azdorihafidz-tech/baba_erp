<?php

namespace Tests\Feature\Baba;

use App\Exceptions\PesananException;
use App\Http\Middleware\VerifikasiAplikasiMember;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\MemberAkun;
use App\Models\OpnameMember;
use App\Models\RoCatatan;
use App\Models\StokMember;
use App\Models\StokMemberMutasi;
use App\Models\TransaksiKeuangan;
use App\Services\KasirMemberService;
use App\Services\OpnameMemberService;
use App\Services\StokMemberService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** ERP BABA Tahap 7f — stok opname member: hanya mengurangi, penyebab wajib, tanpa RO & keuangan BABA, laporan Toko Saya, rekap ERP. */
class OpnameMemberTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private const KUNCI = 'kunci-uji-opname-member-0123456789abcdef';

    private Item $a;
    private Item $b;
    private MemberAkun $m;
    private OpnameMemberService $svc;
    private string $png;

    protected function setUp(): void
    {
        parent::setUp();
        config(['baba.member_api.secret' => self::KUNCI]);
        Cache::flush();
        Storage::fake('local');
        $g = Cabang::create(['nama_cabang' => 'Gudang OPM ' . uniqid(), 'kode_cabang' => 'OM' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        $mk = fn ($nama) => Item::create(['cabang_id' => $g->id, 'kode_item' => 'OPM-' . strtoupper(Str::random(6)), 'nama_item' => $nama, 'tipe' => 'bahan_baku',
            'jenis' => 'bahan_baku', 'satuan' => 'pcs', 'track_stok' => true, 'is_active' => true]);
        $this->a = $mk('Oud Opname EDP 50 ml');
        $this->b = $mk('Rose Opname EDP 30 ml');
        $this->m = MemberAkun::updateOrCreate(['member_id' => 940001], ['nama' => 'Opname Uji', 'no_id' => 'B98-01', 'jabatan_id' => 21, 'jabatan' => 'DISTRIBUTOR', 'aktif' => true]);
        $sm = app(StokMemberService::class);
        $sm->masuk(940001, $this->a->id, 10, 100000, 'beli');
        $sm->masuk(940001, $this->b->id, 4, 60000, 'beli');
        $this->svc = app(OpnameMemberService::class);
        $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
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

    private function stok(Item $i): int
    {
        return (int) StokMember::where('member_id', 940001)->where('item_id', $i->id)->value('qty');
    }

    public function test_hanya_mengurangi_penyebab_wajib_tanpa_ro_dan_keuangan(): void
    {
        $ro = RoCatatan::count();
        $trx = TransaksiKeuangan::count();
        $this->gagal(fn () => $this->svc->simpan($this->m, ['item' => []]), 'Isi jumlah fisik');
        $this->gagal(fn () => $this->svc->simpan($this->m, ['item' => [['item_id' => $this->a->id, 'fisik' => 11, 'penyebab' => 'hilang']]]), 'lebih banyak dari stok 10');
        $this->gagal(fn () => $this->svc->simpan($this->m, ['item' => [['item_id' => $this->a->id, 'fisik' => 8]]]), 'pilih penyebab');
        $this->gagal(fn () => $this->svc->simpan($this->m, ['item' => [['item_id' => $this->a->id, 'fisik' => 7.5, 'penyebab' => 'hilang']]]), 'bilangan bulat');
        $this->gagal(fn () => $this->svc->simpan($this->m, ['item' => [['item_id' => $this->a->id, 'fisik' => 10]]]), 'tidak ada yang perlu dicatat');
        $this->gagal(fn () => $this->svc->simpan($this->m, ['item' => [['item_id' => 999999, 'fisik' => 0, 'penyebab' => 'hilang']]]), 'tidak ada di stok Anda');
        $this->gagal(fn () => $this->svc->simpan($this->m, ['item' => [['item_id' => $this->a->id, 'fisik' => 8, 'penyebab' => 'hilang']]], 'bukan gambar'), 'JPG/PNG/WebP');

        $o = $this->svc->simpan($this->m, ['catatan' => 'hitung akhir bulan', 'item' => [
            ['item_id' => $this->a->id, 'fisik' => 8, 'penyebab' => 'rusak', 'catatan' => 'jatuh'],
            ['item_id' => $this->b->id, 'fisik' => 3, 'penyebab' => 'tester'],
        ]], $this->png);
        $this->assertMatchesRegularExpression('/^OPM-\d{6}-\d{4}$/', $o->nomor);
        $this->assertSame([8, 3], [$this->stok($this->a), $this->stok($this->b)]);
        $this->assertSame(260000.0, $o->total_nilai, '2 × 100.000 + 1 × 60.000 (modal rata-rata)');
        $this->assertSame(['rusak', 'tester'], $o->item->pluck('penyebab')->all());
        Storage::disk('local')->assertExists($o->foto);
        $this->assertSame(2, StokMemberMutasi::where('referensi_type', 'opname_member')->where('referensi_id', $o->id)->where('jenis', 'opname')->count());
        $this->assertSame([$ro, $trx], [RoCatatan::count(), TransaksiKeuangan::count()], 'tidak mengubah RO maupun keuangan BABA');
        // laporan Toko Saya: kerugian stok & laba bersih, transaksi jenis opname
        $lap = app(KasirMemberService::class)->laporan($this->m, 'transaksi', null, null);
        $this->assertSame([260000.0, -260000.0], [$lap['ringkas']['kerugian_stok'], $lap['ringkas']['laba_bersih']]);
        $this->assertSame('opname', $lap['data'][0]['jenis']);
        $this->assertStringContainsString('(Rusak / pecah / bocor)', $lap['data'][0]['keterangan']);
    }

    public function test_stok_dipesan_downline_tidak_boleh_dilampaui(): void
    {
        $pesanan = \Mockery::mock(\App\Services\PesananOnlineService::class);
        $pesanan->shouldReceive('dipesanMember')->andReturn(5);
        $svc = new OpnameMemberService(app(StokMemberService::class), $pesanan);
        $this->gagal(fn () => $svc->simpan($this->m, ['item' => [['item_id' => $this->a->id, 'fisik' => 4, 'penyebab' => 'hilang']]]), '5 sudah dipesan downline');
        $this->assertSame('OPM', substr($svc->simpan($this->m, ['item' => [['item_id' => $this->a->id, 'fisik' => 5, 'penyebab' => 'hilang']]])->nomor, 0, 3));
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

    public function test_api_dan_rekap_erp(): void
    {
        $r = $this->api('POST', '/api/member/stok/opname', 940001, ['item' => [['item_id' => $this->a->id, 'fisik' => 9, 'penyebab' => 'hilang']], 'foto' => base64_encode($this->png)])->assertStatus(201);
        $nomor = $r->json('data.nomor');
        $this->assertSame([1, 100000.0, true], [$r->json('data.item.0.qty'), (float) $r->json('data.total_nilai'), $r->json('data.ada_foto')]);
        $this->api('POST', '/api/member/stok/opname', 940001, ['item' => [['item_id' => $this->a->id, 'fisik' => 50, 'penyebab' => 'hilang']]])->assertStatus(422)->assertJsonPath('ok', false);
        $this->assertSame($nomor, $this->api('GET', '/api/member/stok/opname', 940001)->assertOk()->json('data.0.nomor'));
        $this->api('GET', "/api/member/stok/opname/{$nomor}", 940002)->assertStatus(404);
        $this->api('GET', "/api/member/stok/opname/{$nomor}", 940001)->assertOk()->assertJsonPath('data.item.0.penyebab_label', 'Hilang');
        $this->assertSame('image/png', $this->api('GET', "/api/member/stok/opname/{$nomor}/foto", 940001)->assertOk()->json('mime'));
        $this->assertSame('Stok opname', collect($this->api('GET', '/api/member/stok', 940001)->json('mutasi'))->firstWhere('jenis', 'opname')['jenis_label']);
        // ERP: rekap per member & rincian member
        $admin = $this->buatUser('admin_pusat');
        $this->actingAs($admin)->get(route('toko-online.stok-member.opname'))->assertOk()->assertSee('M940001')->assertSee('Rp 100.000'); // nama ikut profil terakhir dari aplikasi member
        $this->actingAs($admin)->get(route('toko-online.stok-member.show', 940001))->assertOk()->assertSee($nomor);
        $this->actingAs($this->buatUser('kasir', Cabang::first()->id))->get(route('toko-online.stok-member.opname'))->assertForbidden();
        $this->assertSame(1, OpnameMember::where('member_id', 940001)->count());
    }
}
