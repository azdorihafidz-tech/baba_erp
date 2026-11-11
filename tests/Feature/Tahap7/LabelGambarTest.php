<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Panduan;
use App\Services\CupLabelService;
use Database\Seeders\Porting449Seeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * 4.49 Tahap D — Label Produk dicetak sebagai gambar (porting coffeeshop
 * 38bcfde/d285822/8a72d4d): geser per cabang, catatan pesanan, tanggal di
 * sebelah jam, Exp Frozen tetap ada. Bawaan label tetap MATI per cabang.
 */
class LabelGambarTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cabang = Cabang::where('tipe', 'cabang')->where('is_active', true)->orderBy('id')->firstOrFail();
    }

    private function orderBerlabel(array $atribut = []): Order
    {
        $kat = ItemCategory::firstOrCreate(['kode_kategori' => 'DIM'], ['nama_kategori' => 'Uji DIM']);
        $item = Item::create([
            'kode_item' => 'LBG-' . strtoupper(substr(uniqid(), -6)), 'nama_item' => 'Dimsum Mentai',
            'tipe' => 'produk_jual', 'item_category_id' => $kat->id, 'satuan' => 'box',
            'harga_jual' => 20000, 'is_active' => true, 'cabang_id' => $this->cabang->id,
        ]);
        $this->cabang->update(['label_cup_aktif' => true, 'label_cup_kategori' => ['DIM']]);
        $o = Order::create($atribut + [
            'cabang_id' => $this->cabang->id, 'nomor_order' => 'ORD-LBG-' . uniqid() . '-0042',
            'tanggal_order' => today(), 'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway',
            'nama_pelanggan' => 'Budi', 'total_harga' => 40000, 'total_bayar' => 40000, 'jumlah_bayar' => 40000,
            'tipe_pembayaran' => 'tunai', 'status' => 'selesai', 'catatan' => 'Saus dipisah semua',
        ]);
        OrderItem::create([
            'order_id' => $o->id, 'item_id' => $item->id, 'nama_item' => $item->nama_item, 'qty' => 2,
            'satuan' => 'box', 'harga_satuan' => 20000, 'total_harga' => 40000, 'hpp' => 0, 'catatan' => 'pedas',
        ]);

        return $o;
    }

    public function test_bawaan_geser_nol_dan_label_tetap_mati(): void
    {
        $c = Cabang::where('tipe', 'cabang')->get();
        $this->assertTrue($c->every(fn ($x) => (float) $x->label_cup_geser_x === 0.0 && (float) $x->label_cup_geser_y === 0.0));
        $hasil = app(CupLabelService::class)->untukOrder($this->orderBerlabel()->setRelation('cabang', null));
        $this->assertSame([], $hasil['labels']);  // cabang tidak diketahui = tidak ada label
    }

    public function test_label_membawa_geser_catatan_pesanan_dan_tanggal(): void
    {
        $order = $this->orderBerlabel();
        $this->cabang->update(['label_cup_geser_x' => -3.5, 'label_cup_geser_y' => 1]);
        $hasil = app(CupLabelService::class)->untukOrder($order->fresh());

        $this->assertSame(['x' => -3.5, 'y' => 1.0], $hasil['geser']);
        $this->assertCount(2, $hasil['labels']);
        $l = $hasil['labels'][0];
        $this->assertSame('pedas', $l['catatan']);
        $this->assertSame('Saus dipisah semua', $l['catatan_order']);
        $this->assertStringEndsWith(' ' . $order->created_at->format('H:i d/m/Y'), $l['info']);
    }

    public function test_frozen_exp_tetap_ada(): void
    {
        $order = $this->orderBerlabel(['tipe_transaksi' => 'frozen', 'tanggal_expired_frozen' => '2026-12-31']);
        $l = app(CupLabelService::class)->untukOrder($order)['labels'][0];
        $this->assertSame('31/12/2026', $l['exp']);
        $this->assertStringStartsWith('Frozen · ', $l['info']);
    }

    public function test_geser_disimpan_dari_cabang_edit_dan_divalidasi(): void
    {
        $owner = $this->buatUser('owner', $this->cabang->id);
        $this->actingAs($owner)->get(route('cabang.edit', $this->cabang))->assertOk()
            ->assertSee('name="label_cup_geser_x"', false)->assertSee('name="label_cup_geser_y"', false);

        $payload = [
            'nama_cabang' => $this->cabang->nama_cabang, 'kode_cabang' => $this->cabang->kode_cabang,
            'tipe' => $this->cabang->tipe->value, 'is_active' => 1, 'label_cup_form' => 1,
            'label_cup_geser_x' => '-3.5', 'label_cup_geser_y' => '',
        ];
        $this->actingAs($owner)->put(route('cabang.update', $this->cabang), $payload)->assertSessionHasNoErrors();
        $c = $this->cabang->fresh();
        $this->assertSame(-3.5, $c->label_cup_geser_x);
        $this->assertSame(0.0, $c->label_cup_geser_y);

        $this->actingAs($owner)->put(route('cabang.update', $this->cabang), ['label_cup_geser_x' => 25] + $payload)
            ->assertSessionHasErrors('label_cup_geser_x');
    }

    public function test_overlay_versi_gambar_ada_di_halaman(): void
    {
        $owner = $this->buatUser('owner', $this->cabang->id);
        $this->actingAs($owner)->followingRedirects()->get(route('dashboard'))->assertOk()
            ->assertSee('id="cupLabelPosisi"', false)
            ->assertSee('function gambarLabel', false)
            ->assertSee("'\\r\\nBITMAP 0,0,'", false)
            ->assertSee('Label Produk — <span id="cupLabelCount">', false)
            ->assertSee("if (l.exp) out.push('Exp: ' + l.exp);", false)
            ->assertDontSee('Label Cup —', false);
    }

    public function test_panduan_dan_tooltip_idempotent(): void
    {
        (new Porting449Seeder())->run();
        (new Porting449Seeder())->run();
        $konten = (string) Panduan::getBySlug('label-produk')?->konten;
        $this->assertSame(1, substr_count($konten, '<!-- LABEL-GAMBAR-449 -->'));
        $this->assertStringContainsString(Porting449Seeder::KALIMAT_BAHASA_PRINTER, $konten);
        $this->assertStringNotContainsString('**ESC/POS** (mis. Blueprint ECO80BT)', $konten);
        $this->assertDatabaseHas('tooltips', ['key' => 'label_cup.posisi']);
        $this->assertDatabaseHas('tooltips', ['key' => 'cabang.label_cup_geser']);
    }
}
