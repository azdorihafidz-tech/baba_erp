<?php

namespace Tests\Feature\Tahap7;

use App\Enums\TipePembayaran;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use App\Services\PenjualanService;
use App\Services\SetoranKasirService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap 7 D'mentai (2026-09-18) — 2 improvement test manual production:
 * (1) rename label "Nama (jika tidak terdaftar)" -> "Nama" di POS;
 * (2) hapus metode pembayaran Gojek & Grab (fokus retail walk-in, bukan
 * food delivery) -- sisa Tunai/Transfer/QRIS.
 */
class HapusGojekGrabTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;
/** Tahap B 4.38: saklar wajib bukti default aktif — test ini menguji hal lain (bukan bukti), jadi dimatikan. */    protected function setUp(): void    {        parent::setUp();        Cabang::query()->update(['wajib_bukti_transfer' => false, 'wajib_bukti_qris' => false]);    }

    private function cabangOperasional(): Cabang
    {
        return Cabang::where('tipe', 'cabang')->orderBy('id')->firstOrFail();
    }

    private function siapkanKas(Cabang $cabang): void
    {
        foreach (['tunai', 'transfer', 'qris'] as $jenis) {
            Kas::firstOrCreate(
                ['cabang_id' => $cabang->id, 'default_untuk' => $jenis],
                ['nama_kas' => "Kas {$jenis}", 'tipe_kas' => $jenis === 'tunai' ? 'tunai' : 'bank', 'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true]
            );
        }
    }

    // ===== Task 1: Rename Label =====

    public function test_pos_label_nama_sudah_ganti(): void
    {
        $cabang = $this->cabangOperasional();
        $admin = $this->buatUser('admin_pusat', $cabang->id);

        $response = $this->actingAs($admin)->withSession(['active_cabang_id' => $cabang->id])->get('/penjualan/pos');

        $response->assertOk();
        $response->assertSee('>Nama<', false);
        $response->assertDontSee('Nama (jika tidak terdaftar)');
        // Placeholder "walk-in" tetap dipertahankan sesuai instruksi.
        $response->assertSee('Nama pelanggan / walk-in', false);
    }

    // ===== Task 2: Tombol Gojek/Grab hilang dari POS =====

    public function test_pos_tombol_gojek_grab_tidak_ada(): void
    {
        $cabang = $this->cabangOperasional();
        $admin = $this->buatUser('admin_pusat', $cabang->id);

        $response = $this->actingAs($admin)->withSession(['active_cabang_id' => $cabang->id])->get('/penjualan/pos');

        $response->assertOk();
        $response->assertDontSee('btnGojek', false);
        $response->assertDontSee('btnGrab', false);
        $response->assertDontSee('>Gojek<', false);
        $response->assertDontSee('>Grab<', false);
    }

    // ===== Struktur data =====

    public function test_enum_tipe_pembayaran_cuma_3_case(): void
    {
        $values = array_column(TipePembayaran::cases(), 'value');
        $this->assertEquals(['tunai', 'transfer', 'qris'], $values);
    }

    public function test_kolom_enum_db_sudah_diperbarui(): void
    {
        $orderCol = collect(DB::select("SHOW COLUMNS FROM orders WHERE Field = 'tipe_pembayaran'"))->first();
        $this->assertStringNotContainsString('gojek', $orderCol->Type);
        $this->assertStringNotContainsString('grab', $orderCol->Type);

        $paymentCol = collect(DB::select("SHOW COLUMNS FROM order_payments WHERE Field = 'metode'"))->first();
        $this->assertStringNotContainsString('gojek', $paymentCol->Type);
        $this->assertStringNotContainsString('grab', $paymentCol->Type);
    }

    // ===== POS submit: 3 metode valid, gojek/grab ditolak =====

    public function test_submit_order_dengan_tunai_transfer_qris_berhasil(): void
    {
        $cabang = $this->cabangOperasional();
        $this->siapkanKas($cabang);
        $kasir = $this->buatUser('kasir', $cabang->id);
        $item = Item::where('kode_item', 'PJ-MNM-001')->firstOrFail();
        \App\Models\Stock::updateOrCreate(['item_id' => $item->id, 'lokasi_id' => $cabang->id], ['qty' => 100, 'qty_minimum' => 0]);

        foreach (['tunai', 'transfer', 'qris'] as $metode) {
            $kas = Kas::where('cabang_id', $cabang->id)->where('default_untuk', $metode)->first();
            $order = app(PenjualanService::class)->buatOrder([
                'kasir_id' => $kasir->id, 'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway',
                'nama_pelanggan' => 'Test', 'items' => [['item_id' => $item->id, 'qty' => 1, 'harga_satuan' => 10000, 'satuan' => $item->satuan, 'nama_item' => $item->nama_item]],
                'payments' => [['metode' => $metode, 'jumlah' => 10000, 'kas_id' => $kas->id]],
                'tipe_pembayaran' => $metode, 'jumlah_bayar' => 10000,
            ], $cabang->id);

            $this->assertNotNull($order, "Order dengan metode {$metode} harus berhasil.");
        }
    }

    public function test_submit_order_dengan_metode_gojek_ditolak_validasi(): void
    {
        $cabang = $this->cabangOperasional();
        $this->siapkanKas($cabang);
        $kasir = $this->buatUser('kasir', $cabang->id);
        $item = Item::where('kode_item', 'PJ-MNM-001')->firstOrFail();
        \App\Models\Stock::updateOrCreate(['item_id' => $item->id, 'lokasi_id' => $cabang->id], ['qty' => 100, 'qty_minimum' => 0]);

        $response = $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])->post('/penjualan', [
            'tipe_transaksi' => 'takeaway', 'nama_pelanggan' => 'Test Gojek',
            'items' => [['item_id' => $item->id, 'qty' => 1, 'harga_satuan' => 10000, 'satuan' => $item->satuan, 'nama_item' => $item->nama_item]],
            'payments' => [['metode' => 'gojek', 'jumlah' => 10000]],
            'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => 10000,
            'tampil_di_antrian' => '1',
        ]);

        $response->assertSessionHasErrors('payments.0.metode');
    }

    public function test_charge_bill_dengan_metode_grab_ditolak_validasi(): void
    {
        $cabang = $this->cabangOperasional();
        $this->siapkanKas($cabang);
        $kasir = $this->buatUser('kasir', $cabang->id);
        $item = Item::where('kode_item', 'PJ-MNM-001')->firstOrFail();
        \App\Models\Stock::updateOrCreate(['item_id' => $item->id, 'lokasi_id' => $cabang->id], ['qty' => 100, 'qty_minimum' => 0]);

        $bill = app(PenjualanService::class)->simpanBill([
            'tipe_transaksi' => 'takeaway', 'nama_pelanggan' => 'Test',
            'items' => [['item_id' => $item->id, 'nama_item' => $item->nama_item, 'qty' => 1, 'satuan' => $item->satuan, 'harga_satuan' => 10000]],
        ], $cabang->id);

        $response = $this->actingAs($kasir)->postJson("/penjualan/{$bill->id}/charge", [
            'payments' => [['metode' => 'grab', 'jumlah' => 10000]],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('payments.0.metode');
    }

    // ===== Setoran Kasir: breakdown cuma 3 metode =====

    public function test_setoran_kasir_breakdown_cuma_3_metode(): void
    {
        $cabang = $this->cabangOperasional();
        $this->siapkanKas($cabang);
        $kasir = $this->buatUser('kasir', $cabang->id);

        $hitung = app(SetoranKasirService::class)->hitungOtomatis($cabang->id, now()->toDateString());

        $this->assertEquals(['tunai', 'transfer', 'qris'], array_keys($hitung['per_metode']));
    }

    public function test_setoran_kasir_create_page_tidak_tampilkan_gojek_grab(): void
    {
        $cabang = $this->cabangOperasional();
        $this->siapkanKas($cabang);
        $kasir = $this->buatUser('kasir', $cabang->id);

        $response = $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])->get('/setoran-kasir/create');

        $response->assertOk();
        $response->assertDontSee('>Gojek<', false);
        $response->assertDontSee('>Grab<', false);
    }

    public function test_setoran_kasir_submit_total_dihitung_benar(): void
    {
        $cabang = $this->cabangOperasional();
        $this->siapkanKas($cabang);
        $kasir = $this->buatUser('kasir', $cabang->id);
        $item = Item::where('kode_item', 'PJ-MNM-001')->firstOrFail();
        \App\Models\Stock::updateOrCreate(['item_id' => $item->id, 'lokasi_id' => $cabang->id], ['qty' => 100, 'qty_minimum' => 0]);
        $kasTunai = Kas::where('cabang_id', $cabang->id)->where('default_untuk', 'tunai')->first();

        app(PenjualanService::class)->buatOrder([
            'kasir_id' => $kasir->id, 'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway',
            'nama_pelanggan' => 'Test', 'items' => [['item_id' => $item->id, 'qty' => 1, 'harga_satuan' => 25000, 'satuan' => $item->satuan, 'nama_item' => $item->nama_item]],
            'payments' => [['metode' => 'tunai', 'jumlah' => 25000, 'kas_id' => $kasTunai->id]],
            'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => 25000,
        ], $cabang->id);

        $response = $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])
            ->post('/setoran-kasir', ['tanggal' => now()->format('Y-m-d'), 'total_disetor' => 25000]);

        $response->assertRedirect();
        $setoran = \App\Models\Setoran::where('cabang_id', $cabang->id)->firstOrFail();
        $this->assertEquals(25000, $setoran->total_disetor);
    }

    // ===== Panduan tidak lagi mention Gojek/Grab =====

    public function test_panduan_pos_tidak_lagi_mention_gojek_grab(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/panduan/pos');

        $response->assertOk();
        $response->assertDontSee('Gojek');
        $response->assertDontSee('Grab');
    }

    public function test_panduan_setoran_kasir_tidak_lagi_mention_gojek_grab(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/panduan/setoran-kasir');

        $response->assertOk();
        $response->assertDontSee('Gojek');
        $response->assertDontSee('Grab');
    }
}
