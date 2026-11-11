<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Kas;
use App\Models\KategoriTransaksi;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PengaturanUmum;
use App\Models\TransaksiKeuangan;
use App\Services\ClosingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\Feature\Tahap7\Concerns\ClosingTestHelpers;
use Tests\TestCase;

/**
 * Closing / Tutup Buku Tahap 1 (2026-10-17) — preview laporan closing.
 * Dua skenario usaha: (A) 1 cabang tanpa Gudang Pusat, setoran lewat
 * Transfer Antar Kas; (B) pusat + cabang, setoran lewat Setoran Kasir &
 * Transfer Dana. Tanggal sengaja jauh di depan (2031) supaya terisolasi
 * dari data seeder.
 */
class ClosingPreviewTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;
    use ClosingTestHelpers;

    private const TGL = '2031-03-10';

    // =====================================================================
    // Periode
    // =====================================================================

    public function test_rentang_bulanan_mulai_tanggal_26(): void
    {
        [$start, $end] = ClosingService::rentangBulanan(2026, 10, 26);
        $this->assertSame('2026-09-26', $start->toDateString());
        $this->assertSame('2026-10-25', $end->toDateString());

        // Periode Maret saat Februari pendek (tahun kabisat & bukan)
        [$start, $end] = ClosingService::rentangBulanan(2027, 3, 26);
        $this->assertSame('2027-02-26', $start->toDateString());
        $this->assertSame('2027-03-25', $end->toDateString());
    }

    public function test_rentang_bulanan_tanggal_1_bulan_kalender(): void
    {
        [$start, $end] = ClosingService::rentangBulanan(2026, 2, 1);
        $this->assertSame('2026-02-01', $start->toDateString());
        $this->assertSame('2026-02-28', $end->toDateString());
    }

    public function test_bulan_periode_untuk_tanggal_berjalan(): void
    {
        $this->assertSame('2026-10', ClosingService::bulanPeriodeUntuk(Carbon::parse('2026-10-17'), 26)->format('Y-m'));
        $this->assertSame('2026-11', ClosingService::bulanPeriodeUntuk(Carbon::parse('2026-10-26'), 26)->format('Y-m'));
        $this->assertSame('2026-10', ClosingService::bulanPeriodeUntuk(Carbon::parse('2026-10-31'), 1)->format('Y-m'));
    }

    public function test_resolve_periode_pakai_setting_dan_mingguan_senin_minggu(): void
    {
        PengaturanUmum::getSetting()->update(['tanggal_mulai_periode' => 26]);
        $s = app(ClosingService::class);

        $p = $s->resolvePeriode('bulanan', null, '2026-10');
        $this->assertSame('2026-09-26', $p['start']->toDateString());
        $this->assertSame('2026-10-25', $p['end']->toDateString());
        $this->assertStringContainsString('Oktober 2026', $p['label']);

        $p = $s->resolvePeriode('mingguan', '2026-10-15', null); // Kamis
        $this->assertSame('2026-10-12', $p['start']->toDateString());
        $this->assertSame('2026-10-18', $p['end']->toDateString());

        $p = $s->resolvePeriode('harian', '2026-10-15', null);
        $this->assertSame('2026-10-15', $p['start']->toDateString());
        $this->assertSame('2026-10-15', $p['end']->toDateString());
    }

    // =====================================================================
    // Skenario A — usaha 1 cabang tanpa pusat
    // =====================================================================

    public function test_usaha_satu_cabang_tanpa_pusat(): void
    {
        Cabang::where('tipe', 'gudang_pusat')->update(['tipe' => 'cabang']);
        $cab = $this->cabang('Toko Tunggal');
        $tunai = $this->kas($cab, 'Laci Kasir', 'tunai');
        $bank = $this->kas($cab, 'BCA', 'bank');

        // Saldo awal sebelum periode
        $this->trx($tunai, 'pemasukan', 'SALDO', 200000, '2031-03-01');

        // Penjualan: 1 selesai, 1 bill pending, 1 dibatalkan (2 terakhir tidak dihitung)
        $o = $this->order($cab, 100000, 40000);
        $this->order($cab, 50000, 0, 'pending');
        $this->order($cab, 70000, 0, 'dibatalkan');
        $this->trx($tunai, 'pemasukan', 'PENJ', 100000, self::TGL, ['referensi_type' => 'order', 'referensi_id' => $o->id]);

        $this->trx($tunai, 'pengeluaran', 'OPS', 10000);
        $this->trx($tunai, 'pengeluaran', 'PBB', 30000);

        // "Setoran" usaha 1 cabang = transfer antar kas laci → bank
        $out = $this->trx($tunai, 'pengeluaran', 'MUTASI-OUT', 20000, self::TGL, ['referensi_type' => 'transfer_antar_kas']);
        $in = $this->trx($bank, 'pemasukan', 'MUTASI-IN', 20000, self::TGL, ['referensi_type' => 'transfer_antar_kas', 'referensi_id' => $out->id]);
        $out->update(['referensi_id' => $in->id]);

        // Transaksi SESUDAH periode — tidak boleh mempengaruhi saldo akhir periode
        $this->trx($tunai, 'pengeluaran', 'OPS', 5000, '2031-03-11');

        $d = $this->hitung($cab);

        // Penjualan & laba rugi
        $this->assertSame(1, $d['penjualan']['jumlah_order']);
        $this->assertEquals(100000, $d['penjualan']['omzet_bersih']);
        $this->assertEquals(40000, $d['laba_rugi']['hpp']);
        $this->assertEquals(60000, $d['laba_rugi']['laba_kotor']);
        $this->assertEquals(10000, $d['laba_rugi']['total_beban'], 'Pembelian bahan tidak boleh dobel dengan HPP');
        $this->assertEquals(50000, $d['laba_rugi']['laba_bersih']);

        // Arus kas: penjualan masuk, OPS + PBB keluar, mutasi TIDAK ikut
        $this->assertEquals(100000, $d['arus_kas']['total_pemasukan']);
        $this->assertEquals(40000, $d['arus_kas']['total_pengeluaran']);

        // Mutasi internal tercantum
        $m = $this->mutasi($d, 'antar_kas');
        $this->assertEquals(20000, $m['keluar']);
        $this->assertEquals(20000, $m['masuk']);
        $this->assertCount(1, $d['mutasi']['detail']);
        $this->assertSame('internal', $d['mutasi']['detail'][0]['arah']);
        $this->assertStringContainsString('Laci Kasir', $d['mutasi']['detail'][0]['dari']);
        $this->assertStringContainsString('BCA', $d['mutasi']['detail'][0]['ke']);

        // Saldo kas per tanggal (dihitung mundur, abaikan transaksi tgl 11)
        $st = $this->saldoKas($d, $tunai);
        $this->assertEquals(200000, $st['saldo_awal']);
        $this->assertEquals(240000, $st['saldo_akhir']); // 200 + 100 - 10 - 30 - 20
        $sb = $this->saldoKas($d, $bank);
        $this->assertEquals(0, $sb['saldo_awal']);
        $this->assertEquals(20000, $sb['saldo_akhir']);
        $this->assertEquals(260000, $d['saldo_kas']['total']['saldo_akhir']);

        // Tanpa pusat → setoran via Transfer Antar Kas
        $this->assertFalse($d['setoran']['pakai_setoran_kasir']);
    }

    public function test_saldo_kas_rekonsiliasi_awal_plus_gerakan_sama_dengan_akhir(): void
    {
        $cab = $this->cabang('Toko Rekon');
        $tunai = $this->kas($cab, 'Laci', 'tunai');
        $this->trx($tunai, 'pemasukan', 'SALDO', 50000, '2031-02-01');
        $this->trx($tunai, 'pemasukan', 'LNY-IN', 12000, '2031-03-05');
        $this->trx($tunai, 'pengeluaran', 'GAJI', 7000, '2031-03-20');

        $d = $this->hitung($cab, '2031-03-01', '2031-03-31');
        $r = $this->saldoKas($d, $tunai);

        $this->assertEquals(50000, $r['saldo_awal']);
        $this->assertEquals(
            $r['saldo_akhir'],
            $r['saldo_awal'] + $r['masuk'] - $r['keluar'] + $r['mutasi_masuk'] - $r['mutasi_keluar']
        );
        $this->assertEquals(55000, $r['saldo_akhir']);
    }

    // =====================================================================
    // Skenario B — pusat + cabang
    // =====================================================================

    private function setupPusatCabang(): array
    {
        $pusat = $this->cabang('Pusat Test', 'gudang_pusat');
        $cab = $this->cabang('Outlet Test');
        $kasPusat = $this->kas($pusat, 'Kas Pusat', 'tunai');
        $kasCab = $this->kas($cab, 'Laci Outlet', 'tunai');
        $bankCab = $this->kas($cab, 'BRI Outlet', 'bank');

        $o = $this->order($cab, 80000, 30000);
        $this->trx($kasCab, 'pemasukan', 'PENJ', 80000, self::TGL, ['referensi_type' => 'order', 'referensi_id' => $o->id]);

        // Antar kas di dalam outlet
        $out = $this->trx($kasCab, 'pengeluaran', 'MUTASI-OUT', 5000, self::TGL, ['referensi_type' => 'transfer_antar_kas']);
        $in = $this->trx($bankCab, 'pemasukan', 'MUTASI-IN', 5000, self::TGL, ['referensi_type' => 'transfer_antar_kas', 'referensi_id' => $out->id]);
        $out->update(['referensi_id' => $in->id]);

        // Setoran Kasir outlet → pusat (sudah approve)
        $this->trx($kasCab, 'pengeluaran', 'SETORKSR-OUT', 30000, self::TGL, ['referensi_type' => 'setoran_kasir', 'referensi_id' => 987654]);
        $this->trx($kasPusat, 'pemasukan', 'SETORKSR-IN', 30000, self::TGL, ['referensi_type' => 'setoran_kasir', 'referensi_id' => 987654]);

        // Transfer Dana pusat → outlet, BELUM diterima
        $tOut = $this->trx($kasPusat, 'pengeluaran', 'SETOR-OUT', 15000, self::TGL, ['status_setoran' => 'menunggu_diterima']);
        $tIn = $this->trx($kasCab, 'pemasukan', 'SETOR-IN', 15000, self::TGL, ['status_setoran' => 'menunggu_diterima', 'setoran_pair_id' => $tOut->id]);
        $tOut->update(['setoran_pair_id' => $tIn->id]);

        return compact('pusat', 'cab', 'kasPusat', 'kasCab', 'bankCab');
    }

    public function test_pusat_cabang_closing_per_cabang_mencantumkan_semua_mutasi(): void
    {
        ['cab' => $cab, 'kasCab' => $kasCab, 'bankCab' => $bankCab] = $this->setupPusatCabang();

        $d = $this->hitung($cab);

        $this->assertEquals(80000, $d['penjualan']['omzet_bersih']);
        $this->assertEquals(0, $d['laba_rugi']['total_beban'], 'Setoran/mutasi bukan beban');
        $this->assertEquals(0, $d['arus_kas']['total_pengeluaran'], 'Setoran/mutasi bukan pengeluaran');

        // Antar kas dalam outlet tercantum (keluar & masuk)
        $ak = $this->mutasi($d, 'antar_kas');
        $this->assertEquals(5000, $ak['keluar']);
        $this->assertEquals(5000, $ak['masuk']);

        // Setoran ke pusat: hanya sisi keluar di closing outlet
        $sk = $this->mutasi($d, 'setoran_kasir');
        $this->assertEquals(30000, $sk['keluar']);
        $this->assertEquals(0, $sk['masuk']);

        // Transfer dana belum diterima: tidak menambah saldo kas outlet
        $td = $this->mutasi($d, 'transfer_dana');
        $this->assertEquals(15000, $td['dalam_perjalanan']);
        $this->assertEquals(0, $td['masuk']);
        $this->assertEquals(15000, $d['setoran']['transfer_dana_belum_diterima']);

        $this->assertEquals(45000, $this->saldoKas($d, $kasCab)['saldo_akhir']); // 80 - 5 - 30
        $this->assertEquals(5000, $this->saldoKas($d, $bankCab)['saldo_akhir']);
        $this->assertTrue($d['setoran']['pakai_setoran_kasir']);
        $this->assertCount(3, $d['mutasi']['detail']);
    }

    public function test_pusat_cabang_keseluruhan_mutasi_saling_meniadakan(): void
    {
        ['pusat' => $pusat, 'cab' => $cab, 'kasPusat' => $kasPusat] = $this->setupPusatCabang();

        $d = $this->hitung(null);

        $sk = $this->mutasi($d, 'setoran_kasir');
        $this->assertEquals($sk['keluar'], $sk['masuk']);
        $ak = $this->mutasi($d, 'antar_kas');
        $this->assertEquals($ak['keluar'], $ak['masuk']);

        // Pasangan setoran kasir tampil 1 baris, arah internal
        $setoranRows = collect($d['mutasi']['detail'])->where('jenis', 'Setoran Kasir');
        $this->assertCount(1, $setoranRows);
        $this->assertSame('internal', $setoranRows->first()['arah']);

        // Saldo pusat: +30 setoran, -15 transfer dana yang sudah keluar
        $this->assertEquals(15000, $this->saldoKas($d, $kasPusat)['saldo_akhir']);

        // Ringkasan per cabang ada untuk kedua cabang
        $perCabang = collect($d['per_cabang']);
        $this->assertEquals(80000, $perCabang->firstWhere('cabang_id', $cab->id)['omzet']);
        $this->assertNotNull($perCabang->firstWhere('cabang_id', $pusat->id));
    }

    // =====================================================================
    // HTTP & hak akses
    // =====================================================================

    public function test_owner_bisa_buka_preview_cabang_dan_keseluruhan(): void
    {
        ['cab' => $cab] = $this->setupPusatCabang();
        $owner = $this->buatUser('owner');

        $this->actingAs($owner)
            ->get(route('closing.index', ['jenis' => 'harian', 'tanggal' => self::TGL, 'cabang_id' => $cab->id]))
            ->assertOk()
            ->assertSee('PREVIEW')
            ->assertSee('Mutasi Internal')
            ->assertSee('Antar kas dalam cabang')
            ->assertSee('Laci Outlet');

        $this->actingAs($owner)
            ->get(route('closing.index', ['jenis' => 'harian', 'tanggal' => self::TGL, 'cabang_id' => 'semua']))
            ->assertOk()
            ->assertSee('Ringkasan per Cabang')
            ->assertSee('Semua Cabang');

        foreach (['mingguan', 'bulanan'] as $jenis) {
            $this->actingAs($owner)->get(route('closing.index', ['jenis' => $jenis]))->assertOk();
        }
    }

    public function test_kasir_hanya_bisa_lihat_cabangnya_sendiri(): void
    {
        ['pusat' => $pusat, 'cab' => $cab] = $this->setupPusatCabang();
        $kasir = $this->buatUser('kasir', $cab->id);

        $res = $this->actingAs($kasir)
            ->get(route('closing.index', ['jenis' => 'harian', 'tanggal' => self::TGL, 'cabang_id' => $pusat->id]));

        $res->assertOk();
        $res->assertViewHas('cabangId', $cab->id); // permintaan cabang lain diabaikan
        $res->assertDontSee('Semua Cabang (Keseluruhan)');

        $this->actingAs($kasir)
            ->get(route('closing.index', ['cabang_id' => 'semua']))
            ->assertOk()
            ->assertViewHas('cabangId', $cab->id);
    }

    public function test_role_tanpa_permission_ditolak(): void
    {
        $operator = $this->buatUser('operator_produksi', $this->cabangPertama()->id);

        $this->actingAs($operator)->get(route('closing.index'))->assertForbidden();
    }

    public function test_menu_sidebar_tampil_untuk_admin_pusat(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $this->actingAs($admin)->get(route('closing.index'))
            ->assertOk()
            ->assertSee('Closing / Tutup Buku');
    }

    // =====================================================================
    // Pengaturan Umum
    // =====================================================================

    public function test_pengaturan_tanggal_mulai_periode_tersimpan_dan_divalidasi(): void
    {
        $owner = $this->buatUser('owner');

        $this->actingAs($owner)->put(route('pengaturan.umum.update'), [
            'nama_perusahaan' => "D'mentai", 'tanggal_mulai_periode' => 26,
        ])->assertRedirect();
        $this->assertSame(26, (int) PengaturanUmum::first()->tanggal_mulai_periode);

        $this->actingAs($owner)->put(route('pengaturan.umum.update'), [
            'nama_perusahaan' => "D'mentai", 'tanggal_mulai_periode' => 31,
        ])->assertSessionHasErrors('tanggal_mulai_periode');

        $this->actingAs($owner)->get(route('pengaturan.umum'))
            ->assertOk()
            ->assertSee('Tanggal Mulai Periode Bulanan');
    }
}
