<?php

namespace Tests\Feature\Tahap7;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap 7 D'mentai — Bug 6 (bonus temuan smoke test 187 halaman): Laporan
 * Eksekutif crash 500 kalau cabang/periode TIDAK ADA penjualan sama sekali
 * bulan ini (margin_persen null -> RangkumanFinalService::skalakan() butuh
 * float non-null). Severity KRITIS: akan terpicu natural di production
 * (outlet baru buka = belum ada histori penjualan bulan berjalan).
 */
class Bug6RangkumanFinalNullMarginTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_laporan_eksekutif_preview_tidak_crash_tanpa_penjualan_bulan_ini(): void
    {
        $owner = $this->buatUser('owner');

        // Sengaja TIDAK buat order apapun -- mensimulasikan cabang/periode
        // tanpa penjualan bulan ini (kondisi yang bikin margin_persen null).
        $response = $this->actingAs($owner)->get('/laporan/eksekutif/preview');

        $response->assertOk();
    }

    public function test_laporan_eksekutif_export_pdf_tidak_crash_tanpa_penjualan_bulan_ini(): void
    {
        $owner = $this->buatUser('owner');

        $response = $this->actingAs($owner)->get('/laporan/eksekutif/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
