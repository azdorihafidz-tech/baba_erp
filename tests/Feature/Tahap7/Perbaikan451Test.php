<?php

namespace Tests\Feature\Tahap7;

use App\Models\Absensi;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\Karyawan;
use App\Models\Kas;
use App\Models\KategoriTransaksi;
use App\Models\Order;
use App\Models\Penggajian;
use App\Models\PengaturanGaji;
use App\Models\RecurringTransaksi;
use App\Models\TransaksiKeuangan;
use App\Services\CascadeDeleteService;
use App\Services\NeracaService;
use App\Services\PenggajianService;
use App\Services\StokService;
use App\Support\TransaksiInternal;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * 4.51 — perbaikan temuan A–G audit Kamus Menu:
 * A Bayar gaji mengurangi kas (+ Batal Bayar)   B Generate ulang tidak menimpa slip disetujui/dibayar
 * C Transaksi Berulang: frekuensi, tidak dobel   D Cabang beroperasi tidak bisa dihapus
 * E Neraca tidak menghitung stok barang terhapus F Laporan kas tanpa transfer internal + filter cabang
 * G Potongan telat per menit dipakai
 */
class Perbaikan451Test extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    protected function setUp(): void
    {
        parent::setUp();
        TransaksiInternal::reset();
    }

    private function cabang(): Cabang
    {
        return Cabang::where('tipe', 'cabang')->orderBy('id')->firstOrFail();
    }

    private function kasTunai(Cabang $cabang, float $saldo = 1000000): Kas
    {
        $kas = Kas::firstOrCreate(
            ['cabang_id' => $cabang->id, 'default_untuk' => 'tunai'],
            ['nama_kas' => 'Kas tunai T451', 'tipe_kas' => 'tunai', 'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true]
        );
        $kas->update(['saldo_sekarang' => $saldo, 'is_active' => true]);

        return $kas->fresh();
    }

    private function karyawan(Cabang $cabang, float $gaji = 3000000): Karyawan
    {
        return Karyawan::create([
            'cabang_id' => $cabang->id, 'nik' => 'K451' . rand(10000, 99999), 'nama_lengkap' => 'Karyawan T451',
            'jenis_kelamin' => 'L', 'jabatan' => 'Staf', 'tipe_karyawan' => 'tetap', 'tanggal_masuk' => '2026-01-01',
            'gaji_pokok' => $gaji, 'status' => 'aktif',
        ]);
    }

    // ---------------- A. Bayar gaji → kas ----------------

    // BABA: test bayar & batal bayar versi Dimsum tidak dipakai — bayar gaji ke kas BABA lewat JurnalGajiService (Tahap 5, Baba/JurnalGajiTest).

    public function test_bayar_gaji_menolak_kas_cabang_lain(): void
    {
        $cabang = $this->cabang();
        $lain = Cabang::where('tipe', 'cabang')->where('id', '!=', $cabang->id)->first();
        if (! $lain) {
            $this->markTestSkipped('Butuh 2 cabang.');
        }
        $kasLain = $this->kasTunai($lain);
        $slip = app(PenggajianService::class)->generate($this->karyawan($cabang), '2026-10');
        $this->actingAs($this->buatUser('owner'))->post(route('penggajian.bayar', $slip), ['kas_id' => $kasLain->id])
            ->assertSessionHas('error');
        $this->assertSame('draft', $slip->fresh()->status);
    }

    // ---------------- B. Generate ulang ----------------

    public function test_generate_ulang_tidak_menimpa_slip_disetujui_atau_dibayar(): void
    {
        $cabang = $this->cabang();
        $k = $this->karyawan($cabang, 3000000);
        $svc = app(PenggajianService::class);
        $slip = $svc->generate($k, '2026-09');
        $slip->update(['status' => 'disetujui']);
        $totalLama = (float) $slip->total_gaji;
        $k->update(['gaji_pokok' => 9000000]);

        try {
            $svc->generate($k, '2026-09');
            $this->fail('Seharusnya ditolak');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('tidak bisa dihitung ulang', $e->getMessage());
        }
        $hasil = $svc->bulkGenerate($cabang->id, '2026-09');
        $this->assertContains($k->nama_lengkap, $hasil['dilewati']);
        $slip->refresh();
        $this->assertSame('disetujui', $slip->status);
        $this->assertEqualsWithDelta($totalLama, (float) $slip->total_gaji, 0.01);
    }

    // ---------------- G. Potongan telat ----------------

    public function test_potongan_telat_per_menit_dipakai(): void
    {
        $cabang = $this->cabang();
        $k = $this->karyawan($cabang);
        $setting = PengaturanGaji::getSetting();
        $setting->update(['potongan_telat_per_menit' => 1000]);
        \Illuminate\Support\Facades\Cache::flush();
        foreach (['2026-08-03' => 10, '2026-08-04' => 25] as $tgl => $menit) {
            Absensi::create(['karyawan_id' => $k->id, 'cabang_id' => $cabang->id, 'tanggal' => $tgl, 'status' => 'hadir', 'is_telat' => true, 'menit_telat' => $menit]);
        }
        $slip = app(PenggajianService::class)->generate($k, '2026-08');
        $this->assertSame(35, (int) $slip->menit_telat_total);
        $this->assertEqualsWithDelta(35000, (float) $slip->potongan_telat, 0.01);
    }

    // ---------------- C. Transaksi Berulang ----------------

    private function template(array $attr): RecurringTransaksi
    {
        $cabang = $this->cabang();

        return RecurringTransaksi::create(array_merge([
            'nama_template' => 'Sewa T451', 'cabang_id' => $cabang->id, 'kas_id' => $this->kasTunai($cabang)->id,
            'kategori_id' => KategoriTransaksi::where('kode', 'SEWA')->value('id'), 'tipe' => 'pengeluaran', 'jumlah' => 100000,
            'keterangan' => 'Sewa T451', 'frekuensi' => 'bulanan', 'tanggal_jatuh_tempo' => 31, 'tanggal_mulai' => '2026-01-01', 'is_active' => true,
        ], $attr));
    }

    public function test_transaksi_berulang_tidak_dobel_walau_dijalankan_berkali_kali(): void
    {
        $t = $this->template([]);
        $hari = Carbon::parse('2026-02-28'); // bulan pendek: tgl 31 → 28 Feb
        $this->assertSame(1, RecurringTransaksi::jalankanYangJatuhTempo($hari) >= 1 ? 1 : 0);
        RecurringTransaksi::jalankanYangJatuhTempo($hari);
        RecurringTransaksi::jalankanYangJatuhTempo($hari->copy()->addDays(3)); // masih periode Feb
        $this->assertSame(1, TransaksiKeuangan::where('keterangan', 'Sewa T451 (Recurring)')->count());
        $this->assertSame('2026-02-28', $t->fresh()->tanggal_terakhir_generate->toDateString());
        // tombol Generate manual juga menolak periode yang sudah dibuat
        Carbon::setTestNow('2026-03-05');
        $this->actingAs($this->buatUser('owner'))->post(route('recurring.generate', $t->fresh()))->assertSessionHas('error');
        Carbon::setTestNow();
    }

    public function test_frekuensi_harian_mingguan_tahunan_dihormati(): void
    {
        $harian = $this->template(['frekuensi' => 'harian', 'tanggal_mulai' => '2026-03-01']);
        $this->assertSame('2026-03-10', $harian->jatuhTempoTerakhir(Carbon::parse('2026-03-10'))->toDateString());
        $mingguan = $this->template(['frekuensi' => 'mingguan', 'tanggal_mulai' => '2026-03-02']); // Senin
        $this->assertSame('2026-03-09', $mingguan->jatuhTempoTerakhir(Carbon::parse('2026-03-14'))->toDateString());
        $tahunan = $this->template(['frekuensi' => 'tahunan', 'tanggal_jatuh_tempo' => 15, 'tanggal_mulai' => '2025-07-01']);
        $this->assertSame('2025-07-15', $tahunan->jatuhTempoTerakhir(Carbon::parse('2026-07-14'))->toDateString());
        $this->assertNull((clone $tahunan)->forceFill(['tanggal_terakhir_generate' => '2025-07-15'])->perluDibuat(Carbon::parse('2026-07-14')));
    }

    // ---------------- D. Hapus cabang ----------------

    public function test_cabang_yang_punya_transaksi_tidak_bisa_dihapus(): void
    {
        $cabang = $this->cabang();
        $this->assertNotEmpty(app(CascadeDeleteService::class)->alasanCabangTidakBolehDihapus($cabang)
            ?: (function () use ($cabang) {
                TransaksiKeuangan::create(['cabang_id' => $cabang->id, 'nomor_transaksi' => 'T451-' . uniqid(), 'tanggal_transaksi' => today(),
                    'tipe' => 'pengeluaran', 'kategori' => 'lainnya', 'keterangan' => 'x', 'jumlah' => 1]);
                return app(CascadeDeleteService::class)->alasanCabangTidakBolehDihapus($cabang);
            })());
        $this->actingAs($this->buatUser('owner'))->delete(route('cabang.destroy', $cabang))->assertSessionHas('error');
        $this->assertNull($cabang->fresh()->deleted_at);

        $kosong = Cabang::create(['kode_cabang' => 'T451' . rand(10, 99), 'nama_cabang' => 'Cabang Kosong T451', 'tipe' => 'cabang', 'is_active' => true]);
        $this->assertSame([], app(CascadeDeleteService::class)->alasanCabangTidakBolehDihapus($kosong));
    }

    // ---------------- E. Neraca ----------------

    public function test_neraca_tidak_menghitung_stok_barang_yang_dihapus(): void
    {
        $cabang = $this->cabang();
        $item = Item::create(['cabang_id' => $cabang->id, 'kode_item' => 'T451-' . uniqid(), 'nama_item' => 'Bahan T451', 'tipe' => 'bahan_baku', 'satuan' => 'pcs', 'is_active' => true, 'track_stok' => true]);
        $sebelum = app(NeracaService::class)->hitungNeraca(today(), $cabang->id)['aset']['lancar']['persediaan_bahan_baku'];
        app(StokService::class)->masuk($item->id, $cabang->id, 10, 'stok T451', 'test', null, 5000);
        $dengan = app(NeracaService::class)->hitungNeraca(today(), $cabang->id)['aset']['lancar']['persediaan_bahan_baku'];
        $this->assertEqualsWithDelta($sebelum + 50000, $dengan, 0.01);
        DB::table('items')->where('id', $item->id)->update(['deleted_at' => now()]); // cara hapus lama (stok ikut tersembunyi)
        $tanpa = app(NeracaService::class)->hitungNeraca(today(), $cabang->id)['aset']['lancar']['persediaan_bahan_baku'];
        $this->assertEqualsWithDelta($sebelum, $tanpa, 0.01);
    }

    // ---------------- F. Laporan kas ----------------

    public function test_laporan_keuangan_tidak_menghitung_transfer_internal_dan_saldo_awal(): void
    {
        $cabang = $this->cabang();
        $buat = fn ($kode, $tipe, $jumlah) => TransaksiKeuangan::create([
            'cabang_id' => $cabang->id, 'nomor_transaksi' => 'T451-' . uniqid(), 'tanggal_transaksi' => '2026-06-10',
            'tipe' => $tipe, 'kategori' => 'lainnya', 'kategori_id' => KategoriTransaksi::where('kode', $kode)->value('id'),
            'keterangan' => 'T451 ' . $kode, 'jumlah' => $jumlah,
        ]);
        $buat('MUTASI-OUT', 'pengeluaran', 700000);
        $buat('MUTASI-IN', 'pemasukan', 700000);
        $buat('SALDO', 'pemasukan', 5000000);
        $buat('LISTRIK', 'pengeluaran', 150000);

        $q = TransaksiKeuangan::where('cabang_id', $cabang->id)->whereDate('tanggal_transaksi', '2026-06-10')->where('keterangan', 'like', 'T451 %');
        $this->assertEqualsWithDelta(150000, (float) TransaksiInternal::kecualikan(clone $q)->sum('jumlah'), 0.01);
        $this->assertEqualsWithDelta(6400000, (float) TransaksiInternal::hanya(clone $q)->sum('jumlah'), 0.01);

        $html = $this->actingAs($this->buatUser('owner'))
            ->get(route('laporan.keuangan.laba-rugi', ['dari' => '2026-06-10', 'sampai' => '2026-06-10', 'cabang_id' => $cabang->id]))
            ->assertOk()->assertSee('Tidak dihitung (uang hanya pindah tempat)')->getContent();
        $this->assertStringContainsString('5.700.000', $html); // internal masuk ditampilkan sebagai info
    }

    public function test_laporan_keuangan_role_cabang_tidak_bisa_membuka_cabang_lain_lewat_alamat(): void
    {
        $cabang = $this->cabang();
        $lain = Cabang::where('tipe', 'cabang')->where('id', '!=', $cabang->id)->first();
        if (! $lain) {
            $this->markTestSkipped('Butuh 2 cabang.');
        }
        TransaksiKeuangan::create(['cabang_id' => $lain->id, 'nomor_transaksi' => 'T451-' . uniqid(), 'tanggal_transaksi' => '2026-06-11',
            'tipe' => 'pengeluaran', 'kategori' => 'lainnya', 'keterangan' => 'RAHASIA-CABANG-LAIN-451', 'jumlah' => 123457]);
        $manajer = $this->buatUser('manajer_cabang', $cabang->id);
        DB::table('role_permissions')->insertOrIgnore(['role' => 'manajer_cabang', 'permission_id' => DB::table('permissions')->where('name', 'laporan.keuangan.view')->value('id')]);
        \Illuminate\Support\Facades\Cache::forget('role_permissions_manajer_cabang');

        $this->actingAs($manajer)->get(route('laporan.keuangan.arus-kas', ['dari' => '2026-06-11', 'sampai' => '2026-06-11', 'cabang_id' => $lain->id]))
            ->assertOk()->assertDontSee('RAHASIA-CABANG-LAIN-451');
    }
}
