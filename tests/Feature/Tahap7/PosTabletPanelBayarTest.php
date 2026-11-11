<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * 4.47 — POS mode Tablet: panel Ringkasan & Pembayaran tidak ikut ter-scroll,
 * isinya scroll sendiri, tombol aksi (Total, PROSES ORDER, Save Bill) di kaki
 * panel. Tombol tetap di dalam form transaksi.
 */
class PosTabletPanelBayarTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_panel_bayar_punya_isi_dan_kaki_dengan_tombol_aksi(): void
    {
        $c = Cabang::create(['nama_cabang' => 'Outlet Tablet', 'kode_cabang' => 'TB' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true]);
        $html = $this->actingAs($this->buatUser('kasir', $c->id))->withSession(['active_cabang_id' => $c->id])
            ->get(route('penjualan.pos'))->assertOk()->getContent();

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8">' . $html);
        $x = new \DOMXPath($dom);
        $ada = fn (string $q) => $x->query($q)->length;

        $this->assertSame(1, $ada("//*[@id='panelBayar']/*[@id='panelBayarIsi']"));
        $this->assertSame(1, $ada("//*[@id='panelBayar']/*[@id='panelBayarKaki']"));
        foreach (['btnProses', 'btnSaveBill', 'kakiTotalNilai'] as $id) {
            $this->assertSame(1, $ada("//*[@id='panelBayarKaki']//*[@id='{$id}']"), $id);
        }
        $this->assertSame(1, $ada("//form[@id='formPos']//*[@id='panelBayarKaki']//*[@id='btnProses']"));
        $this->assertSame(1, $ada("//*[@id='panelBayarIsi']//*[@id='jumlahBayarInput']"));
        $this->assertSame(1, $ada("//*[@id='posKolomKiri']/following-sibling::*[@id='posKolomKanan']"));

        $this->assertStringContainsString('body.pos-tablet-mode #panelBayarIsi', $html);
        $this->assertStringContainsString("getElementById('kakiTotalNilai')", $html);
    }
}
