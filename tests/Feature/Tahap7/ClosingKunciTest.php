<?php

namespace Tests\Feature\Tahap7;

use App\Exceptions\PeriodeTerkunciException;
use App\Models\Cabang;
use App\Models\Closing;
use App\Models\Kas;
use App\Models\Order;
use App\Models\PengaturanUmum;
use App\Models\Setoran;
use App\Models\SetoranDetail;
use App\Models\TransaksiKeuangan;
use App\Services\ClosingArsipService;
use App\Services\SetoranKasirService;
use App\Support\ClosingLock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\Feature\Tahap7\Concerns\ClosingTestHelpers;
use Tests\TestCase;

/**
 * Closing / Tutup Buku Tahap 2-3 (2026-10-17) — simpan snapshot, kunci
 * periode, buka kunci, tutup buku keseluruhan, rekap mingguan, export.
 */
class ClosingKunciTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;
    use ClosingTestHelpers;

    private const TGL = '2026-03-10';

    protected function setUp(): void
    {
        parent::setUp();
        PengaturanUmum::getSetting()->update(['tanggal_mulai_periode' => 1]);
    }

    private function arsip(): ClosingArsipService
    {
        return app(ClosingArsipService::class);
    }

    /** Cabang + kas tunai berisi transaksi penjualan 100rb di TGL. */
    private function setupCabang(string $nama = 'Outlet Kunci'): array
    {
        $cab = $this->cabang($nama);
        $tunai = $this->kas($cab, 'Laci ' . $nama, 'tunai');
        $bank = $this->kas($cab, 'Bank ' . $nama, 'bank');
        $o = $this->order($cab, 100000, 40000);
        $this->trx($tunai, 'pemasukan', 'PENJ', 100000, self::TGL, ['referensi_type' => 'order', 'referensi_id' => $o->id]);

        return compact('cab', 'tunai', 'bank', 'o');
    }

    // =====================================================================
    // Closing harian
    // =====================================================================

    public function test_kasir_closing_harian_menyimpan_snapshot_dan_uang_fisik(): void
    {
        ['cab' => $cab, 'tunai' => $tunai] = $this->setupCabang();
        $kasir = $this->buatUser('kasir', $cab->id);

        $res = $this->actingAs($kasir)->post(route('closing.harian.store'), [
            'cabang_id' => $cab->id, 'tanggal' => self::TGL,
            'fisik' => [$tunai->id => 95000], 'catatan' => 'Uang kurang 5rb',
        ]);

        $closing = Closing::where('cabang_id', $cab->id)->firstOrFail();
        $res->assertRedirect(route('closing.show', $closing));
        $this->assertSame('harian', $closing->jenis);
        $this->assertTrue($closing->isFinal());
        $this->assertEquals(100000, $closing->omzet);
        $this->assertEquals(60000, $closing->laba_bersih);
        $this->assertEquals(-5000, $closing->total_selisih_fisik);

        $kasRow = $closing->kasRows()->where('kas_id', $tunai->id)->first();
        $this->assertEquals(100000, $kasRow->saldo_akhir);
        $this->assertEquals(95000, $kasRow->saldo_fisik);
        $this->assertEquals(-5000, $kasRow->selisih);

        $this->actingAs($kasir)->get(route('closing.show', $closing))
            ->assertOk()->assertSee('Hitung Uang Fisik Laci')->assertSee('Terkunci')->assertDontSee('modalBukaKunci', false);
    }

    public function test_closing_harian_wajib_isi_uang_fisik_semua_kas_tunai(): void
    {
        ['cab' => $cab] = $this->setupCabang();
        $kasir = $this->buatUser('kasir', $cab->id);

        $this->actingAs($kasir)->post(route('closing.harian.store'), ['cabang_id' => $cab->id, 'tanggal' => self::TGL])
            ->assertSessionHas('error');

        $this->assertSame(0, Closing::where('cabang_id', $cab->id)->count());
    }

    public function test_closing_harian_tidak_boleh_dobel_atau_tanggal_akan_datang(): void
    {
        ['cab' => $cab, 'tunai' => $tunai] = $this->setupCabang();
        $owner = $this->buatUser('owner');

        $this->arsip()->simpanHarian($cab->id, Carbon::parse(self::TGL), $owner->id, [$tunai->id => 100000]);

        $this->actingAs($owner)->post(route('closing.harian.store'), [
            'cabang_id' => $cab->id, 'tanggal' => self::TGL, 'fisik' => [$tunai->id => 100000],
        ])->assertSessionHas('error');

        $this->actingAs($owner)->post(route('closing.harian.store'), [
            'cabang_id' => $cab->id, 'tanggal' => now()->addDay()->toDateString(), 'fisik' => [$tunai->id => 0],
        ])->assertSessionHas('error');

        $this->assertSame(1, Closing::where('cabang_id', $cab->id)->count());
    }

    public function test_kasir_tidak_bisa_closing_cabang_lain(): void
    {
        ['cab' => $cab, 'tunai' => $tunai] = $this->setupCabang();
        $lain = $this->cabang('Outlet Lain');
        $kasir = $this->buatUser('kasir', $lain->id);

        $this->actingAs($kasir)->post(route('closing.harian.store'), [
            'cabang_id' => $cab->id, 'tanggal' => self::TGL, 'fisik' => [$tunai->id => 1],
        ])->assertForbidden();
    }

    // =====================================================================
    // Kunci periode
    // =====================================================================

    public function test_setelah_closing_transaksi_dan_order_tanggal_itu_terkunci(): void
    {
        ['cab' => $cab, 'tunai' => $tunai, 'o' => $order] = $this->setupCabang();
        $owner = $this->buatUser('owner');
        $manual = $this->trx($tunai, 'pengeluaran', 'OPS', 7000);
        $this->arsip()->simpanHarian($cab->id, Carbon::parse(self::TGL), $owner->id, [$tunai->id => 93000]);

        // Tambah transaksi baru di tanggal terkunci
        try {
            $this->trx($tunai, 'pengeluaran', 'OPS', 1000);
            $this->fail('Seharusnya ditolak');
        } catch (PeriodeTerkunciException $e) {
            $this->assertStringContainsString('sudah di-closing', $e->getMessage());
        }

        // Pindahkan transaksi dari tanggal terbuka ke tanggal terkunci
        $bebas = $this->trx($tunai, 'pengeluaran', 'OPS', 2000, '2026-03-12');
        $this->expectException(PeriodeTerkunciException::class);
        try {
            $bebas->update(['tanggal_transaksi' => self::TGL]);
        } finally {
            // Hapus transaksi terkunci via HTTP → pesan, bukan 500, data & saldo utuh
            $saldoSebelum = (float) $tunai->fresh()->saldo_sekarang;
            $this->actingAs($owner)->delete(route('keuangan.destroy', $manual))
                ->assertRedirect()->assertSessionHas('error');
            $this->assertNotSoftDeleted('transaksi_keuangans', ['id' => $manual->id]);
            $this->assertEquals($saldoSebelum, (float) $tunai->fresh()->saldo_sekarang);

            // Batalkan order terkunci via HTTP → ditolak, status tetap
            $this->actingAs($owner)->post(route('penjualan.batalkan', $order), ['alasan_pembatalan_kategori' => 'lainnya', 'alasan_pembatalan_detail' => 'test'])
                ->assertRedirect();
            $this->assertSame('selesai', $order->fresh()->status->value);
            $this->assertSame(1, TransaksiKeuangan::where('referensi_type', 'order')->where('referensi_id', $order->id)->count());
        }
    }

    public function test_kolom_non_angka_tetap_boleh_diubah_saat_terkunci(): void
    {
        ['cab' => $cab, 'tunai' => $tunai, 'o' => $order] = $this->setupCabang();
        $owner = $this->buatUser('owner');
        $t = $this->trx($tunai, 'pengeluaran', 'OPS', 7000);
        $this->arsip()->simpanHarian($cab->id, Carbon::parse(self::TGL), $owner->id, [$tunai->id => 93000]);

        $t->update(['catatan' => 'catatan baru', 'status_setoran' => null]);
        $order->update(['catatan' => 'catatan order']);

        $this->assertSame('catatan baru', $t->fresh()->catatan);
        $this->assertSame('catatan order', $order->fresh()->catatan);
    }

    public function test_cabang_lain_dan_tanggal_lain_tidak_ikut_terkunci(): void
    {
        ['cab' => $cab, 'tunai' => $tunai] = $this->setupCabang();
        ['cab' => $cab2, 'tunai' => $tunai2] = $this->setupCabang('Outlet Dua');
        $owner = $this->buatUser('owner');
        $this->arsip()->simpanHarian($cab->id, Carbon::parse(self::TGL), $owner->id, [$tunai->id => 100000]);

        $this->trx($tunai2, 'pengeluaran', 'OPS', 1000); // cabang lain, tanggal sama
        $this->trx($tunai, 'pengeluaran', 'OPS', 1000, '2026-03-11'); // cabang sama, besoknya
        Order::create([
            'cabang_id' => $cab->id, 'nomor_order' => 'CLS-BEBAS-' . uniqid(), 'tanggal_order' => '2026-03-11',
            'tipe_order' => 'penjualan', 'status' => 'selesai', 'total_bayar' => 1, 'jumlah_bayar' => 1, 'kembalian' => 0, 'tipe_pembayaran' => 'tunai',
        ]);

        $this->assertTrue(ClosingLock::terkunci($cab->id, self::TGL));
        $this->assertFalse(ClosingLock::terkunci($cab2->id, self::TGL));
        $this->assertFalse(ClosingLock::terkunci($cab->id, '2026-03-11'));
    }

    public function test_snapshot_tidak_berubah_walau_data_berubah_setelah_buka_kunci(): void
    {
        ['cab' => $cab, 'tunai' => $tunai] = $this->setupCabang();
        $owner = $this->buatUser('owner');
        $v1 = $this->arsip()->simpanHarian($cab->id, Carbon::parse(self::TGL), $owner->id, [$tunai->id => 100000]);

        $this->actingAs($owner)->post(route('closing.buka', $v1), ['alasan' => 'Ada biaya operasional tertinggal'])
            ->assertRedirect(route('closing.show', $v1));
        $this->assertSame('dibuka', $v1->fresh()->status);
        $this->assertFalse(ClosingLock::terkunci($cab->id, self::TGL));

        $this->trx($tunai, 'pengeluaran', 'OPS', 10000); // koreksi setelah dibuka
        $v2 = $this->arsip()->simpanHarian($cab->id, Carbon::parse(self::TGL), $owner->id, [$tunai->id => 90000]);

        $this->assertSame(2, $v2->versi);
        $this->assertEquals(60000, $v1->fresh()->laba_bersih); // arsip lama beku
        $this->assertEquals(50000, $v2->laba_bersih);
        $this->assertEquals(60000, $v1->fresh()->data['laba_rugi']['laba_bersih']);

        $this->actingAs($owner)->get(route('closing.show', $v1))->assertOk()->assertSee('Kunci sudah dibuka')->assertSee('Versi 2');
    }

    // =====================================================================
    // Buka kunci
    // =====================================================================

    public function test_buka_kunci_hanya_owner_dan_wajib_alasan(): void
    {
        ['cab' => $cab, 'tunai' => $tunai] = $this->setupCabang();
        $owner = $this->buatUser('owner');
        $closing = $this->arsip()->simpanHarian($cab->id, Carbon::parse(self::TGL), $owner->id, [$tunai->id => 100000]);

        $this->actingAs($this->buatUser('admin_pusat'))->post(route('closing.buka', $closing), ['alasan' => 'alasan cukup panjang'])
            ->assertForbidden();
        $this->actingAs($owner)->post(route('closing.buka', $closing), ['alasan' => 'pendek'])
            ->assertSessionHasErrors('alasan');

        $this->assertTrue($closing->fresh()->isFinal());
    }

    // =====================================================================
    // Tutup buku bulanan & keseluruhan
    // =====================================================================

    public function test_tutup_buku_bulanan_mengunci_sebulan_dan_buka_ikut_buka_harian(): void
    {
        ['cab' => $cab, 'tunai' => $tunai] = $this->setupCabang();
        $owner = $this->buatUser('owner');
        $harian = $this->arsip()->simpanHarian($cab->id, Carbon::parse(self::TGL), $owner->id, [$tunai->id => 100000]);

        $this->actingAs($owner)->post(route('closing.bulanan.store'), ['cabang_id' => $cab->id, 'bulan' => '2026-03'])
            ->assertRedirect();
        $bulanan = Closing::where('cabang_id', $cab->id)->where('jenis', 'bulanan')->firstOrFail();
        $this->assertSame('2026-03-01', $bulanan->periode_mulai->toDateString());
        $this->assertSame('2026-03-31', $bulanan->periode_selesai->toDateString());
        $this->assertTrue(ClosingLock::terkunci($cab->id, '2026-03-25'));

        // Harian tidak bisa dibuka sendiri selama bulanan terkunci
        $this->actingAs($owner)->post(route('closing.buka', $harian), ['alasan' => 'coba buka harian saja'])
            ->assertSessionHas('error');
        $this->assertTrue($harian->fresh()->isFinal());

        // Buka bulanan → harian ikut terbuka, periode bebas
        $this->arsip()->bukaKunci($bulanan, $owner->id, 'Koreksi pembelian bulan Maret');
        $this->assertSame('dibuka', $harian->fresh()->status);
        $this->assertFalse(ClosingLock::terkunci($cab->id, self::TGL));
    }

    public function test_tutup_buku_bulanan_ditolak_kalau_periode_belum_berakhir(): void
    {
        ['cab' => $cab] = $this->setupCabang();
        $owner = $this->buatUser('owner');

        $this->actingAs($owner)->post(route('closing.bulanan.store'), [
            'cabang_id' => $cab->id, 'bulan' => now()->addMonth()->format('Y-m'),
        ])->assertSessionHas('error');

        $this->assertSame(0, Closing::where('cabang_id', $cab->id)->count());
    }

    public function test_periode_26_sampai_25_dipakai_saat_tutup_buku(): void
    {
        PengaturanUmum::getSetting()->update(['tanggal_mulai_periode' => 26]);
        ['cab' => $cab] = $this->setupCabang();
        $owner = $this->buatUser('owner');

        $c = $this->arsip()->simpanBulanan($cab->id, '2026-03', $owner->id);

        $this->assertSame('2026-02-26', $c->periode_mulai->toDateString());
        $this->assertSame('2026-03-25', $c->periode_selesai->toDateString());
        $this->assertTrue(ClosingLock::terkunci($cab->id, '2026-02-27'));
        $this->assertFalse(ClosingLock::terkunci($cab->id, '2026-03-26'));
    }

    public function test_keseluruhan_hanya_setelah_semua_cabang_tutup_buku_dan_ikut_dibuka(): void
    {
        ['cab' => $a] = $this->setupCabang('Outlet A');
        ['cab' => $b] = $this->setupCabang('Outlet B');
        Cabang::whereNotIn('id', [$a->id, $b->id])->update(['is_active' => false]);
        $owner = $this->buatUser('owner');

        $this->arsip()->simpanBulanan($a->id, '2026-03', $owner->id);
        $this->actingAs($owner)->post(route('closing.bulanan.store'), ['cabang_id' => 'semua', 'bulan' => '2026-03'])
            ->assertSessionHas('error');

        $bulananB = $this->arsip()->simpanBulanan($b->id, '2026-03', $owner->id);
        $this->actingAs($owner)->post(route('closing.bulanan.store'), ['cabang_id' => 'semua', 'bulan' => '2026-03'])
            ->assertRedirect();
        $kons = Closing::whereNull('cabang_id')->firstOrFail();
        $this->assertEquals(200000, $kons->omzet);
        $this->assertCount(2, $kons->data['per_cabang']);

        $this->actingAs($owner)->get(route('closing.show', $kons))->assertOk()->assertSee('Tutup Buku Keseluruhan');

        $this->arsip()->bukaKunci($bulananB, $owner->id, 'Koreksi Outlet B bulan Maret');
        $this->assertSame('dibuka', $kons->fresh()->status);
    }

    // =====================================================================
    // Setoran Kasir & tanggal terkunci
    // =====================================================================

    public function test_approve_setoran_kasir_untuk_tanggal_terkunci_dicatat_di_tanggal_approve(): void
    {
        ['cab' => $cab, 'tunai' => $tunai] = $this->setupCabang();
        $pusat = Cabang::gudangPusat()->firstOrFail();
        $kasPusat = $this->kas($pusat, 'Kas Pusat Tes', 'tunai');
        Kas::where('cabang_id', $pusat->id)->where('id', '!=', $kasPusat->id)->update(['default_untuk' => null]);
        $kasPusat->update(['default_untuk' => 'tunai']);
        $tunai->update(['default_untuk' => 'tunai']);
        $owner = $this->buatUser('owner');

        $setoran = Setoran::create([
            'cabang_id' => $cab->id, 'tanggal' => self::TGL, 'disubmit_oleh' => $owner->id, 'disubmit_pada' => now(),
            'total_penjualan_sistem' => 100000, 'total_disetor' => 100000, 'selisih' => 0, 'status' => 'menunggu',
        ]);
        SetoranDetail::create(['setoran_id' => $setoran->id, 'metode' => 'tunai', 'jumlah_sistem' => 100000, 'jumlah_fisik' => 100000]);

        $this->arsip()->simpanHarian($cab->id, Carbon::parse(self::TGL), $owner->id, [$tunai->id => 100000]);

        app(SetoranKasirService::class)->approveSetoran($setoran, $owner->id);

        $out = TransaksiKeuangan::where('referensi_type', 'setoran_kasir')->where('referensi_id', $setoran->id)->where('tipe', 'pengeluaran')->firstOrFail();
        $this->assertSame(now()->toDateString(), $out->tanggal_transaksi->toDateString());
    }

    // =====================================================================
    // Halaman, riwayat, rekap mingguan, export
    // =====================================================================

    public function test_halaman_preview_tampil_tombol_dan_status_terkunci(): void
    {
        ['cab' => $cab, 'tunai' => $tunai] = $this->setupCabang();
        $owner = $this->buatUser('owner');
        $q = ['jenis' => 'harian', 'tanggal' => self::TGL, 'cabang_id' => $cab->id];

        $this->actingAs($owner)->get(route('closing.index', $q))
            ->assertOk()->assertSee('Closing Harian')->assertSee('modalClosingHarian', false)->assertSee('Laci Outlet Kunci');

        $this->arsip()->simpanHarian($cab->id, Carbon::parse(self::TGL), $owner->id, [$tunai->id => 100000]);

        $this->actingAs($owner)->get(route('closing.index', $q))
            ->assertOk()->assertSee('Sudah di-closing')->assertDontSee('modalClosingHarian', false);

        $this->actingAs($owner)->get(route('closing.index', ['jenis' => 'mingguan', 'tanggal' => self::TGL, 'cabang_id' => $cab->id]))
            ->assertOk()->assertSee('Status Closing Harian Minggu Ini');

        $this->actingAs($owner)->get(route('closing.index', ['jenis' => 'bulanan', 'bulan' => '2026-03', 'cabang_id' => $cab->id]))
            ->assertOk()->assertSee('Tutup Buku Bulanan');
    }

    public function test_riwayat_kasir_hanya_cabangnya_dan_tidak_bisa_lihat_arsip_cabang_lain(): void
    {
        ['cab' => $a, 'tunai' => $ta] = $this->setupCabang('Outlet RA');
        ['cab' => $b, 'tunai' => $tb] = $this->setupCabang('Outlet RB');
        $owner = $this->buatUser('owner');
        $ca = $this->arsip()->simpanHarian($a->id, Carbon::parse(self::TGL), $owner->id, [$ta->id => 100000]);
        $cb = $this->arsip()->simpanHarian($b->id, Carbon::parse(self::TGL), $owner->id, [$tb->id => 100000]);
        $kasir = $this->buatUser('kasir', $a->id);

        $this->actingAs($kasir)->get(route('closing.riwayat'))
            ->assertOk()->assertSee(route('closing.show', $ca))->assertDontSee(route('closing.show', $cb));
        $this->actingAs($kasir)->get(route('closing.show', $cb))->assertForbidden();
        $this->actingAs($owner)->get(route('closing.riwayat'))->assertOk()->assertSee('Outlet RB');
    }

    public function test_export_excel_dan_pdf_preview_maupun_arsip(): void
    {
        ['cab' => $cab, 'tunai' => $tunai] = $this->setupCabang();
        $owner = $this->buatUser('owner');
        $closing = $this->arsip()->simpanHarian($cab->id, Carbon::parse(self::TGL), $owner->id, [$tunai->id => 98000]);
        $q = ['jenis' => 'harian', 'tanggal' => self::TGL, 'cabang_id' => $cab->id];

        $xlsx = $this->actingAs($owner)->get(route('closing.export', $q + ['format' => 'excel']));
        $xlsx->assertOk();
        $this->assertStringContainsString('spreadsheetml', $xlsx->headers->get('Content-Type'));

        $pdf = $this->actingAs($owner)->get(route('closing.export', $q + ['format' => 'pdf']));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));

        $this->actingAs($owner)->get(route('closing.show.export', [$closing, 'format' => 'excel']))->assertOk();
        $this->actingAs($owner)->get(route('closing.show.export', [$closing, 'format' => 'pdf']))->assertOk();

        $this->actingAs($owner)->get(route('closing.export', ['jenis' => 'bulanan', 'bulan' => '2026-03', 'cabang_id' => 'semua', 'format' => 'excel']))->assertOk();

        // Kasir tidak punya closing.export
        $this->actingAs($this->buatUser('kasir', $cab->id))->get(route('closing.export', $q + ['format' => 'excel']))->assertForbidden();
    }
}
