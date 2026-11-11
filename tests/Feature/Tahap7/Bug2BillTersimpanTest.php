<?php

namespace Tests\Feature\Tahap7;

use App\Enums\StatusOrder;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use App\Models\Order;
use App\Models\Stock;
use App\Services\PenjualanService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap 7 D'mentai — Bug 2 fix: Bill Tersimpan (a) posisi paling bawah,
 * (b) bisa dibatalkan (permission baru order.bill_tersimpan.batalkan),
 * (c) bisa bayar dengan modal pembayaran lengkap (bukan cuma tunai pas).
 */
class Bug2BillTersimpanTest extends TestCase
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
        foreach (['tunai', 'qris', 'transfer'] as $jenis) {
            Kas::firstOrCreate(
                ['cabang_id' => $cabang->id, 'default_untuk' => $jenis],
                ['nama_kas' => "Kas {$jenis} Test", 'tipe_kas' => $jenis === 'tunai' ? 'tunai' : 'bank', 'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true]
            );
        }
    }

    private function buatBillTersimpan(Cabang $cabang, int $kasirId, float $harga = 15000): Order
    {
        $item = Item::where('kode_item', 'PJ-MNM-001')->firstOrFail();
        Stock::updateOrCreate(['item_id' => $item->id, 'lokasi_id' => $cabang->id], ['qty' => 100, 'qty_minimum' => 0]);

        return app(PenjualanService::class)->simpanBill([
            'tipe_transaksi' => 'takeaway', 'nama_pelanggan' => 'Test Bill',
            'items' => [['item_id' => $item->id, 'nama_item' => $item->nama_item, 'qty' => 1, 'satuan' => $item->satuan, 'harga_satuan' => $harga]],
        ], $cabang->id);
    }

    // ===== 2a: posisi paling bawah =====

    public function test_bill_tersimpan_tampil_setelah_form_utama_ditutup(): void
    {
        $cabang = $this->cabangOperasional();
        $this->siapkanKas($cabang);
        $kasir = $this->buatUser('kasir', $cabang->id);
        $bill = $this->buatBillTersimpan($cabang, $kasir->id);

        $response = $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])->get('/penjualan/pos');

        $response->assertOk();
        $html = $response->getContent();
        $posisiFormClose = strpos($html, '</form>');
        $posisiBillTersimpan = strpos($html, 'Bill Tersimpan (Belum Dibayar)');
        $this->assertNotFalse($posisiBillTersimpan, 'Section Bill Tersimpan harus ada di halaman.');
        $this->assertGreaterThan($posisiFormClose, $posisiBillTersimpan, 'Bill Tersimpan harus tampil SETELAH form utama ditutup (paling bawah).');
    }

    // ===== 2b: Batalkan Bill =====

    public function test_kasir_tanpa_permission_tidak_lihat_tombol_batalkan(): void
    {
        $cabang = $this->cabangOperasional();
        $this->siapkanKas($cabang);
        $kasir = $this->buatUser('kasir', $cabang->id);
        $this->buatBillTersimpan($cabang, $kasir->id);

        $response = $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])->get('/penjualan/pos');

        $response->assertOk();
        // JS function DEFINITION selalu ada (global script), yang di-gate
        // permission adalah TOMBOLnya (onclick="batalkanBillTersimpan(...)").
        $response->assertDontSee('onclick="batalkanBillTersimpan(', false);
    }

    public function test_admin_pusat_dengan_permission_lihat_tombol_batalkan(): void
    {
        $cabang = $this->cabangOperasional();
        $this->siapkanKas($cabang);
        $admin = $this->buatUser('admin_pusat', $cabang->id);
        $this->buatBillTersimpan($cabang, $admin->id);

        $response = $this->actingAs($admin)->withSession(['active_cabang_id' => $cabang->id])->get('/penjualan/pos');

        $response->assertOk();
        $response->assertSee('onclick="batalkanBillTersimpan(', false);
    }

    public function test_batalkan_bill_pending_berhasil_tanpa_efek_stok_kas(): void
    {
        $cabang = $this->cabangOperasional();
        $this->siapkanKas($cabang);
        $admin = $this->buatUser('admin_pusat', $cabang->id);
        $bill = $this->buatBillTersimpan($cabang, $admin->id);
        $item = Item::where('kode_item', 'PJ-MNM-001')->firstOrFail();
        $stokSebelum = Stock::where('item_id', $item->id)->where('lokasi_id', $cabang->id)->value('qty');

        $response = $this->actingAs($admin)->postJson("/penjualan/{$bill->id}/batalkan-bill");

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $bill->refresh();
        $this->assertSame(StatusOrder::Dibatalkan, $bill->status);
        $stokSesudah = Stock::where('item_id', $item->id)->where('lokasi_id', $cabang->id)->value('qty');
        $this->assertEquals($stokSebelum, $stokSesudah, 'Stok TIDAK BOLEH berubah -- bill Pending memang belum pernah potong stok.');
    }

    public function test_kasir_tanpa_permission_tidak_bisa_batalkan_via_endpoint_langsung(): void
    {
        $cabang = $this->cabangOperasional();
        $this->siapkanKas($cabang);
        $kasir = $this->buatUser('kasir', $cabang->id);
        $bill = $this->buatBillTersimpan($cabang, $kasir->id);

        $response = $this->actingAs($kasir)->postJson("/penjualan/{$bill->id}/batalkan-bill");

        $response->assertForbidden();
        $bill->refresh();
        $this->assertSame(StatusOrder::Pending, $bill->status);
    }

    public function test_batalkan_bill_yang_sudah_dibayar_ditolak(): void
    {
        $cabang = $this->cabangOperasional();
        $this->siapkanKas($cabang);
        $admin = $this->buatUser('admin_pusat', $cabang->id);
        $bill = $this->buatBillTersimpan($cabang, $admin->id);
        app(PenjualanService::class)->chargeBill($bill, ['payments' => [['metode' => 'tunai', 'jumlah' => 15000]]]);

        $response = $this->actingAs($admin)->postJson("/penjualan/{$bill->id}/batalkan-bill");

        $response->assertStatus(422);
        $bill->refresh();
        $this->assertSame(StatusOrder::Selesai, $bill->status);
    }

    // ===== 2c: Modal Bayar Lengkap (reuse endpoint charge-bill dgn split) =====

    public function test_bayar_bill_via_modal_split_payment_dua_metode(): void
    {
        $cabang = $this->cabangOperasional();
        $this->siapkanKas($cabang);
        $kasir = $this->buatUser('kasir', $cabang->id);
        $bill = $this->buatBillTersimpan($cabang, $kasir->id, 20000);
        $kasQris = Kas::where('cabang_id', $cabang->id)->where('default_untuk', 'qris')->first();

        $response = $this->actingAs($kasir)->postJson("/penjualan/{$bill->id}/charge", [
            'payments' => [
                ['metode' => 'transfer', 'jumlah' => 10000],
                ['metode' => 'qris', 'jumlah' => 10000, 'kas_id' => $kasQris->id],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $bill->refresh();
        $this->assertSame(StatusOrder::Selesai, $bill->status);
        $this->assertEquals(20000, $bill->jumlah_bayar);
    }

    public function test_modal_bayar_bill_markup_muncul_di_pos(): void
    {
        $cabang = $this->cabangOperasional();
        $this->siapkanKas($cabang);
        $kasir = $this->buatUser('kasir', $cabang->id);
        $this->buatBillTersimpan($cabang, $kasir->id);

        $response = $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])->get('/penjualan/pos');

        $response->assertOk();
        $response->assertSee('modalBayarBill', false);
        $response->assertSee('bukaModalBayarBill', false);
        $response->assertSee('Split Payment', false);
    }

    // ===== Regresi: Tunai Pas existing tetap jalan =====

    public function test_regresi_tunai_pas_tetap_berfungsi(): void
    {
        $cabang = $this->cabangOperasional();
        $this->siapkanKas($cabang);
        $kasir = $this->buatUser('kasir', $cabang->id);
        $bill = $this->buatBillTersimpan($cabang, $kasir->id, 15000);

        $response = $this->actingAs($kasir)->postJson("/penjualan/{$bill->id}/charge", [
            'payments' => [['metode' => 'tunai', 'jumlah' => 15000]],
        ]);

        $response->assertOk();
        $bill->refresh();
        $this->assertSame(StatusOrder::Selesai, $bill->status);
    }
}
