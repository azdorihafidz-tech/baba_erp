<?php

namespace Tests\Feature\Tahap7;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Sprint 3 lanjutan (2026-09-22) — Export Snapshot Simulator BEP. Server
 * mereproduksi ULANG logic `hitung()` JS (bukan percaya angka dari client)
 * supaya snapshot tidak bisa dipalsukan lewat manipulasi browser.
 */
class SimulatorBepSnapshotExportTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function payloadDasar(array $override = []): array
    {
        return array_merge([
            'nama_simulasi' => 'Test Simulasi BEP',
            'volume_harian' => 50,
            'harga_jual' => 20000,
            'biaya_variabel' => 12000,
            'beban_tetap' => 5000000,
            'modal_awal' => 20000000,
        ], $override);
    }

    public function test_export_excel_3_sheet_valid(): void
    {
        $owner = $this->buatUser('owner');

        $response = $this->actingAs($owner)->post(route('laporan.simulator-bep.export-excel'), $this->payloadDasar());

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type') ?? '');
    }

    public function test_export_pdf_valid(): void
    {
        $owner = $this->buatUser('owner');

        $response = $this->actingAs($owner)->post(route('laporan.simulator-bep.export-pdf'), $this->payloadDasar());

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_validasi_reject_field_kosong(): void
    {
        $owner = $this->buatUser('owner');

        $response = $this->actingAs($owner)->post(route('laporan.simulator-bep.export-excel'), []);

        $response->assertSessionHasErrors(['volume_harian', 'harga_jual', 'biaya_variabel', 'beban_tetap']);
    }

    public function test_validasi_reject_nilai_negatif(): void
    {
        $owner = $this->buatUser('owner');

        $response = $this->actingAs($owner)->post(route('laporan.simulator-bep.export-excel'), $this->payloadDasar([
            'harga_jual' => -1000,
        ]));

        $response->assertSessionHasErrors('harga_jual');
    }

    public function test_target_profit_opsional_kosong_tetap_jalan(): void
    {
        $owner = $this->buatUser('owner');

        $response = $this->actingAs($owner)->post(route('laporan.simulator-bep.export-pdf'), $this->payloadDasar());

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_permission_export_ditolak_untuk_role_tanpa_akses(): void
    {
        $kasir = $this->buatUser('kasir', $this->cabangPertama()->id);

        $response = $this->actingAs($kasir)->post(route('laporan.simulator-bep.export-excel'), $this->payloadDasar());

        $response->assertForbidden();
    }

    /**
     * PDF binary tidak bisa di-assert via string search konten (dompdf
     * compress/encode teks) — kesimpulan dinamis diverifikasi lewat method
     * `buildSnapshot()`/`buildKesimpulan()` (reflection) supaya genuinely
     * cek logic-nya, bukan cuma "response OK".
     */
    private function ambilKesimpulan(array $override): array
    {
        $controller = app(\App\Http\Controllers\SimulatorBepController::class);
        $ref = new \ReflectionClass($controller);
        $method = $ref->getMethod('buildSnapshot');
        $method->setAccessible(true);

        $snapshot = $method->invoke($controller, $this->payloadDasar($override));

        return $snapshot['kesimpulan'];
    }

    public function test_kesimpulan_belum_mencapai_bep(): void
    {
        // Volume kecil (5kg/hari) vs beban tetap besar -> BEP unit >> volume bulanan
        $kesimpulan = $this->ambilKesimpulan(['volume_harian' => 5, 'beban_tetap' => 50000000]);

        $this->assertStringContainsString('BELUM mencapai BEP', $kesimpulan[0]);
    }

    public function test_kesimpulan_sudah_mencapai_bep(): void
    {
        // Volume besar vs beban tetap kecil -> BEP unit << volume bulanan
        $kesimpulan = $this->ambilKesimpulan(['volume_harian' => 500, 'beban_tetap' => 100000]);

        $this->assertStringContainsString('SUDAH mencapai BEP', $kesimpulan[0]);
    }

    public function test_kesimpulan_margin_negatif_tidak_bisa_bep(): void
    {
        $kesimpulan = $this->ambilKesimpulan(['harga_jual' => 10000, 'biaya_variabel' => 15000]);

        $this->assertStringContainsString('BEP tidak bisa dicapai', $kesimpulan[0]);
    }

    public function test_dengan_target_profit_margin_of_safety_muncul(): void
    {
        $kesimpulan = $this->ambilKesimpulan(['target_profit' => 3000000]);

        $this->assertStringContainsString('margin of safety', implode(' ', $kesimpulan));
    }

    public function test_reproduksi_logic_server_sama_dengan_js(): void
    {
        // Skenario JS existing: volume 26 hari kerja, margin=hargaJual-biayaVariabel,
        // bepUnit=bebanTetap/margin, bepRupiah=bepUnit*hargaJual.
        $controller = app(\App\Http\Controllers\SimulatorBepController::class);
        $ref = new \ReflectionClass($controller);
        $method = $ref->getMethod('buildSnapshot');
        $method->setAccessible(true);

        $snapshot = $method->invoke($controller, $this->payloadDasar([
            'volume_harian' => 50, 'harga_jual' => 20000, 'biaya_variabel' => 12000, 'beban_tetap' => 5000000,
        ]));

        $margin = 20000 - 12000;
        $bepUnitExpected = 5000000 / $margin;
        $bepRupiahExpected = $bepUnitExpected * 20000;
        $volumeBulananExpected = 50 * 26;

        $this->assertEqualsWithDelta($bepUnitExpected, $snapshot['bepUnit'], 0.001);
        $this->assertEqualsWithDelta($bepRupiahExpected, $snapshot['bepRupiah'], 0.001);
        $this->assertEqualsWithDelta($volumeBulananExpected, $snapshot['volumeBulanan'], 0.001);
    }
}
