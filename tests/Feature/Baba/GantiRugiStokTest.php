<?php

namespace Tests\Feature\Baba;

use App\Exceptions\StokOpnameException;
use App\Models\Cabang;
use App\Models\GantiRugiStok;
use App\Models\Item;
use App\Models\Karyawan;
use App\Models\Penggajian;
use App\Models\StokOpnameItem;
use App\Models\StokOpnameRincian;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use App\Services\GantiRugiStokService;
use App\Services\JurnalGajiService;
use App\Services\KasBawaanService;
use App\Services\LabaRugiFormalService;
use App\Services\PenggajianService;
use App\Services\StokOpnameService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** ERP BABA Tahap 7d — ganti rugi karyawan atas barang hilang: tunai (kas) & potong gaji dicicil lewat slip gaji. */
class GantiRugiStokTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $gudang;
    private Karyawan $rina;
    private Karyawan $budi;
    private StokOpnameRincian $hilang;
    private GantiRugiStokService $svc;
    private User $ap;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAs($this->buatUser('owner'));
        $this->svc = app(GantiRugiStokService::class);
        $this->gudang = Cabang::create(['nama_cabang' => 'Gudang GR ' . uniqid(), 'kode_cabang' => 'GR' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        $mk = fn ($nama) => Karyawan::create(['cabang_id' => $this->gudang->id, 'nik' => (string) rand(1000000000, 9999999999), 'nama_lengkap' => $nama,
            'jenis_kelamin' => 'P', 'jabatan' => 'Admin Gudang', 'tanggal_masuk' => today()->subYear(), 'gaji_pokok' => 3000000, 'status' => 'aktif']);
        $this->rina = $mk('Rina Uji GR');
        $this->budi = $mk('Budi Uji GR');
        $i = Item::create(['cabang_id' => $this->gudang->id, 'kode_item' => 'GR' . Str::random(6), 'nama_item' => 'Parfum Hilang Uji', 'tipe' => 'bahan_baku', 'jenis' => 'bahan_baku',
            'satuan' => 'pcs', 'track_stok' => true, 'is_active' => true]);
        app(StokService::class)->masuk($i->id, $this->gudang->id, 10, 'awal', 'uji', null, 50000);
        // opname: 4 botol hilang (Rp 200.000 = batas foto → foto wajib)
        $op = app(StokOpnameService::class);
        $adminG = $this->buatUser('admin_gudang', $this->gudang->id);
        $this->actingAs($adminG);
        $o = $op->mulai($adminG, $this->gudang, ['cakupan' => 'semua']);
        $row = $op->hitung($adminG, $o, StokOpnameItem::where('stok_opname_id', $o->id)->firstOrFail(), 6);
        $op->simpanRincian($adminG, $o, $row, [['penyebab' => 'hilang', 'qty' => 4,
            'foto' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==')]]);
        $o = $op->ajukan($adminG, $o);
        $this->ap = $this->buatUser('admin_pusat');
        $this->actingAs($this->ap);
        $op->setujui($this->ap, $o);
        $this->hilang = StokOpnameRincian::whereHas('itemOpname', fn ($q) => $q->where('stok_opname_id', $o->id))->firstOrFail();
    }

    private function gagal(callable $f, string $pola): void
    {
        try {
            $f();
            $this->fail("Seharusnya ditolak: {$pola}");
        } catch (StokOpnameException $e) {
            $this->assertMatchesRegularExpression($pola, $e->getMessage());
        }
    }

    private function slipDibayar(string $periode): Penggajian
    {
        $p = app(PenggajianService::class)->generate($this->budi, $periode);
        app(JurnalGajiService::class)->bayar($p, null, $this->ap->id);

        return $p->fresh();
    }

    public function test_tunai_dan_potong_gaji_dicicil_sampai_lunas(): void
    {
        $this->assertSame(200000.0, (float) $this->hilang->nilai);
        $this->gagal(fn () => $this->svc->bebankan($this->ap, $this->hilang, ['karyawan_id' => $this->rina->id, 'nilai' => 150000, 'cara' => 'tunai']), '/tulis alasannya/');
        $a = $this->svc->bebankan($this->ap, $this->hilang, ['karyawan_id' => $this->rina->id, 'nilai' => 100000, 'cara' => 'tunai', 'alasan' => 'dibagi dua dengan Budi']);
        $this->assertSame(['GR-' . now()->format('Ym') . '-', 100000.0, 100000.0, null], [substr($a->nomor, 0, 10), $a->nilai, $a->nilai_pokok, $a->mulai_periode]);
        // sisa pokok 100.000 → nilai sama tidak perlu alasan; potong gaji 3×
        $b = $this->svc->bebankan($this->ap, $this->hilang, ['karyawan_id' => $this->budi->id, 'nilai' => 100000, 'cara' => 'potong_gaji', 'cicilan_bulan' => 3]);
        $p1 = now()->addMonthNoOverflow()->format('Y-m');
        $this->assertSame([$p1, 3, 33334.0], [$b->mulai_periode, $b->cicilan_bulan, $b->cicilan]);
        $this->gagal(fn () => $this->svc->bebankan($this->ap, $this->hilang, ['karyawan_id' => $this->rina->id, 'nilai' => 10000, 'cara' => 'tunai']), '/sudah dibebankan seluruhnya/');

        // tunai Rina
        $kas = app(KasBawaanService::class)->pastikan($this->gudang)['tunai'];
        $this->gagal(fn () => $this->svc->bayarTunai($this->ap, $a, 150000, $kas->id), '/antara Rp 1 dan Rp 100\.000/');
        $this->svc->bayarTunai($this->ap, $a, 100000, $kas->id);
        $this->assertSame(['lunas', 100000.0], [$a->fresh()->status, (float) $a->fresh()->terbayar]);
        $this->assertSame(100000.0, (float) $kas->fresh()->saldo_sekarang);

        // gaji bulan ini: belum jatuh tempo → tanpa potongan
        $p0 = app(PenggajianService::class)->generate($this->budi, now()->format('Y-m'));
        $this->assertSame(0.0, (float) $p0->potongan_ganti_rugi);
        // bulan berikutnya: potongan 33.334, generate ulang tidak dobel
        $s = app(PenggajianService::class)->generate($this->budi, $p1);
        $bersih = (float) $s->total_gaji;
        $this->assertSame(33334.0, (float) $s->potongan_ganti_rugi);
        $s = app(PenggajianService::class)->generate($this->budi, $p1);
        $this->assertSame([33334.0, $bersih], [(float) $s->potongan_ganti_rugi, (float) $s->total_gaji]);
        $this->assertSame(1, $b->bayar()->where('status', 'menunggu')->count());
        $kasT = app(KasBawaanService::class)->pastikan($this->gudang)['transfer'];
        app(JurnalGajiService::class)->bayar($s, null, $this->ap->id);
        // beban gaji bruto, kas hanya gaji bersih, potongan = pemulihan tanpa kas
        $this->assertSame(-$bersih, (float) $kasT->fresh()->saldo_sekarang);
        $gaji = TransaksiKeuangan::where('referensi_type', 'penggajian')->where('referensi_id', $s->id)->firstOrFail();
        $this->assertSame([$bersih, (int) $kasT->id], [(float) $gaji->jumlah, (int) $gaji->kas_id], 'kas hanya gaji bersih');
        $potong = TransaksiKeuangan::where('referensi_type', 'penggajian_potongan')->where('referensi_id', $s->id)->firstOrFail();
        $this->assertSame([33334.0, null, 'gaji'], [(float) $potong->jumlah, $potong->kas_id, $potong->kategori->value], 'beban gaji bruto: bagian potongan tanpa kas');
        $pulih = TransaksiKeuangan::where('referensi_type', 'penggajian_ganti_rugi')->where('referensi_id', $s->id)->firstOrFail();
        $this->assertSame([33334.0, null, 'PULIH-STOK'], [(float) $pulih->jumlah, $pulih->kas_id, $pulih->kategoriDinamis?->kode]);
        $this->assertSame([33334.0, 'berjalan'], [(float) $b->fresh()->terbayar, $b->fresh()->status]);
        $this->slipDibayar(now()->addMonthsNoOverflow(2)->format('Y-m'));
        $s3 = $this->slipDibayar(now()->addMonthsNoOverflow(3)->format('Y-m'));
        $this->assertSame(33332.0, (float) $s3->potongan_ganti_rugi, 'cicilan terakhir = sisa');
        $this->assertSame([100000.0, 'lunas'], [(float) $b->fresh()->terbayar, $b->fresh()->status]);
        $this->assertSame(0.0, (float) app(PenggajianService::class)->generate($this->budi, now()->addMonthsNoOverflow(4)->format('Y-m'))->potongan_ganti_rugi);
        // Laba Rugi gudang: pemulihan tunai tercatat (7-1103)
        $lr = app(LabaRugiFormalService::class)->hitungLabaRugi(Carbon::today(), Carbon::today(), $this->gudang->id);
        $this->assertEquals(100000 + 100000, collect($lr['pendapatan_lain']['detail'])->firstWhere('kode', '7-1103')['jumlah']);
        // halaman slip menampilkan potongan
        $this->get(route('penggajian.show', $s))->assertOk()->assertSee('Ganti rugi barang hilang');
    }

    public function test_pengaturan_stok_opname_disimpan_dan_dipakai(): void
    {
        $owner = User::where('role', 'owner')->orderBy('id')->get()->last();
        $s = \App\Models\PengaturanUmum::getSetting();
        $this->actingAs($owner)->get(route('pengaturan.umum'))->assertOk()->assertSee('data-uji="pengaturan-opname"', false);
        $this->actingAs($owner)->put(route('pengaturan.umum.update'), ['nama_perusahaan' => $s->nama_perusahaan ?: 'BABA PARFUME', 'mode_ro' => $s->mode_ro ?: 'lunas',
            'opname_hitung_buta' => '0', 'opname_batas_foto_hilang' => '0'])->assertSessionHasNoErrors();
        $baru = \App\Models\PengaturanUmum::query()->firstOrFail();
        $this->assertSame([false, 0.0], [(bool) $baru->opname_hitung_buta, (float) $baru->opname_batas_foto_hilang]);
        $this->actingAs($owner)->put(route('pengaturan.umum.update'), ['nama_perusahaan' => $baru->nama_perusahaan, 'opname_batas_foto_hilang' => '-5'])->assertSessionHasErrors('opname_batas_foto_hilang');
        // batas 0 → setiap barang hilang wajib difoto saat diajukan
        $op = app(StokOpnameService::class);
        $this->actingAs($owner);
        $i = Item::create(['cabang_id' => $this->gudang->id, 'kode_item' => 'BT' . Str::random(6), 'nama_item' => 'Uji Batas Foto', 'tipe' => 'bahan_baku', 'jenis' => 'bahan_baku',
            'satuan' => 'pcs', 'track_stok' => true, 'is_active' => true]);
        app(StokService::class)->masuk($i->id, $this->gudang->id, 2, 'awal', 'uji', null, 1000);
        $o = $op->mulai($owner, $this->gudang, ['cakupan' => 'barang', 'barang' => [$i->id]]);
        $row = $op->hitung($owner, $o, StokOpnameItem::where('stok_opname_id', $o->id)->firstOrFail(), 1);
        $op->simpanRincian($owner, $o, $row, [['penyebab' => 'hilang', 'qty' => 1]]);
        $this->assertStringContainsString('wajib difoto (batas Rp 0)', implode(' ', $op->masalah($o)));
        $this->assertFalse((bool) $op->mulai($owner, $this->gudang, ['cakupan' => 'barang', 'barang' => [$this->budi->id * 0 + $this->hilang->itemOpname->item_id]])->hitung_buta, 'hitung buta bawaan ikut pengaturan');
    }

    public function test_batal_mengeluarkan_potongan_dari_slip_belum_dibayar_dan_hak(): void
    {
        $g = $this->svc->bebankan($this->ap, $this->hilang, ['karyawan_id' => $this->budi->id, 'nilai' => 200000, 'cara' => 'potong_gaji', 'cicilan_bulan' => 1]);
        $s = app(PenggajianService::class)->generate($this->budi, $g->mulai_periode);
        $this->assertSame(200000.0, (float) $s->potongan_ganti_rugi);
        $total = (float) $s->total_gaji;
        $this->gagal(fn () => $this->svc->batal($this->ap, $g, 'x'), '/alasan/');
        $this->svc->batal($this->ap, $g, 'Barang ternyata ditemukan');
        $s->refresh();
        $this->assertSame([0.0, $total + 200000, 'batal'], [(float) $s->potongan_ganti_rugi, (float) $s->total_gaji, $g->fresh()->status]);
        $this->assertSame(0, $g->bayar()->count());
        // hak: admin gudang tanpa izin ganti rugi
        $adminG = $this->buatUser('admin_gudang', $this->gudang->id);
        $this->gagal(fn () => $this->svc->bebankan($adminG, $this->hilang, ['karyawan_id' => $this->rina->id, 'nilai' => 1, 'cara' => 'tunai', 'alasan' => 'uji uji']), '/tidak bisa membebankan/');
        $this->actingAs($adminG)->get(route('stok-opname.ganti-rugi'))->assertForbidden();
        // halaman & form
        $this->actingAs($this->ap)->get(route('stok-opname.ganti-rugi', ['tab' => 'batal']))->assertOk()->assertSee($g->nomor);
        $this->actingAs($this->ap)->post(route('stok-opname.ganti-rugi.bebankan', $this->hilang), ['karyawan_id' => $this->rina->id, 'nilai' => 200000, 'cara' => 'tunai'])
            ->assertRedirect()->assertSessionHas('success');
        $this->actingAs($this->ap)->get(route('stok-opname.show', $this->hilang->itemOpname->stok_opname_id, false) . '?tab=selisih')->assertOk()->assertSee('Rina Uji GR');
        $this->assertSame(1, GantiRugiStok::where('karyawan_id', $this->rina->id)->where('status', 'berjalan')->count());
    }
}
