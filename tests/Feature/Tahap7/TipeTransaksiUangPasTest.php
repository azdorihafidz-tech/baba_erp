<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use App\Models\Order;
use App\Models\Stock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * CLAUDE.md 4.41 — pilihan Tipe Transaksi di POS bisa disembunyikan per
 * cabang (tipe bawaan), tipe otomatis terpilih kalau hanya 1 aktif, dan
 * tombol Uang Pas + nominal pintar.
 */
class TipeTransaksiUangPasTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $c;
    private Item $produk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->c = Cabang::create(['nama_cabang' => 'Outlet TT', 'kode_cabang' => 'TT' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true,
            'dine_in_aktif' => true, 'takeaway_aktif' => true, 'frozen_aktif' => true, 'wajib_bukti_transfer' => false, 'wajib_bukti_qris' => false]);
        foreach (['tunai', 'transfer', 'qris'] as $jenis) {
            Kas::create(['cabang_id' => $this->c->id, 'default_untuk' => $jenis, 'nama_kas' => "Kas {$jenis} TT", 'tipe_kas' => $jenis === 'tunai' ? 'tunai' : 'bank', 'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true]);
        }
        $kategori = \App\Models\ItemCategory::where('kode_kategori', '!=', 'TMB')->value('id');
        $this->produk = Item::create(['cabang_id' => $this->c->id, 'item_category_id' => $kategori, 'kode_item' => 'TT-' . uniqid(), 'nama_item' => 'Air TT',
            'tipe' => 'produk_jual', 'satuan' => 'botol', 'harga_jual' => 5000, 'is_active' => true]);
        Stock::create(['item_id' => $this->produk->id, 'lokasi_id' => $this->c->id, 'qty' => 50, 'qty_minimum' => 0]);
    }

    private function kasir()
    {
        return $this->actingAs($this->buatUser('kasir', $this->c->id))->withSession(['active_cabang_id' => $this->c->id]);
    }

    private function payloadOrder(array $extra = []): array
    {
        return array_merge([
            'nama_pelanggan' => 'Walk-in',
            'items' => [['item_id' => $this->produk->id, 'qty' => 1, 'harga_satuan' => 5000, 'satuan' => 'botol', 'nama_item' => 'Air TT']],
            'payments' => [['metode' => 'tunai', 'jumlah' => 5000]],
            'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => 5000,
        ], $extra);
    }

    public function test_pengaturan_cabang_simpan_saklar_dan_tipe_bawaan(): void
    {
        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->get(route('cabang.edit', $this->c))->assertOk()
            ->assertSee('name="pilih_tipe_transaksi"', false)->assertSee('name="tipe_transaksi_default"', false);

        $this->actingAs($owner)->put(route('cabang.update', $this->c), [
            'kode_cabang' => $this->c->kode_cabang, 'nama_cabang' => $this->c->nama_cabang, 'tipe' => 'cabang',
            'dine_in_aktif' => 1, 'takeaway_aktif' => 1, 'frozen_aktif' => 1,
            'pilih_tipe_transaksi' => 0, 'tipe_transaksi_default' => 'dine_in',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertFalse($this->c->fresh()->pilih_tipe_transaksi);
        $this->assertSame('dine_in', $this->c->fresh()->tipeTransaksiOtomatis());

        $this->actingAs($owner)->get(route('cabang.create'))->assertOk()->assertSee('name="pilih_tipe_transaksi"', false);
    }

    public function test_pos_pilihan_disembunyikan_pakai_tipe_bawaan(): void
    {
        $this->c->update(['pilih_tipe_transaksi' => false, 'tipe_transaksi_default' => 'takeaway']);

        $this->kasir()->get(route('penjualan.pos'))->assertOk()
            ->assertSee('id="tipeTransaksiInput" value="takeaway"', false)
            ->assertSee('(otomatis)')
            ->assertDontSee('<div class="card-header">Tipe Transaksi', false);

        // Server memakai tipe bawaan walau tipe_transaksi tidak terkirim
        $this->kasir()->post(route('penjualan.store'), $this->payloadOrder())->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('takeaway', Order::where('cabang_id', $this->c->id)->latest('id')->first()->tipe_transaksi->value);

        $this->kasir()->postJson(route('penjualan.simpan-bill'), [
            'nama_pelanggan' => 'Bill TT', 'items' => [['item_id' => $this->produk->id, 'nama_item' => 'Air TT', 'qty' => 1, 'satuan' => 'botol', 'harga_satuan' => 5000]],
        ])->assertOk();
        $this->assertSame('takeaway', Order::where('cabang_id', $this->c->id)->where('nama_pelanggan', 'Bill TT')->first()->tipe_transaksi->value);
    }

    public function test_hanya_satu_tipe_aktif_otomatis_terpilih(): void
    {
        $this->c->update(['dine_in_aktif' => false, 'frozen_aktif' => false]);

        $this->kasir()->get(route('penjualan.pos'))->assertOk()
            ->assertSee('id="tipeTransaksiInput" value="takeaway"', false)
            ->assertSee('<div class="card-header">Tipe Transaksi', false);
    }

    public function test_beberapa_tipe_aktif_kasir_wajib_memilih(): void
    {
        $this->kasir()->get(route('penjualan.pos'))->assertOk()->assertSee('id="tipeTransaksiInput" value=""', false);
        $this->kasir()->post(route('penjualan.store'), $this->payloadOrder())->assertSessionHasErrors('tipe_transaksi');
    }

    public function test_pos_punya_tombol_uang_pas_dan_nominal_pintar(): void
    {
        $this->kasir()->get(route('penjualan.pos'))->assertOk()
            ->assertSee('id="nominalCepat"', false)
            ->assertSee('function renderNominalCepat', false)
            ->assertSee('Uang Pas', false)
            ->assertDontSee('setBayar(150000)', false);
    }
}
