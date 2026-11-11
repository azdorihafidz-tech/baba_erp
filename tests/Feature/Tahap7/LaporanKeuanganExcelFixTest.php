<?php

namespace Tests\Feature\Tahap7;

use App\Models\Kas;
use App\Models\TransaksiKeuangan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Bug fix 2026-09-21 (laporan Owner: "Laporan → Keuangan : Error", juga
 * keluhan "1 kolom excel banyak isi" di menu laporan lain yang serupa).
 *
 * Root cause: `LaporanKeuanganController::labaRugi()` export Excel manual
 * pakai `fputcsv()` -- data `$pemasukan`/`$pengeluaran` didapat dari
 * `TransaksiKeuangan::selectRaw('kategori, SUM(jumlah) as total')`, TAPI
 * Eloquent TETAP menerapkan cast model (`kategori` => App\Enums\
 * KategoriTransaksi enum) ke kolom raw itu. `fputcsv($file, [$p->kategori,
 * ...])` coba string-cast OBJEK enum -> PHP fatal error "Object of class
 * App\Enums\KategoriTransaksi could not be converted to string" -- INI
 * "Error" yang dilaporkan Owner, reproducible persis via test di bawah.
 *
 * Selain crash, file .xls yang dihasilkan sebenarnya CSV teks delimiter
 * ";" (bukan xlsx sungguhan) -- kalau locale Excel pakai koma sbg list
 * separator, SEMUA kolom kebaca jadi 1 kolom (keluhan "1 kolom banyak
 * isi"). Fix: xlsx sungguhan via maatwebsite/excel (`LaporanLabaRugiExport`,
 * `LaporanArusKasExport`), kategori dipanggil `->label()` bukan objek mentah.
 */
class LaporanKeuanganExcelFixTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function buatTransaksi(int $cabangId, ?int $kasId, string $tipe, string $kategori, float $jumlah): TransaksiKeuangan
    {
        return TransaksiKeuangan::create([
            'cabang_id' => $cabangId, 'kas_id' => $kasId,
            'nomor_transaksi' => 'TRX-TEST-' . uniqid('', false),
            'tanggal_transaksi' => now()->toDateString(), 'tipe' => $tipe, 'kategori' => $kategori,
            'keterangan' => 'test laporan keuangan', 'jumlah' => $jumlah,
        ]);
    }

    public function test_laba_rugi_html_masih_render_normal(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get(route('laporan.keuangan.laba-rugi'));

        $response->assertOk();
    }

    public function test_export_excel_laba_rugi_tidak_crash_dengan_data_kategori_asli(): void
    {
        // Skenario PERSIS bug asli: transaksi dgn kategori enum bukan null
        // ('operasional', bukan 'saldo_awal') supaya groupBy+selectRaw
        // menghasilkan Eloquent model dgn atribut `kategori` ter-cast jadi
        // objek enum -- ini yang crash di fputcsv() versi lama.
        $admin = $this->buatUser('admin_pusat');
        $cabang = $this->cabangPertama();
        $this->buatTransaksi($cabang->id, null, 'pemasukan', 'penjualan', 500000);
        $this->buatTransaksi($cabang->id, null, 'pengeluaran', 'operasional', 150000);

        $response = $this->actingAs($admin)->get(route('laporan.keuangan.laba-rugi', ['export' => 'excel']));

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('Content-Type') ?? $response->headers->get('content-type') ?? ''
        );
    }

    public function test_export_excel_laba_rugi_dengan_kategori_saldo_awal_tidak_crash(): void
    {
        // Kategori 'saldo_awal' (fix Ronde sebelumnya, CLAUDE.md 4.20) --
        // pastikan export tidak regresi sama sekali dgn kategori ini juga.
        $admin = $this->buatUser('admin_pusat');
        $cabang = $this->cabangPertama();
        $this->buatTransaksi($cabang->id, null, 'pemasukan', 'saldo_awal', 1000000);

        $response = $this->actingAs($admin)->get(route('laporan.keuangan.laba-rugi', ['export' => 'excel']));

        $response->assertOk();
    }

    public function test_arus_kas_html_masih_render_normal(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get(route('laporan.keuangan.arus-kas'));

        $response->assertOk();
    }

    public function test_export_excel_arus_kas_tidak_crash(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $cabang = $this->cabangPertama();
        $kas = Kas::firstOrCreate(
            ['cabang_id' => $cabang->id, 'default_untuk' => 'tunai'],
            ['nama_kas' => 'Kas Tunai Test LK', 'tipe_kas' => 'tunai', 'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true]
        );
        $this->buatTransaksi($cabang->id, $kas->id, 'pemasukan', 'penjualan', 250000);

        $response = $this->actingAs($admin)->get(route('laporan.keuangan.arus-kas', ['export' => 'excel']));

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('Content-Type') ?? $response->headers->get('content-type') ?? ''
        );
    }

    public function test_export_tanpa_permission_ditolak(): void
    {
        $kasir = $this->buatUser('kasir', $this->cabangPertama()->id);

        $response = $this->actingAs($kasir)->get(route('laporan.keuangan.laba-rugi', ['export' => 'excel']));

        $response->assertStatus(403);
    }

    public function test_regresi_harian_dan_pengeluaran_alias_masih_normal(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $this->actingAs($admin)->get(route('laporan.keuangan.harian'))->assertOk();
        $this->actingAs($admin)->get(route('laporan.keuangan.laba-rugi'))->assertOk();
    }
}
