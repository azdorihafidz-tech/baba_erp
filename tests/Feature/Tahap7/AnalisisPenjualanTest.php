<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Order;
use App\Services\AnalisisPenjualanService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Sprint 4.32 (2026-10-05) — 5 metrik analisis penjualan di 3 halaman.
 *
 * Scope test:
 * - Helper `fmt_rupiah_singkat()` 3 tier format.
 * - Service: query optimal (sum/count group-by date), hitungan 5 metrik akurat,
 *   edge case (data kosong, 1 hari aktif), filter cabang, cache 5 menit.
 * - HTTP: section component tampil di Dashboard Pusat/Cabang + Laporan Penjualan.
 * - Preset dropdown Laporan Penjualan auto-apply ke rentang tanggal.
 */
class AnalisisPenjualanTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    protected function setUp(): void
    {
        parent::setUp();
        // DB test bisa kosong cabang (bug pre-existing erp_dimsum_test, lihat chat log).
        // Pastikan minimum 2 cabang ada supaya cabangPertama()/cabangKedua() tidak throw.
        if (Cabang::count() < 2) {
            Cabang::firstOrCreate(['kode_cabang' => 'TST-AP1'], [
                'nama_cabang' => 'Cabang Test AP 1', 'tipe' => \App\Enums\TipeCabang::Cabang, 'is_active' => true,
            ]);
            Cabang::firstOrCreate(['kode_cabang' => 'TST-AP2'], [
                'nama_cabang' => 'Cabang Test AP 2', 'tipe' => \App\Enums\TipeCabang::Cabang, 'is_active' => true,
            ]);
        }
    }

    private function orderPada(Carbon $tanggal, float $totalBayar, ?int $cabangId = null): Order
    {
        $cabang = $cabangId ?: $this->cabangPertama()->id;
        return Order::create([
            'nomor_order'    => 'AP-' . Str::uuid()->toString(),
            'tanggal_order'  => $tanggal->toDateString(),
            'cabang_id'      => $cabang,
            'kasir_id'       => auth()->id() ?? \App\Models\User::query()->value('id'),
            'tipe_order'     => 'penjualan',
            'tipe_transaksi' => 'dine_in',
            'status'         => 'selesai',
            'subtotal'       => $totalBayar,
            'total_bayar'    => $totalBayar,
            'jumlah_bayar'   => $totalBayar,
            'kembalian'      => 0,
        ]);
    }

    public function test_fmt_rupiah_singkat_3_tier(): void
    {
        $this->assertSame('Rp 50.000', fmt_rupiah_singkat(50000));
        $this->assertSame('Rp 99.999', fmt_rupiah_singkat(99999));
        $this->assertSame('Rp 100rb', fmt_rupiah_singkat(100000));
        $this->assertSame('Rp 850rb', fmt_rupiah_singkat(850000));
        $this->assertSame('Rp 1jt', fmt_rupiah_singkat(1000000));
        $this->assertSame('Rp 1,5jt', fmt_rupiah_singkat(1500000));
        $this->assertSame('Rp 8,5jt', fmt_rupiah_singkat(8500000));
        $this->assertSame('Rp 0', fmt_rupiah_singkat(0));
    }

    public function test_service_hitung_5_metrik_akurat(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $this->actingAs($user);

        $cab = $this->cabangPertama()->id;
        $hariA = Carbon::parse('2026-05-01');
        $hariB = Carbon::parse('2026-05-02');
        $hariC = Carbon::parse('2026-05-03');

        // Hari A: 2 order (100rb + 200rb = 300rb)
        $this->orderPada($hariA, 100000, $cab);
        $this->orderPada($hariA, 200000, $cab);
        // Hari B: 3 order (50rb + 50rb + 100rb = 200rb)  — jumlah tertinggi
        $this->orderPada($hariB, 50000, $cab);
        $this->orderPada($hariB, 50000, $cab);
        $this->orderPada($hariB, 100000, $cab);
        // Hari C: 1 order 500rb — nilai tertinggi
        $this->orderPada($hariC, 500000, $cab);

        $svc = app(AnalisisPenjualanService::class);
        Cache::flush();
        $data = $svc->hitung($cab, Carbon::parse('2026-05-01')->startOfDay(), Carbon::parse('2026-05-03')->endOfDay());

        // Total: 1_000_000, 6 trx, 3 hari aktif
        $this->assertSame(1000000.0, $data['total_omzet']);
        $this->assertSame(6, $data['total_transaksi']);
        $this->assertSame(3, $data['hari_aktif']);

        // Rata-rata nilai transaksi = 1jt / 6 = 166.666,67
        $this->assertEqualsWithDelta(166666.67, $data['rata_rata_nilai_transaksi'], 0.01);
        // Rata-rata jumlah transaksi = 6 / 3 = 2
        $this->assertSame(2.0, $data['rata_rata_jumlah_transaksi']);
        // Hari nilai tertinggi = C (500rb)
        $this->assertSame($hariC->toDateString(), $data['hari_nilai_tertinggi']['tanggal']);
        $this->assertSame(500000.0, $data['hari_nilai_tertinggi']['nilai']);
        // Hari jumlah tertinggi = B (3 trx)
        $this->assertSame($hariB->toDateString(), $data['hari_jumlah_tertinggi']['tanggal']);
        $this->assertSame(3, $data['hari_jumlah_tertinggi']['nilai']);
        // Rata-rata omzet harian = 1jt / 3 = 333.333,33
        $this->assertEqualsWithDelta(333333.33, $data['rata_rata_omzet_harian'], 0.01);
    }

    public function test_service_data_kosong_tidak_div_by_zero(): void
    {
        $this->buatUser('owner', $this->cabangPertama()->id);
        $svc = app(AnalisisPenjualanService::class);
        Cache::flush();

        $data = $svc->hitung(null, Carbon::parse('2026-01-01')->startOfDay(), Carbon::parse('2026-01-07')->endOfDay());

        $this->assertEquals(0, $data['rata_rata_nilai_transaksi']);
        $this->assertEquals(0, $data['rata_rata_jumlah_transaksi']);
        $this->assertSame(0, $data['total_transaksi']);
        $this->assertSame(0, $data['hari_aktif']);
        $this->assertNull($data['hari_nilai_tertinggi']['tanggal']);
        $this->assertNull($data['hari_jumlah_tertinggi']['tanggal']);
    }

    public function test_service_filter_cabang_isolate(): void
    {
        $u = $this->buatUser('owner', $this->cabangPertama()->id);
        $this->actingAs($u);
        $cabA = $this->cabangPertama()->id;
        $cabB = $this->cabangKedua()->id;
        $tgl = Carbon::parse('2026-06-15');

        $this->orderPada($tgl, 100000, $cabA);
        $this->orderPada($tgl, 500000, $cabB);

        Cache::flush();
        $svc = app(AnalisisPenjualanService::class);

        $dataA = $svc->hitung($cabA, $tgl->copy()->startOfDay(), $tgl->copy()->endOfDay());
        $this->assertSame(100000.0, $dataA['total_omzet']);

        $dataB = $svc->hitung($cabB, $tgl->copy()->startOfDay(), $tgl->copy()->endOfDay());
        $this->assertSame(500000.0, $dataB['total_omzet']);

        $dataAll = $svc->hitung(null, $tgl->copy()->startOfDay(), $tgl->copy()->endOfDay());
        $this->assertSame(600000.0, $dataAll['total_omzet']);
    }

    public function test_service_cache_hit_tidak_rekompute(): void
    {
        $u = $this->buatUser('owner', $this->cabangPertama()->id);
        $this->actingAs($u);
        $cab = $this->cabangPertama()->id;
        $tgl = Carbon::parse('2026-07-01');

        Cache::flush();
        $svc = app(AnalisisPenjualanService::class);

        $this->orderPada($tgl, 100000, $cab);
        $pertama = $svc->hitung($cab, $tgl->copy()->startOfDay(), $tgl->copy()->endOfDay());

        // Tambah order setelah cache populated — hasil kedua harus SAMA (dari cache)
        $this->orderPada($tgl, 999999, $cab);
        $kedua = $svc->hitung($cab, $tgl->copy()->startOfDay(), $tgl->copy()->endOfDay());

        $this->assertSame($pertama['total_omzet'], $kedua['total_omzet']);
    }

    public function test_preset_periode_resolve_benar(): void
    {
        $hari = AnalisisPenjualanService::presetPeriode('hari_ini');
        $this->assertSame('Hari Ini', $hari['label']);
        $this->assertTrue($hari['dari']->isToday());

        $tujuh = AnalisisPenjualanService::presetPeriode('7_hari');
        $this->assertEqualsWithDelta(7, $tujuh['dari']->diffInDays($tujuh['sampai']) + 0.01, 0.5);

        $bulan = AnalisisPenjualanService::presetPeriode('bulan_ini');
        $this->assertSame(1, $bulan['dari']->day);

        $invalid = AnalisisPenjualanService::presetPeriode('xyz_tidak_valid');
        $this->assertSame('Bulan Ini', $invalid['label']);
    }

    public function test_dashboard_pusat_render_section_analisis(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $response = $this->actingAs($user)->get(route('dashboard.pusat'));
        $response->assertOk();
        $response->assertSee('Analisis Penjualan', false);
        $response->assertSee('Rata-rata Nilai Transaksi', false);
        $response->assertSee('Rata-rata Jumlah Transaksi', false);
        $response->assertSee('Hari Nilai Tertinggi', false);
        $response->assertSee('Hari Jumlah Tertinggi', false);
        $response->assertSee('Rata-rata Omzet Harian', false);
    }

    public function test_dashboard_cabang_render_section_analisis_scoped(): void
    {
        $cab = $this->cabangPertama();
        $user = $this->buatUser('manajer_cabang', $cab->id);
        session(['active_cabang_id' => $cab->id]);
        $response = $this->actingAs($user)->get(route('dashboard.cabang'));
        $response->assertOk();
        $response->assertSee('Analisis Penjualan', false);
        $response->assertSee('Rata-rata Nilai Transaksi', false);
    }

    public function test_laporan_penjualan_render_section_analisis(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $response = $this->actingAs($user)->get(route('laporan.penjualan'));
        $response->assertOk();
        $response->assertSee('Analisis Penjualan', false);
        $response->assertSee('ap_periode', false);
    }

    public function test_laporan_penjualan_preset_override_tanggal(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);

        // Pakai preset "7_hari" tanpa isi dari/sampai — controller harus resolve
        $response = $this->actingAs($user)->get(route('laporan.penjualan', ['ap_periode' => '7_hari']));
        $response->assertOk();
        // Section rendered, periode aktif = 7_hari
        $this->assertStringContainsString('name="ap_periode"', $response->getContent());
    }

    public function test_dropdown_preset_tampil_6_opsi(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $response = $this->actingAs($user)->get(route('dashboard.pusat'));
        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('value="hari_ini"', $html);
        $this->assertStringContainsString('value="7_hari"', $html);
        $this->assertStringContainsString('value="30_hari"', $html);
        $this->assertStringContainsString('value="bulan_ini"', $html);
        $this->assertStringContainsString('value="bulan_lalu"', $html);
        $this->assertStringContainsString('value="tahun_ini"', $html);
    }

    public function test_tooltip_full_value_hadir_di_markup(): void
    {
        $user = $this->buatUser('owner', $this->cabangPertama()->id);
        $response = $this->actingAs($user)->get(route('dashboard.pusat'));
        $response->assertOk();
        // Tooltip Bootstrap pakai data-bs-toggle="tooltip" + title
        $this->assertStringContainsString('data-bs-toggle="tooltip"', $response->getContent());
    }
}
