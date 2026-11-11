<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use App\Models\OrderPayment;
use App\Models\Stock;
use App\Models\TransaksiKeuangan;
use App\Services\PenjualanService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Bug (2026-09-23): order Rp 5.000, bayar tunai Rp 50.000, kembali Rp 45.000
 * tapi Kas & Transaksi mencatat pemasukan Rp 50.000 (kembalian tidak
 * dikurangkan). Sekarang kas/transaksi/order_payments hanya mencatat uang
 * yang benar-benar tinggal di laci.
 */
class KembalianKasSinkronTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;
/** Tahap B 4.38: saklar wajib bukti default aktif — test ini menguji hal lain (bukan bukti), jadi dimatikan. */    protected function setUp(): void    {        parent::setUp();        Cabang::query()->update(['wajib_bukti_transfer' => false, 'wajib_bukti_qris' => false]);    }

    private function siapkan(): array
    {
        $cabang = Cabang::where('tipe', 'cabang')->orderBy('id')->firstOrFail();
        foreach (['tunai', 'transfer', 'qris'] as $jenis) {
            Kas::firstOrCreate(
                ['cabang_id' => $cabang->id, 'default_untuk' => $jenis],
                ['nama_kas' => "Kas {$jenis}", 'tipe_kas' => $jenis === 'tunai' ? 'tunai' : 'bank', 'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true]
            );
        }
        $kasir = $this->buatUser('kasir', $cabang->id);
        $item = Item::where('kode_item', 'PJ-MNM-001')->firstOrFail();
        Stock::updateOrCreate(['item_id' => $item->id, 'lokasi_id' => $cabang->id], ['qty' => 100, 'qty_minimum' => 0]);

        return [$cabang, $kasir, $item];
    }

    private function order($cabang, $kasir, $item, array $payments, float $harga = 5000)
    {
        $total = array_sum(array_column($payments, 'jumlah'));
        // 4.47 — harga di luar harga produk hanya dihormati utk pemegang izin Ubah Harga (Owner)
        $this->actingAs($this->buatUser('owner'));

        return app(PenjualanService::class)->buatOrder([
            'kasir_id' => $kasir->id, 'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway',
            'nama_pelanggan' => 'Test',
            'items' => [['item_id' => $item->id, 'qty' => 1, 'harga_satuan' => $harga, 'satuan' => $item->satuan, 'nama_item' => $item->nama_item]],
            'payments' => $payments,
            'tipe_pembayaran' => $payments[0]['metode'], 'jumlah_bayar' => $total,
        ], $cabang->id);
    }

    public function test_bayar_lebih_kas_hanya_bertambah_sebesar_total_order(): void
    {
        [$cabang, $kasir, $item] = $this->siapkan();
        $kas = Kas::where('cabang_id', $cabang->id)->where('default_untuk', 'tunai')->first();
        $saldoAwal = (float) $kas->saldo_sekarang;

        $order = $this->order($cabang, $kasir, $item, [['metode' => 'tunai', 'jumlah' => 50000, 'kas_id' => $kas->id]]);

        $this->assertEquals($saldoAwal + 5000, (float) $kas->fresh()->saldo_sekarang);
        $this->assertEquals(5000, (float) TransaksiKeuangan::where('referensi_type', 'order')->where('referensi_id', $order->id)->sum('jumlah'));
        $this->assertEquals(5000, (float) OrderPayment::where('order_id', $order->id)->sum('jumlah'));
        // Struk tetap menampilkan uang yang diserahkan & kembalian.
        $this->assertEquals(50000, (float) $order->jumlah_bayar);
        $this->assertEquals(45000, (float) $order->kembalian);
    }

    public function test_bayar_pas_tidak_berubah(): void
    {
        [$cabang, $kasir, $item] = $this->siapkan();
        $kas = Kas::where('cabang_id', $cabang->id)->where('default_untuk', 'tunai')->first();
        $saldoAwal = (float) $kas->saldo_sekarang;

        $order = $this->order($cabang, $kasir, $item, [['metode' => 'tunai', 'jumlah' => 5000, 'kas_id' => $kas->id]]);

        $this->assertEquals($saldoAwal + 5000, (float) $kas->fresh()->saldo_sekarang);
        $this->assertEquals(0, (float) $order->kembalian);
    }

    public function test_split_kembalian_dipotong_dari_tunai_bukan_transfer(): void
    {
        [$cabang, $kasir, $item] = $this->siapkan();
        $tunai = Kas::where('cabang_id', $cabang->id)->where('default_untuk', 'tunai')->first();
        $transfer = Kas::where('cabang_id', $cabang->id)->where('default_untuk', 'transfer')->first();
        $t0 = (float) $tunai->saldo_sekarang;
        $b0 = (float) $transfer->saldo_sekarang;

        // Total 5.000: transfer 3.000 + tunai 10.000 -> kembali 8.000 (dari tunai).
        $this->order($cabang, $kasir, $item, [
            ['metode' => 'transfer', 'jumlah' => 3000, 'kas_id' => $transfer->id],
            ['metode' => 'tunai', 'jumlah' => 10000, 'kas_id' => $tunai->id],
        ]);

        $this->assertEquals($b0 + 3000, (float) $transfer->fresh()->saldo_sekarang);
        $this->assertEquals($t0 + 2000, (float) $tunai->fresh()->saldo_sekarang);
    }

    public function test_batalkan_order_dengan_kembalian_mengembalikan_kas_tepat(): void
    {
        [$cabang, $kasir, $item] = $this->siapkan();
        $kas = Kas::where('cabang_id', $cabang->id)->where('default_untuk', 'tunai')->first();
        $saldoAwal = (float) $kas->saldo_sekarang;

        $order = $this->order($cabang, $kasir, $item, [['metode' => 'tunai', 'jumlah' => 50000, 'kas_id' => $kas->id]]);
        app(PenjualanService::class)->batalkan($order);

        $this->assertEquals($saldoAwal, (float) $kas->fresh()->saldo_sekarang);
    }
}
