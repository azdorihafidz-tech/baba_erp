<?php

namespace Tests\Feature\Baba;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** ERP BABA Tahap 6b — unduh BABA Printer Agent, panduan, dan komponen agen di halaman. */
class PrinterAgentTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_unduh_agen_wajib_login_dan_berisi_exe(): void
    {
        $this->get(route('printer-agent.unduh'))->assertRedirect();
        if (! is_file(public_path('downloads/baba-printer-agent.exe'))) {
            $this->markTestSkipped('Exe belum dibangun (tools/printer-agent, tidak masuk git).');
        }
        $r = $this->actingAs($this->buatUser('kasir'))->get(route('printer-agent.unduh'));
        $r->assertOk();
        $this->assertStringContainsString('baba-printer-agent.exe', (string) $r->headers->get('content-disposition'));
        $this->assertGreaterThan(1_000_000, filesize(public_path('downloads/baba-printer-agent.exe')));
        $this->assertSame('MZ', substr((string) file_get_contents(public_path('downloads/baba-printer-agent.exe'), false, null, 0, 2), 0, 2), 'file Windows PE');
    }

    public function test_komponen_agen_dan_panduan(): void
    {
        $this->actingAs($this->buatUser('owner'));
        $h = $this->get(route('penjualan.index'))->assertOk()->getContent();
        $this->assertStringContainsString("http://127.0.0.1:17331", $h);
        $this->assertStringContainsString('window.BabaAgent', $h);
        $this->assertStringContainsString('id="babaAgentSettingsLink"', $h);
        $this->assertStringNotContainsString('BMAgent', $h);
        $this->get(route('panduan.show', 'printer-kasir'))->assertOk()->assertSee('BABA Printer Agent')->assertSee('baba-printer-agent.exe');
    }
}
