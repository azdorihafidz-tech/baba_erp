<?php

namespace Tests\Feature\Tahap7;

use App\Models\Order;
use App\Models\PengaturanUmum;
use App\Support\Branding;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * 4.44 Poin 1 — sisi ERP untuk aplikasi Android "Kasir POS" (porting
 * erp-coffeeshop 097a577/b5155eb/aa0ca0c). Di browser biasa semua alur cetak
 * lama tetap ada; bagian APK hanya aktif kalau window.KopiDripPrinter disuntik
 * aplikasi.
 */
class ApkPrinterTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_app_info_publik_dengan_format_kontrak_apk(): void
    {
        $this->get('/app-info')->assertOk()
            ->assertExactJson(['erp' => true] + app(Branding::class)->untukAplikasi())
            ->assertJson(['erp' => true, 'nama_singkat' => "D'mentai", 'tagline' => 'Dimsum & Gyoza'])
            ->assertJsonStructure(['erp', 'nama', 'nama_singkat', 'tagline', 'logo_url', 'ikon_url']);
    }

    public function test_app_info_ikut_identitas_usaha_terbaru(): void
    {
        PengaturanUmum::query()->firstOrFail()->update(['nama_perusahaan' => 'Dimsum NJ Jaya', 'nama_singkat' => 'Dimsum NJ']);

        $this->get('/app-info')->assertOk()
            ->assertJson(['nama' => 'Dimsum NJ Jaya', 'nama_singkat' => 'Dimsum NJ']);
    }

    public function test_pos_memuat_helper_apk_dan_jalur_laci_lama_tetap_ada(): void
    {
        $cabang = $this->cabangPertama();
        $kasir  = $this->buatUser('kasir', $cabang->id);

        $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])
            ->get(route('penjualan.pos'))->assertOk()
            ->assertSee('window.KDApk', false)
            ->assertSee('KopiDripPrinterResult', false)
            ->assertSee('id="kdApkSettingsLink"', false)
            ->assertSee('window.KDApk.bukaLaci()', false)
            ->assertSee('navigator.bluetooth.requestDevice', false);
    }

    public function test_struk_modal_punya_jalur_apk_dan_tetap_punya_jalur_bluetooth_lama(): void
    {
        $order = Order::query()->firstOrFail();
        $owner = $this->buatUser('owner', $order->cabang_id);

        $this->actingAs($owner)->get(route('penjualan.struk-modal', $order))->assertOk()
            ->assertSee('id="strukApkGroup"', false)
            ->assertSee('window.strukCetakApk', false)
            ->assertSee('id="strukWebBtGroup"', false)
            ->assertSee('window.strukCetakBluetooth', false)
            ->assertSee('id="strukBtnBrowserPrint"', false);
    }

    public function test_panduan_pos_menjelaskan_aplikasi_kasir(): void
    {
        $owner = $this->buatUser('owner', $this->cabangPertama()->id);

        $this->actingAs($owner)->get(route('panduan.show', 'pos'))->assertOk()
            ->assertSee('Aplikasi Kasir di Tablet')
            ->assertSee('Pengaturan Printer Aplikasi');
    }

    public function test_halaman_tanpa_login_tidak_memuat_helper_apk(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('window.KDApk', false);
    }
}
