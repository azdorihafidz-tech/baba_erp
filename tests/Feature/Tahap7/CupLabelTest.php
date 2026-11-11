<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Panduan;
use App\Models\Permission;
use App\Models\Tooltip;
use App\Services\CupLabelService;
use Database\Seeders\LabelProdukSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * 4.44 Poin 2 — Label Produk (stiker), porting erp-coffeeshop Label Cup.
 * Bawaan MATI per cabang: selama mati POS, struk, dan detail order sama
 * persis seperti sebelumnya.
 */
class CupLabelTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cabang = Cabang::where('tipe', 'cabang')->where('is_active', true)->orderBy('id')->firstOrFail();
    }

    private function produk(string $kodeKategori): Item
    {
        $kat = ItemCategory::firstOrCreate(['kode_kategori' => $kodeKategori], ['nama_kategori' => 'Uji ' . $kodeKategori]);

        return Item::create([
            'kode_item' => 'LBL-' . strtoupper(substr(uniqid(), -6)), 'nama_item' => 'Dimsum Uji ' . $kodeKategori,
            'tipe' => 'produk_jual', 'item_category_id' => $kat->id, 'satuan' => 'box',
            'harga_jual' => 20000, 'is_active' => true, 'cabang_id' => $this->cabang->id,
        ]);
    }

    private function order(array $baris, array $atribut = []): Order
    {
        $o = Order::create($atribut + [
            'cabang_id' => $this->cabang->id, 'nomor_order' => 'ORD-LBL-' . uniqid() . '-0042',
            'tanggal_order' => today(), 'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway',
            'nama_pelanggan' => 'Budi', 'total_harga' => 20000, 'total_bayar' => 20000, 'jumlah_bayar' => 20000,
            'tipe_pembayaran' => 'tunai', 'status' => 'selesai',
        ]);
        foreach ($baris as [$item, $qty, $catatan]) {
            OrderItem::create([
                'order_id' => $o->id, 'item_id' => $item->id, 'nama_item' => $item->nama_item, 'qty' => $qty,
                'satuan' => 'box', 'harga_satuan' => 20000, 'total_harga' => 20000 * $qty, 'hpp' => 0, 'catatan' => $catatan,
            ]);
        }

        return $o;
    }

    private function nyalakan(?array $kategori): void
    {
        $this->cabang->update(['label_cup_aktif' => true, 'label_cup_kategori' => $kategori]);
    }

    public function test_bawaan_mati_dan_ukuran_40x30_untuk_semua_cabang(): void
    {
        $this->assertTrue(Schema::hasColumns('cabangs', ['label_cup_aktif', 'label_cup_kategori', 'label_cup_lebar_mm', 'label_cup_tinggi_mm']));
        $this->assertSame(0, Cabang::where('label_cup_aktif', true)->count());
        $this->assertSame(40, (int) $this->cabang->label_cup_lebar_mm);
        $this->assertSame(30, (int) $this->cabang->label_cup_tinggi_mm);
    }

    public function test_selama_mati_struk_pos_dan_detail_order_tidak_berubah(): void
    {
        $order = $this->order([[$this->produk('DIM'), 2, null]]);
        $kasir = $this->buatUser('kasir', $this->cabang->id);

        $this->actingAs($kasir)->get(route('penjualan.struk-modal', $order))->assertOk()
            ->assertDontSee('Cetak Label (', false)
            ->assertSee('var LABEL_URL = null;', false);
        $this->actingAs($kasir)->get(route('penjualan.show', $order))->assertOk()
            ->assertDontSee('bi bi-tag me-1"></i>Cetak Label', false);
        $this->actingAs($kasir)->withSession(['active_cabang_id' => $this->cabang->id])
            ->get(route('penjualan.pos'))->assertOk()
            ->assertSee('window.POS_LABEL_AKTIF = false;', false);

        $this->actingAs($kasir)->getJson(route('label-cup.order', $order))->assertOk()
            ->assertJson(['aktif' => false, 'labels' => []]);
    }

    public function test_label_aktif_hanya_kategori_tercentang_satu_stiker_per_qty(): void
    {
        $this->nyalakan(['DIM']);
        $dimsum = $this->produk('DIM');
        $minum  = $this->produk('MNM');
        $order  = $this->order([[$dimsum, 2, 'saus dipisah'], [$minum, 1, null]]);
        $kasir  = $this->buatUser('kasir', $this->cabang->id);

        $res = $this->actingAs($kasir)->getJson(route('label-cup.order', $order))->assertOk()
            ->assertJsonPath('aktif', true)
            ->assertJsonPath('ukuran', ['lebar' => 40, 'tinggi' => 30])
            ->assertJsonCount(2, 'labels')
            ->assertJsonPath('labels.0.toko', 'BABA PARFUME')
            ->assertJsonPath('labels.0.nomor', '0042')
            ->assertJsonPath('labels.0.nama', mb_strtoupper($dimsum->nama_item))
            ->assertJsonPath('labels.0.catatan', 'saus dipisah')
            ->assertJsonPath('labels.0.pelanggan', 'Budi')
            ->assertJsonPath('labels.1.ke', 2)
            ->assertJsonPath('labels.1.dari', 2)
            ->json();
        $this->assertStringStartsWith('Takeaway · ', $res['labels'][0]['info']);
        $this->assertNull($res['labels'][0]['exp']);

        $this->actingAs($kasir)->get($res['print_url'])->assertOk()
            ->assertSee('Cetak 2 Label')->assertSee('saus dipisah')->assertSee('size: 40mm 30mm', false);

        $this->actingAs($kasir)->get(route('penjualan.struk-modal', $order))->assertOk()
            ->assertSee('Cetak Label (2 stiker)')
            ->assertSee('var LABEL_URL = ' . json_encode(route('label-cup.order', $order)), false);
        $this->actingAs($kasir)->get(route('penjualan.show', $order))->assertOk()
            ->assertSee('cupLabelOverlay', false)
            ->assertSee(route('label-cup.order', $order), false);
    }

    public function test_kategori_belum_diatur_berarti_semua_kategori_produk_jual(): void
    {
        $this->nyalakan(null);
        $order = $this->order([[$this->produk('DIM'), 1, null], [$this->produk('GYZ'), 1, null]]);

        $hasil = app(CupLabelService::class)->untukOrder($order);
        $this->assertCount(2, $hasil['labels']);

        $this->cabang->update(['label_cup_kategori' => []]);
        $this->assertCount(0, app(CupLabelService::class)->untukOrder($order->fresh())['labels']);
    }

    public function test_frozen_mencetak_tanggal_expired_dan_maks_100_stiker(): void
    {
        $this->nyalakan(['FRZ']);
        $order = $this->order([[$this->produk('FRZ'), 150, null]], [
            'tipe_transaksi' => 'frozen', 'tanggal_expired_frozen' => '2026-12-31',
        ]);

        $hasil = app(CupLabelService::class)->untukOrder($order);
        $this->assertCount(CupLabelService::MAKS_LABEL, $hasil['labels']);
        $this->assertSame('31/12/2026', $hasil['labels'][0]['exp']);
        $this->assertStringStartsWith('Frozen · ', $hasil['labels'][0]['info']);
    }

    public function test_tanpa_izin_atau_cabang_lain_ditolak(): void
    {
        $this->nyalakan(['DIM']);
        $order = $this->order([[$this->produk('DIM'), 1, null]]);

        $helper = $this->buatUser('helper', $this->cabang->id);
        $this->actingAs($helper)->getJson(route('label-cup.order', $order))->assertForbidden();

        $lain = Cabang::where('id', '!=', $this->cabang->id)->where('is_active', true)->firstOrFail();
        $kasirLain = $this->buatUser('kasir', $lain->id);
        $this->actingAs($kasirLain)->getJson(route('label-cup.order', $order))->assertForbidden();
    }

    public function test_pengaturan_disimpan_dari_cabang_edit(): void
    {
        $owner = $this->buatUser('owner', $this->cabang->id);

        $this->actingAs($owner)->get(route('cabang.edit', $this->cabang))->assertOk()
            ->assertSee('Label Produk (stiker)')
            ->assertSee('name="label_cup_aktif"', false)
            ->assertSee('panduanModal-label-produk', false);

        $payload = [
            'nama_cabang' => $this->cabang->nama_cabang, 'kode_cabang' => $this->cabang->kode_cabang,
            'tipe' => $this->cabang->tipe->value, 'is_active' => 1,
            'label_cup_form' => 1, 'label_cup_aktif' => 1, 'label_cup_kategori' => ['DIM', 'FRZ'],
            'label_cup_lebar_mm' => 50, 'label_cup_tinggi_mm' => 25,
        ];
        $this->actingAs($owner)->put(route('cabang.update', $this->cabang), $payload)->assertRedirect(route('cabang.index'));
        $c = $this->cabang->fresh();
        $this->assertTrue($c->label_cup_aktif);
        $this->assertSame(['DIM', 'FRZ'], $c->label_cup_kategori);
        $this->assertSame(50, $c->label_cup_lebar_mm);

        // Semua kategori dikosongkan → tidak ada kategori berlabel
        unset($payload['label_cup_kategori']);
        $this->actingAs($owner)->put(route('cabang.update', $this->cabang), $payload)->assertRedirect();
        $this->assertSame([], $this->cabang->fresh()->label_cup_kategori);
    }

    public function test_pos_menampilkan_catatan_item_saat_aktif_dan_catatan_tersimpan(): void
    {
        $this->nyalakan(['DIM']);
        $kasir = $this->buatUser('kasir', $this->cabang->id);

        $this->actingAs($kasir)->withSession(['active_cabang_id' => $this->cabang->id])
            ->get(route('penjualan.pos'))->assertOk()
            ->assertSee('window.POS_LABEL_AKTIF = true;', false)
            ->assertSee('items[${idx}][catatan]', false);

        $res = $this->actingAs($kasir)->withSession(['active_cabang_id' => $this->cabang->id])
            ->postJson(route('penjualan.simpan-bill'), [
                'tipe_transaksi' => 'takeaway',
                'items' => [['nama_item' => 'Item Bebas', 'qty' => 1, 'harga_satuan' => 1000, 'catatan' => 'tanpa cabe']],
            ])->assertOk()->json();

        $this->assertDatabaseHas('order_items', ['order_id' => $res['order_id'], 'catatan' => 'tanpa cabe']);
    }

    public function test_seeder_izin_additive_ikut_role_yang_bisa_transaksi_pos(): void
    {
        $orderCreate = Permission::where('name', 'order.create')->firstOrFail();
        DB::table('role_permissions')->insertOrIgnore(['role' => 'team_uji_label', 'permission_id' => $orderCreate->id]);

        (new LabelProdukSeeder())->run();
        (new LabelProdukSeeder())->run();

        $label = Permission::where('name', 'label_cup.print')->firstOrFail();
        $roles = DB::table('role_permissions')->where('permission_id', $label->id)->pluck('role');
        $this->assertContains('team_uji_label', $roles);
        $this->assertContains('kasir', $roles);
        $this->assertNotContains('helper', $roles);
        $this->assertSame($roles->count(), $roles->unique()->count());

        $this->assertTrue(Panduan::where('slug', 'label-produk')->exists());
        foreach (['aktif', 'kategori', 'ukuran'] as $k) {
            $this->assertTrue(Tooltip::where('key', "cabang.label_cup_$k")->exists());
        }
    }
}
