<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use App\Models\ProduksiBatch;
use App\Models\ResepBumbu;
use App\Models\ResepBumbuItem;
use App\Models\ResepProduksi;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Services\PenjualanService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * 4.49 Tahap B1 — Produksi bahan setengah jadi di gudang (porting coffeeshop
 * 1139fe5). Contoh: Isian Ayam dari daging ayam (stok kg, takaran g), tepung
 * (stok kg) dan kecap (stok liter, takaran ml), lalu dipakai di komposisi
 * Dimsum Ayam. Data barang Dimsum terpisah per cabang (4.39) → semua barang
 * milik gudang uji.
 */
class ProduksiTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $gudang;
    private Cabang $outlet;
    private Item $ayam;
    private Item $tepung;
    private Item $kecap;
    private Item $isian;

    protected function setUp(): void
    {
        parent::setUp();
        $u = uniqid();
        $this->gudang = Cabang::create(['nama_cabang' => "Gudang Uji {$u}", 'kode_cabang' => 'GU' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        $this->outlet = Cabang::create(['nama_cabang' => "Outlet Uji {$u}", 'kode_cabang' => 'OU' . rand(10000, 99999), 'tipe' => 'cabang', 'is_active' => true]);

        $this->ayam = $this->barang('Daging Ayam Uji', 'kg', 40000);
        $this->tepung = $this->barang('Tepung Uji', 'kg', 10000);
        $this->kecap = $this->barang('Kecap Uji', 'liter', 20000);
        $this->isian = $this->barang('Isian Ayam Uji', 'kg', 0);

        $stok = app(StokService::class);
        $this->actingAs($this->buatUser('owner'));
        $stok->masuk($this->ayam->id, $this->gudang->id, 3, 'uji', 'uji', null, 40000);
        $stok->masuk($this->tepung->id, $this->gudang->id, 2, 'uji', 'uji', null, 10000);
        $stok->masuk($this->kecap->id, $this->gudang->id, 5, 'uji', 'uji', null, 20000);
    }

    private function barang(string $nama, string $satuan, float $harga, ?Cabang $milik = null): Item
    {
        return Item::create([
            'kode_item' => 'PRDUJI-' . strtoupper(substr(md5($nama . uniqid()), 0, 8)), 'nama_item' => $nama,
            'tipe' => 'bahan_baku', 'satuan' => $satuan, 'harga_beli_terakhir' => $harga, 'harga_jual' => 0,
            'is_active' => true, 'cabang_id' => ($milik ?? $this->gudang)->id,
        ]);
    }

    private function stok(Item $i, ?Cabang $c = null): float
    {
        return (float) Stock::where('item_id', $i->id)->where('lokasi_id', ($c ?? $this->gudang)->id)->value('qty');
    }

    /** Resep: 1 kg Isian Ayam = ayam 200 g + tepung 100 g + kecap 300 ml. */
    private function resep(): ResepProduksi
    {
        $this->actingAs($this->buatUser('owner'))->post(route('produksi.resep.store'), [
            'cabang_id' => $this->gudang->id, 'item_id' => $this->isian->id, 'qty_hasil' => 1, 'is_active' => 1,
            'bahan' => [
                ['item_id' => $this->ayam->id, 'qty' => 200, 'satuan' => 'g'],
                ['item_id' => $this->tepung->id, 'qty' => 100, 'satuan' => 'g'],
                ['item_id' => $this->kecap->id, 'qty' => 300, 'satuan' => 'ml'],
                ['item_id' => '', 'qty' => '', 'satuan' => ''], // baris kosong diabaikan
            ],
        ])->assertRedirect(route('produksi.resep.index'))->assertSessionHas('success');

        return ResepProduksi::where('item_id', $this->isian->id)->firstOrFail();
    }

    private function produksi(array $ubah = [], ?\App\Models\User $user = null)
    {
        $resep = ResepProduksi::where('item_id', $this->isian->id)->first() ?? $this->resep();

        return $this->actingAs($user ?? $this->buatUser('owner'))->post(route('produksi.store'), array_merge([
            'cabang_id' => $this->gudang->id, 'resep_produksi_id' => $resep->id, 'tanggal' => today()->toDateString(),
            'qty_rencana' => 5, 'qty_hasil' => 4.8, 'catatan' => 'masak pagi',
        ], $ubah));
    }

    public function test_resep_tersimpan_dan_validasi(): void
    {
        $resep = $this->resep();
        $this->assertCount(3, $resep->items);
        $this->assertSame([$this->ayam->id => 0.2, $this->tepung->id => 0.1, $this->kecap->id => 0.3], $resep->fresh('items.bahan')->kebutuhan(1));

        $owner = $this->buatUser('owner');
        // Barang hasil dipakai sebagai bahan sendiri → ditolak
        $this->actingAs($owner)->post(route('produksi.resep.store'), [
            'cabang_id' => $this->gudang->id, 'item_id' => $this->ayam->id, 'qty_hasil' => 1,
            'bahan' => [['item_id' => $this->ayam->id, 'qty' => 1, 'satuan' => 'kg']],
        ])->assertSessionHas('error');
        // Barang yang sudah punya resep → ditolak (satu barang satu resep)
        $this->actingAs($owner)->post(route('produksi.resep.store'), [
            'cabang_id' => $this->gudang->id, 'item_id' => $this->isian->id, 'qty_hasil' => 1,
            'bahan' => [['item_id' => $this->tepung->id, 'qty' => 1, 'satuan' => 'kg']],
        ])->assertSessionHas('error');
        $this->assertSame(1, ResepProduksi::where('item_id', $this->isian->id)->count());

        // Bahan milik cabang lain (data barang per cabang) → ditolak
        $bahanOutlet = $this->barang('Tepung Outlet Uji', 'kg', 10000, $this->outlet);
        $hasilLain = $this->barang('Saus Uji', 'liter', 0);
        $this->actingAs($owner)->post(route('produksi.resep.store'), [
            'cabang_id' => $this->gudang->id, 'item_id' => $hasilLain->id, 'qty_hasil' => 1,
            'bahan' => [['item_id' => $bahanOutlet->id, 'qty' => 1, 'satuan' => 'kg']],
        ])->assertSessionHas('error');
        // Gudang bukan tipe gudang (outlet) → ditolak validasi
        $this->actingAs($owner)->post(route('produksi.resep.store'), [
            'cabang_id' => $this->outlet->id, 'item_id' => $hasilLain->id, 'qty_hasil' => 1,
            'bahan' => [['item_id' => $this->tepung->id, 'qty' => 1, 'satuan' => 'kg']],
        ])->assertSessionHasErrors('cabang_id');
        $this->assertNull(ResepProduksi::where('item_id', $hasilLain->id)->first());

        // Ubah resep
        $this->actingAs($owner)->put(route('produksi.resep.update', $resep), [
            'cabang_id' => $this->gudang->id, 'item_id' => $this->isian->id, 'qty_hasil' => 2, 'is_active' => 1,
            'bahan' => [['item_id' => $this->tepung->id, 'qty' => 0.2, 'satuan' => 'kg']],
        ])->assertRedirect(route('produksi.resep.index'));
        $this->assertSame(2.0, (float) $resep->fresh()->qty_hasil);
        $this->assertCount(1, $resep->fresh()->items);
    }

    public function test_produksi_memotong_bahan_dan_menambah_stok_hasil_dengan_modal_fifo(): void
    {
        $res = $this->produksi();
        $batch = ProduksiBatch::latest('id')->firstOrFail();
        $res->assertRedirect(route('produksi.show', $batch));

        // Bahan = resep × 5: ayam 1 kg, tepung 0,5 kg, kecap 1,5 liter
        $this->assertEqualsWithDelta(2.0, $this->stok($this->ayam), 0.0001);
        $this->assertEqualsWithDelta(1.5, $this->stok($this->tepung), 0.0001);
        $this->assertEqualsWithDelta(3.5, $this->stok($this->kecap), 0.0001);
        $this->assertEqualsWithDelta(4.8, $this->stok($this->isian), 0.0001);

        // Biaya FIFO: 1×40.000 + 0,5×10.000 + 1,5×20.000 = 75.000 → ÷ 4,8 = 15.625 / kg
        $this->assertEqualsWithDelta(75000, (float) $batch->biaya_bahan, 0.01);
        $this->assertEqualsWithDelta(15625, (float) $batch->modal_otomatis, 0.01);
        $this->assertEqualsWithDelta(15625, (float) $batch->modal_per_unit, 0.01);
        $this->assertFalse($batch->modal_manual);
        $this->assertSame('PRD-' . now()->format('Ym') . '-', substr($batch->kode_batch, 0, 11));
        $this->assertCount(3, $batch->bahan);

        // Batch FIFO hasil ber-modal produksi; Harga Standar yang kosong diisi
        $b = StockBatch::where('item_id', $this->isian->id)->where('lokasi_id', $this->gudang->id)->firstOrFail();
        $this->assertEqualsWithDelta(15625, (float) $b->harga_beli_per_unit, 0.01);
        $this->assertEqualsWithDelta(15625, (float) $this->isian->fresh()->harga_beli_terakhir, 0.01);

        $this->actingAs($this->buatUser('owner'))->get(route('produksi.show', $batch))->assertOk()->assertSee($batch->kode_batch);
        $this->get(route('produksi.index'))->assertOk()->assertSee($batch->kode_batch);
    }

    public function test_harga_standar_yang_sudah_diisi_tidak_ditimpa_dan_modal_manual(): void
    {
        $this->isian->update(['harga_beli_terakhir' => 12000]);
        $this->produksi(['pakai_modal_manual' => 1, 'modal_manual' => 20000])->assertSessionHasNoErrors();
        $batch = ProduksiBatch::latest('id')->firstOrFail();
        $this->assertTrue($batch->modal_manual);
        $this->assertEqualsWithDelta(20000, (float) $batch->modal_per_unit, 0.01);
        $this->assertEqualsWithDelta(15625, (float) $batch->modal_otomatis, 0.01);
        $this->assertEqualsWithDelta(12000, (float) $this->isian->fresh()->harga_beli_terakhir, 0.01); // aturan 4.38
    }

    public function test_stok_kurang_ditolak_dengan_pesan_dan_stok_utuh(): void
    {
        $this->produksi(['qty_rencana' => 20, 'qty_hasil' => 20])->assertSessionHas('error');
        $this->assertStringContainsString('Daging Ayam Uji', session('error'));
        $this->assertStringContainsString('Kecap Uji', session('error'));
        $this->assertEqualsWithDelta(3.0, $this->stok($this->ayam), 0.0001);
        $this->assertSame(0, ProduksiBatch::where('item_id', $this->isian->id)->count());
    }

    public function test_hak_akses_dan_hanya_gudang(): void
    {
        $this->resep();
        // Kasir tidak punya izin produksi
        $this->actingAs($this->buatUser('kasir', $this->outlet->id))->get(route('produksi.index'))->assertForbidden();
        // Operator produksi: boleh catat, tidak boleh kelola resep / batalkan
        $op = $this->buatUser('operator_produksi', $this->gudang->id);
        $this->actingAs($op)->get(route('produksi.create'))->assertOk();
        $this->actingAs($op)->get(route('produksi.resep.create'))->assertForbidden();
        $this->produksi([], $op)->assertSessionHasNoErrors();
        $batch = ProduksiBatch::latest('id')->firstOrFail();
        $this->actingAs($op)->post(route('produksi.batalkan', $batch), ['alasan' => 'coba'])->assertForbidden();
        // Admin gudang yang tidak ditugaskan ke gudang ini tidak bisa melihat/mencatat
        $lain = $this->buatUser('admin_gudang', $this->outlet->id);
        $this->actingAs($lain)->get(route('produksi.show', $batch))->assertNotFound();
        $this->produksi([], $lain)->assertSessionHasErrors('cabang_id');
    }

    public function test_batalkan_mengembalikan_stok_dan_ditolak_bila_hasil_terpakai(): void
    {
        $this->produksi();
        $batch = ProduksiBatch::latest('id')->firstOrFail();
        $owner = $this->buatUser('owner');

        $this->actingAs($owner)->post(route('produksi.batalkan', $batch), ['alasan' => 'salah input'])->assertSessionHas('success');
        $this->assertSame('dibatalkan', $batch->fresh()->status);
        $this->assertEqualsWithDelta(3.0, $this->stok($this->ayam), 0.0001);
        $this->assertEqualsWithDelta(2.0, $this->stok($this->tepung), 0.0001);
        $this->assertEqualsWithDelta(5.0, $this->stok($this->kecap), 0.0001);
        $this->assertEqualsWithDelta(0.0, $this->stok($this->isian), 0.0001);
        // Nilai FIFO bahan kembali: ayam 3 × 40.000
        $nilai = StockBatch::where('item_id', $this->ayam->id)->where('lokasi_id', $this->gudang->id)->get()->sum(fn ($b) => $b->qty_sisa * $b->harga_beli_per_unit);
        $this->assertEqualsWithDelta(120000, $nilai, 0.5);
        // Batal dua kali ditolak
        $this->actingAs($owner)->post(route('produksi.batalkan', $batch), ['alasan' => 'lagi'])->assertSessionHas('error');

        // Produksi lagi, hasilnya terpakai sebagian → batal ditolak
        $this->produksi();
        $batch2 = ProduksiBatch::latest('id')->firstOrFail();
        $this->actingAs($owner);
        app(StokService::class)->keluar($this->isian->id, $this->gudang->id, 1, 'terpakai');
        $this->post(route('produksi.batalkan', $batch2), ['alasan' => 'salah'])->assertSessionHas('error');
        $this->assertSame('selesai', $batch2->fresh()->status);
    }

    public function test_hasil_produksi_dipakai_komposisi_produk_jual_hpp_ikut_modal_produksi(): void
    {
        $this->produksi();   // 4,8 kg Isian Ayam @15.625
        Cabang::whereKey($this->gudang->id)->update(['wajib_bukti_transfer' => false, 'wajib_bukti_qris' => false]);
        $kas = Kas::create(['cabang_id' => $this->gudang->id, 'default_untuk' => 'tunai', 'nama_kas' => 'Kas Tunai Gudang Uji',
            'tipe_kas' => 'tunai', 'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true]);

        $dimsum = Item::create(['cabang_id' => $this->gudang->id, 'kode_item' => 'PJ-UJI-' . uniqid(), 'nama_item' => 'Dimsum Ayam Uji',
            'tipe' => 'produk_jual', 'satuan' => 'porsi', 'harga_jual' => 10000, 'is_active' => true, 'track_stok' => false]);
        $r = ResepBumbu::create(['cabang_id' => $this->gudang->id, 'nama' => 'Dimsum Ayam Uji', 'kode' => 'R-' . substr(md5(uniqid()), 0, 12), 'item_id' => $dimsum->id, 'is_active' => true]);
        // 100 g isian per porsi (satuan stok kg)
        ResepBumbuItem::create(['resep_bumbu_id' => $r->id, 'item_id' => $this->isian->id, 'qty_per_unit' => 100, 'satuan' => 'g', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 0]);

        $kasir = $this->buatUser('kasir', $this->gudang->id);
        $this->actingAs($kasir);
        $order = app(PenjualanService::class)->buatOrder([
            'kasir_id' => $kasir->id, 'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway', 'nama_pelanggan' => 'Uji',
            'items' => [['item_id' => $dimsum->id, 'qty' => 2, 'harga_satuan' => 10000, 'satuan' => 'porsi', 'nama_item' => $dimsum->nama_item]],
            'payments' => [['metode' => 'tunai', 'jumlah' => 20000, 'kas_id' => $kas->id]],
            'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => 20000,
        ], $this->gudang->id);

        // POS memotong Isian Ayam (bukan bahan mentahnya): 2 × 0,1 kg
        $this->assertEqualsWithDelta(4.6, $this->stok($this->isian), 0.0001);
        $this->assertEqualsWithDelta(2.0, $this->stok($this->ayam), 0.0001);
        // HPP = 0,2 kg × modal produksi 15.625 = 3.125
        $this->assertEqualsWithDelta(3125, (float) $order->items->sum('hpp'), 0.5);
    }

    public function test_halaman_dan_menu(): void
    {
        $this->resep();
        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->withSession(['active_cabang_id' => $this->gudang->id]);
        $this->get(route('produksi.resep.index'))->assertOk()->assertSee('Isian Ayam Uji')->assertSee('panduanModal-produksi', false);
        $this->get(route('produksi.resep.create', ['cabang_id' => $this->gudang->id]))->assertOk()->assertSee('Daging Ayam Uji');
        $this->get(route('produksi.create'))->assertOk()->assertSee('id="formProduksi"', false);
        $this->get(route('produksi.index'))->assertOk()->assertSee('href="' . route('produksi.index') . '"', false);
    }
}
