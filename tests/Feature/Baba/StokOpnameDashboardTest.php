<?php

namespace Tests\Feature\Baba;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\StokOpnameItem;
use App\Models\User;
use App\Notifications\StokOpnamePengingatNotification;
use App\Services\DashboardStokOpnameService;
use App\Services\StokOpnameService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** ERP BABA Tahap 7e — Dashboard Stok Opname & pengingat akhir bulan. */
class StokOpnameDashboardTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private User $owner;
    protected array $lok = [];
    protected function lokasiM() { return $this->lok["M"]; }
    protected function namaA() { return $this->lok["A"]->nama_cabang; }
    private array $brg = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->owner = $this->buatUser('owner');
        $this->actingAs($this->owner);
        foreach (['A' => ['gudang_pusat', null], 'B' => ['cabang', 'baba'], 'C' => ['cabang', 'baba'], 'M' => ['cabang', 'kemitraan']] as $k => [$tipe, $jenis]) {
            $this->lok[$k] = Cabang::create(['nama_cabang' => "Lokasi Dash {$k} " . uniqid(), 'kode_cabang' => 'DS' . rand(10000, 99999), 'tipe' => $tipe, 'jenis_toko' => $jenis, 'is_active' => true]);
            $this->brg[$k] = Item::create(['cabang_id' => $this->lok[$k]->id, 'kode_item' => 'DS' . Str::random(6), 'nama_item' => "Barang Dash {$k}", 'tipe' => 'bahan_baku', 'jenis' => 'bahan_baku',
                'satuan' => 'pcs', 'track_stok' => true, 'is_active' => true]);
            app(StokService::class)->masuk($this->brg[$k]->id, $this->lok[$k]->id, 10, 'awal', 'uji', null, 10000);
        }
    }

    public function test_status_bulan_ini_data_dashboard_dan_halaman(): void
    {
        $op = app(StokOpnameService::class);
        // A selesai (kurang 2 hilang), B sedang dihitung, C belum
        $o = $op->mulai($this->owner, $this->lok['A'], ['cakupan' => 'semua']);
        $row = $op->hitung($this->owner, $o, StokOpnameItem::where('stok_opname_id', $o->id)->firstOrFail(), 8);
        $op->simpanRincian($this->owner, $o, $row, [['penyebab' => 'hilang', 'qty' => 2]]);
        $o = $op->setujui($this->owner, $op->ajukan($this->owner, $o));
        $op->mulai($this->owner, $this->lok['B'], ['cakupan' => 'semua']);
        $dash = app(DashboardStokOpnameService::class);
        $st = collect($dash->statusBulanIni(collect([$this->lok['A'], $this->lok['B'], $this->lok['C']])))->mapWithKeys(fn ($s) => [$s['lokasi']->id => $s['status']]);
        $this->assertSame(['selesai', 'hitung', 'belum'], [$st[$this->lok['A']->id], $st[$this->lok['B']->id], $st[$this->lok['C']->id]]);
        $d = $dash->data($this->owner, [$this->lok['A']->id, $this->lok['B']->id, $this->lok['C']->id]);
        $this->assertEquals(20000, $d['ini']['per_penyebab']['hilang']['nilai']);
        $this->assertSame('Barang Dash A', $d['sering'][0]->nama);
        $this->assertCount(6, $d['bulan']);
        $this->assertSame(0.0, end($d['akurasi'][0]['data']) ?: 0.0, 'akurasi bulan ini 0% (1 barang, kurang)');
        $this->actingAs($this->owner)->get(route('stok-opname.dashboard'))->assertOk()->assertSee('data-uji="status-lokasi"', false)->assertSee($o->nomor);
        $this->actingAs($this->buatUser('admin_gudang', $this->lok['A']->id))->get(route('stok-opname.dashboard'))->assertForbidden();
        $pemilik = $this->buatUser('pemilik_toko', $this->lok['M']->id);
        $this->flushSession(); // sesi uji dipakai bersama antar-permintaan (cabang aktif Owner); di aplikasi tiap pengguna punya sesi sendiri
        $this->actingAs($pemilik)->get(route('stok-opname.dashboard'))->assertOk()->assertSee($this->lok['M']->nama_cabang)->assertDontSee($this->lok['A']->nama_cabang);
    }

    public function test_masa_dan_pengingat_akhir_bulan(): void
    {
        $this->assertFalse(DashboardStokOpnameService::masaPengingat(Carbon::parse('2026-11-26')));
        $this->assertTrue(DashboardStokOpnameService::masaPengingat(Carbon::parse('2026-11-27')));
        $this->assertTrue(DashboardStokOpnameService::masaPengingat(Carbon::parse('2026-11-30')));
        $this->assertTrue(DashboardStokOpnameService::masaPengingat(Carbon::parse('2027-02-25')));
        $this->assertFalse(DashboardStokOpnameService::masaPengingat(Carbon::parse('2027-02-24')));

        $kepalaB = $this->buatUser('manajer_cabang', $this->lok['B']->id);
        $kasirB = $this->buatUser('kasir', $this->lok['B']->id);
        $pemilik = $this->buatUser('pemilik_toko', $this->lok['M']->id);
        // A sudah opname → tidak diingatkan
        $op = app(StokOpnameService::class);
        $op->mulai($this->owner, $this->lok['A'], ['cakupan' => 'semua']);
        $kepalaA = $this->buatUser('admin_gudang', $this->lok['A']->id);

        $this->artisan('stok-opname:pengingat', ['--tanggal' => now()->startOfMonth()->toDateString()])->expectsOutputToContain('Belum masa pengingat')->assertSuccessful();
        $this->assertSame(0, $this->owner->notifications()->count());
        $this->artisan('stok-opname:pengingat', ['--paksa' => true])->assertSuccessful();
        $n = fn (User $u) => $u->notifications()->where('type', StokOpnamePengingatNotification::class)->get();
        $this->assertCount(1, $n($kepalaB));
        $this->assertStringContainsString($this->lok['B']->nama_cabang, $n($kepalaB)->first()->data['message']);
        $this->assertCount(0, $n($kasirB), 'kasir (tanpa izin ajukan) bukan kepala lokasi');
        $this->assertCount(0, $n($kepalaA), 'lokasi yang sudah opname tidak diingatkan');
        $pesanOwner = $n($this->owner)->first()->data['message'];
        $this->assertStringContainsString($this->lok['C']->nama_cabang, $pesanOwner);
        $this->assertStringNotContainsString($this->lok['A']->nama_cabang, $pesanOwner);
        $this->assertStringNotContainsString($this->lok['M']->nama_cabang, $pesanOwner, 'Owner BABA tidak diingatkan toko mitra');
        $this->assertStringContainsString($this->lok['M']->nama_cabang, $n($pemilik)->first()->data['message'], 'pemilik toko mitra diingatkan tokonya');
        // dijalankan lagi hari yang sama → tidak dobel
        $this->artisan('stok-opname:pengingat', ['--paksa' => true])->assertSuccessful();
        $this->assertCount(1, $n($kepalaB));
        $this->assertCount(1, $n($this->owner));
    }
}
