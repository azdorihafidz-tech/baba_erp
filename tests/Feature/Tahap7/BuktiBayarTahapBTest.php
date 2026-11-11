<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Kas;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\PenjualanService;
use Database\Seeders\Perbaikan438PanduanSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap B (CLAUDE.md 4.38, 2026-10-18) — saklar wajib bukti Transfer/QRIS
 * per cabang, dicek di server (transaksi baru & bayar Bill Tersimpan).
 */
class BuktiBayarTahapBTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function outlet(bool $wajibTransfer = true, bool $wajibQris = true): Cabang
    {
        $c = Cabang::create([
            'nama_cabang' => 'Outlet Bukti ' . uniqid(), 'kode_cabang' => 'BKT' . rand(1000, 9999), 'tipe' => 'cabang',
            'is_active' => true, 'dine_in_aktif' => true, 'takeaway_aktif' => true, 'frozen_aktif' => true,
            'wajib_bukti_transfer' => $wajibTransfer, 'wajib_bukti_qris' => $wajibQris,
        ]);
        foreach (['tunai', 'transfer', 'qris'] as $m) {
            Kas::create(['cabang_id' => $c->id, 'nama_kas' => 'Kas ' . $m, 'tipe_kas' => $m === 'tunai' ? 'tunai' : 'bank',
                'default_untuk' => $m, 'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true]);
        }

        return $c;
    }

    private function bill(Cabang $c, float $total = 20000): Order
    {
        $o = Order::create([
            'cabang_id' => $c->id, 'nomor_order' => 'BKT-' . uniqid(), 'tanggal_order' => now()->toDateString(),
            'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway', 'status' => 'pending',
            'total_harga' => $total, 'diskon' => 0, 'total_bayar' => $total, 'jumlah_bayar' => 0, 'kembalian' => 0, 'tipe_pembayaran' => 'tunai',
        ]);
        OrderItem::create(['order_id' => $o->id, 'nama_item' => 'Custom', 'qty' => 1, 'satuan' => 'pcs', 'harga_satuan' => $total, 'total_harga' => $total, 'hpp' => 0]);

        return $o;
    }

    private function cekBukti(Cabang $c, array $metode, ?string $bukti): ?string
    {
        $m = new \ReflectionMethod(PenjualanService::class, 'pastikanBuktiPembayaran');
        $m->setAccessible(true);
        try {
            $m->invoke(app(PenjualanService::class), $c, array_map(fn ($x) => ['metode' => $x, 'jumlah' => 1], $metode), $bukti);

            return null;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function test_aturan_wajib_bukti_mengikuti_saklar_cabang(): void
    {
        $semua = $this->outlet(true, true);
        $hanyaQris = $this->outlet(false, true);
        $mati = $this->outlet(false, false);

        $this->assertNull($this->cekBukti($semua, ['tunai'], null));
        $this->assertStringContainsString('Transfer', $this->cekBukti($semua, ['transfer'], null));
        $this->assertStringContainsString('QRIS', $this->cekBukti($semua, ['qris'], null));
        $this->assertNull($this->cekBukti($semua, ['transfer'], 'bukti/x.jpg'));

        $this->assertNull($this->cekBukti($hanyaQris, ['transfer'], null));
        $this->assertNotNull($this->cekBukti($hanyaQris, ['qris'], null));
        $this->assertNotNull($this->cekBukti($hanyaQris, ['tunai', 'qris'], null), 'split: bagian QRIS tetap wajib');

        $this->assertNull($this->cekBukti($mati, ['transfer', 'qris'], null));
    }

    public function test_bayar_bill_transfer_tanpa_bukti_ditolak_saat_wajib(): void
    {
        $c = $this->outlet(true, true);
        $o = $this->bill($c);
        $kasir = $this->buatUser('kasir', $c->id);

        $this->actingAs($kasir)->withSession(['active_cabang_id' => $c->id])
            ->postJson(route('penjualan.charge-bill', $o), ['payments' => [['metode' => 'transfer', 'jumlah' => 20000]]])
            ->assertStatus(422)->assertJsonFragment(['success' => false]);

        $this->assertSame('pending', $o->fresh()->status->value);
    }

    public function test_bayar_bill_transfer_dengan_bukti_berhasil(): void
    {
        Storage::fake('public');
        $c = $this->outlet(true, true);
        $o = $this->bill($c);
        $kasir = $this->buatUser('kasir', $c->id);

        $this->actingAs($kasir)->withSession(['active_cabang_id' => $c->id])
            ->post(route('penjualan.charge-bill', $o), [
                'payments' => [['metode' => 'transfer', 'jumlah' => 20000]],
                'bukti_pembayaran' => UploadedFile::fake()->image('bukti.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonFragment(['success' => true]);

        $this->assertSame('selesai', $o->fresh()->status->value);
        $this->assertNotNull($o->fresh()->bukti_pembayaran);
    }

    public function test_bayar_bill_transfer_tanpa_bukti_boleh_saat_saklar_mati(): void
    {
        $c = $this->outlet(false, false);
        $o = $this->bill($c);
        $kasir = $this->buatUser('kasir', $c->id);

        $this->actingAs($kasir)->withSession(['active_cabang_id' => $c->id])
            ->postJson(route('penjualan.charge-bill', $o), ['payments' => [['metode' => 'qris', 'jumlah' => 20000]]])
            ->assertOk();

        $this->assertSame('selesai', $o->fresh()->status->value);
    }

    public function test_tunai_tidak_pernah_butuh_bukti(): void
    {
        $c = $this->outlet(true, true);
        $o = $this->bill($c);
        $kasir = $this->buatUser('kasir', $c->id);

        $this->actingAs($kasir)->withSession(['active_cabang_id' => $c->id])
            ->postJson(route('penjualan.charge-bill', $o), ['payments' => [['metode' => 'tunai', 'jumlah' => 20000]]])
            ->assertOk();
    }

    public function test_form_cabang_pos_dan_panduan_tampil(): void
    {
        $this->seed(Perbaikan438PanduanSeeder::class);
        $owner = $this->buatUser('owner');
        $c = $this->outlet(false, true);

        $this->actingAs($owner)->get(route('cabang.edit', $c))->assertOk()
            ->assertSee('name="wajib_bukti_transfer"', false)->assertSee('name="wajib_bukti_qris"', false)
            ->assertSee('panduanModal-cabang', false);
        $this->actingAs($owner)->get(route('cabang.create'))->assertOk()->assertSee('name="wajib_bukti_qris"', false);
        $this->actingAs($owner)->get(route('cabang.index'))->assertOk()->assertSee('panduanModal-cabang', false);

        $kasir = $this->buatUser('kasir', $c->id);
        $this->actingAs($kasir)->withSession(['active_cabang_id' => $c->id])->get(route('penjualan.pos'))->assertOk()
            ->assertSee('window.POS_WAJIB_BUKTI = { transfer: false, qris: true }', false)
            ->assertSee('id="modalBillBukti"', false);
    }

    public function test_simpan_saklar_lewat_model_dan_default_aktif(): void
    {
        $c = Cabang::create(['nama_cabang' => 'Default ' . uniqid(), 'kode_cabang' => 'DFT' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true]);
        $c->refresh();
        $this->assertTrue($c->wajib_bukti_transfer);
        $this->assertTrue($c->wajib_bukti_qris);
    }
}
