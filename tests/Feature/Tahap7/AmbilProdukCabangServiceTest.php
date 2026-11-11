<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemAttribute;
use App\Models\ItemAttributeValue;
use App\Models\ItemVariant;
use App\Models\ResepBumbu;
use App\Models\ResepBumbuItem;
use App\Models\Stock;
use App\Services\AmbilProdukCabangService;
use App\Services\KetersediaanProdukService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * CLAUDE.md 4.42 Tahap 1 — mesin "Ambil Produk dari Cabang Lain":
 * pencocokan bahan (kode + nama) dan penyalinan produk ke cabang tujuan.
 */
class AmbilProdukCabangServiceTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $a;
    private Cabang $b;
    private AmbilProdukCabangService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Cabang::create(['nama_cabang' => 'Outlet AP1', 'kode_cabang' => 'AP' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true]);
        $this->b = Cabang::create(['nama_cabang' => 'Outlet AP2', 'kode_cabang' => 'AQ' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true]);
        $this->svc = app(AmbilProdukCabangService::class);
        $this->actingAs($this->buatUser('owner'));
    }

    private function bahan(Cabang $c, string $kode, string $nama, array $extra = []): Item
    {
        return Item::create(array_merge(['cabang_id' => $c->id, 'kode_item' => $kode, 'nama_item' => $nama, 'tipe' => 'bahan_baku',
            'satuan' => 'pcs', 'harga_beli_terakhir' => 1000, 'is_active' => true, 'track_stok' => true], $extra));
    }

    private function produk(Cabang $c, string $kode, string $nama, array $resep = [], array $extra = []): Item
    {
        $p = Item::create(array_merge(['cabang_id' => $c->id, 'kode_item' => $kode, 'nama_item' => $nama, 'tipe' => 'produk_jual',
            'satuan' => 'porsi', 'harga_jual' => 15000, 'is_active' => true, 'track_stok' => false], $extra));
        if ($resep) {
            $r = ResepBumbu::create(['cabang_id' => $c->id, 'nama' => $nama, 'kode' => 'R-' . substr(md5($kode), 0, 12), 'item_id' => $p->id, 'is_active' => true]);
            foreach ($resep as $i => [$ref, $qty]) {
                ResepBumbuItem::create([
                    'resep_bumbu_id' => $r->id,
                    'item_id' => $ref instanceof Item ? $ref->id : null,
                    'resep_bumbu_ref_id' => $ref instanceof ResepBumbu ? $ref->id : null,
                    'qty_per_unit' => $qty, 'satuan' => $ref instanceof Item ? $ref->satuan : 'porsi',
                    'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => $i,
                ]);
            }
        }
        return $p;
    }

    private function salinan(Item $sumber): ?Item
    {
        return Item::with('resep.items')->where('cabang_id', $this->b->id)->where('kode_item', $sumber->kode_item)->first();
    }

    public function test_bahan_kode_dan_nama_sama_langsung_dipakai_stok_cabang_tujuan(): void
    {
        $k = uniqid();
        $sosisA = $this->bahan($this->a, "SOS-$k", 'Sosis');
        $sosisB = $this->bahan($this->b, "SOS-$k", 'sosis ');
        $p = $this->produk($this->a, "SB-$k", 'Sosis Bakar', [[$sosisA, 1]]);

        $r = $this->svc->rencana($this->a->id, [$p->id], $this->b->id);
        $this->assertSame('cocok', $r['bahan'][$sosisA->id]['status']);
        $this->assertSame(0, $r['perlu_pilih']);

        $hasil = $this->svc->jalankan($this->a->id, [$p->id], $this->b->id, null, [], []);
        $this->assertSame(['Sosis Bakar'], $hasil['produk']);
        $baru = $this->salinan($p);
        $this->assertSame($sosisB->id, $baru->resep->items->first()->item_id);
        $this->assertSame($this->b->id, $baru->resep->cabang_id);

        // Stok tersedia di POS cabang tujuan dihitung dari stok bahan cabang tujuan
        Stock::create(['item_id' => $sosisB->id, 'lokasi_id' => $this->b->id, 'qty' => 7, 'qty_minimum' => 0]);
        Stock::create(['item_id' => $sosisA->id, 'lokasi_id' => $this->a->id, 'qty' => 99, 'qty_minimum' => 0]);
        $info = app(KetersediaanProdukService::class)->untuk(collect([$baru->fresh()]), $this->b->id);
        $this->assertEquals(7, $info[$baru->id]['jumlah']);
    }

    public function test_kode_beda_nama_sama_bawaan_pakai_bahan_bernama_sama(): void
    {
        $k = uniqid();
        $kulitA = $this->bahan($this->a, "DM01-$k", 'Kulit Dimsum');
        $kulitB = $this->bahan($this->b, "DM03-$k", 'Kulit Dimsum');
        $p = $this->produk($this->a, "DS-$k", 'Dimsum Ayam', [[$kulitA, 2]]);

        $r = $this->svc->rencana($this->a->id, [$p->id], $this->b->id);
        $this->assertSame('nama_sama', $r['bahan'][$kulitA->id]['status']);
        $this->assertSame($kulitB->id, $r['bahan'][$kulitA->id]['default']);

        $this->svc->jalankan($this->a->id, [$p->id], $this->b->id, null, [], []);
        $this->assertSame($kulitB->id, $this->salinan($p)->resep->items->first()->item_id);
    }

    public function test_kode_sama_nama_beda_wajib_dipilih(): void
    {
        $k = uniqid();
        $sausA = $this->bahan($this->a, "SAU-$k", 'Saus Mentai');
        $sausB = $this->bahan($this->b, "SAU-$k", 'Saus Sambal');
        $p = $this->produk($this->a, "MT-$k", 'Dimsum Mentai', [[$sausA, 10]]);

        $r = $this->svc->rencana($this->a->id, [$p->id], $this->b->id);
        $this->assertSame('kode_sama', $r['bahan'][$sausA->id]['status']);
        $this->assertNull($r['bahan'][$sausA->id]['default']);
        $this->assertSame(1, $r['perlu_pilih']);

        try {
            $this->svc->jalankan($this->a->id, [$p->id], $this->b->id, null, [], []);
            $this->fail('Harus menolak tanpa pilihan');
        } catch (ValidationException) {
        }
        $this->assertNull($this->salinan($p));

        // Pilih "buat baru" → bahan baru dgn kode berakhiran -2, Saus Sambal tetap utuh
        $hasil = $this->svc->jalankan($this->a->id, [$p->id], $this->b->id, null, [$sausA->id => 'baru'], []);
        $bahanBaru = Item::find($this->salinan($p)->resep->items->first()->item_id);
        $this->assertSame("SAU-$k-2", $bahanBaru->kode_item);
        $this->assertSame('Saus Mentai', $bahanBaru->nama_item);
        $this->assertSame($this->b->id, $bahanBaru->cabang_id);
        $this->assertCount(1, $hasil['bahan_baru']);
        $this->assertSame('Saus Sambal', $sausB->fresh()->nama_item);
    }

    public function test_bahan_tidak_ada_dibuat_baru_stok_nol(): void
    {
        $k = uniqid();
        $tusuk = $this->bahan($this->a, "TSK-$k", 'Tusuk Sate', ['harga_beli_terakhir' => 50, 'unit_beli' => 'pack', 'isi_per_unit_beli' => 100]);
        Stock::create(['item_id' => $tusuk->id, 'lokasi_id' => $this->a->id, 'qty' => 500, 'qty_minimum' => 0]);
        $p = $this->produk($this->a, "ST-$k", 'Sate', [[$tusuk, 1]]);

        $this->assertSame('baru', $this->svc->rencana($this->a->id, [$p->id], $this->b->id)['bahan'][$tusuk->id]['status']);
        $this->svc->jalankan($this->a->id, [$p->id], $this->b->id, null, [], []);

        $baru = Item::where('cabang_id', $this->b->id)->where('kode_item', "TSK-$k")->first();
        $this->assertNotNull($baru);
        $this->assertEquals(50, $baru->harga_beli_terakhir);
        $this->assertSame('pack', $baru->unit_beli);
        $this->assertFalse(Stock::where('item_id', $baru->id)->where('qty', '>', 0)->exists());
        $this->assertSame($baru->id, $this->salinan($p)->resep->items->first()->item_id);
    }

    public function test_produk_kode_sudah_ada_dilewati_nama_sama_bawaan_tidak_disalin(): void
    {
        $k = uniqid();
        $p1 = $this->produk($this->a, "P1-$k", 'Lumpia');
        $p2 = $this->produk($this->a, "P2-$k", 'Hakau');
        $this->produk($this->b, "P1-$k", 'Lumpia Lain', [], ['harga_jual' => 999]);
        $this->produk($this->b, "XX-$k", 'hakau');

        $r = $this->svc->rencana($this->a->id, [$p1->id, $p2->id], $this->b->id);
        $this->assertSame('kode_ada', $r['produk'][$p1->id]['status']);
        $this->assertSame('nama_ada', $r['produk'][$p2->id]['status']);

        $hasil = $this->svc->jalankan($this->a->id, [$p1->id, $p2->id], $this->b->id, null, [], []);
        $this->assertSame([], $hasil['produk']);
        $this->assertEquals(999, $this->salinan($p1)->harga_jual); // tidak tertimpa

        // Dicentang manual: nama sama tetap boleh disalin, kode ada tetap dilewati
        $hasil = $this->svc->jalankan($this->a->id, [$p1->id, $p2->id], $this->b->id, [$p1->id => 1, $p2->id => 1], [], []);
        $this->assertSame(['Hakau'], $hasil['produk']);
    }

    public function test_bumbu_pusat_disalin_atau_dipakai_ulang(): void
    {
        $k = uniqid();
        $cabe = $this->bahan($this->a, "CB-$k", 'Cabai');
        $bumbuA = ResepBumbu::create(['cabang_id' => $this->a->id, 'nama' => 'Saus Rahasia', 'kode' => "BMB-$k", 'item_id' => null, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $bumbuA->id, 'item_id' => $cabe->id, 'qty_per_unit' => 3, 'satuan' => 'pcs', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 0]);
        $p = $this->produk($this->a, "PB-$k", 'Dimsum Saus', [[$bumbuA, 1]]);

        $r = $this->svc->rencana($this->a->id, [$p->id], $this->b->id);
        $this->assertSame('baru', $r['bumbu'][$bumbuA->id]['status']);
        $this->assertArrayHasKey($cabe->id, $r['bahan']);

        $hasil = $this->svc->jalankan($this->a->id, [$p->id], $this->b->id, null, [], []);
        $this->assertSame(['Saus Rahasia'], $hasil['bumbu_baru']);
        $bumbuB = ResepBumbu::with('items.item')->where('cabang_id', $this->b->id)->where('kode', "BMB-$k")->first();
        $this->assertNull($bumbuB->item_id);
        $this->assertSame($this->b->id, $bumbuB->items->first()->item->cabang_id);
        $this->assertSame($bumbuB->id, $this->salinan($p)->resep->items->first()->resep_bumbu_ref_id);

        // Produk kedua yg memakai bumbu sama → pakai bumbu yg sudah ada di cabang tujuan
        $p2 = $this->produk($this->a, "PC-$k", 'Gyoza Saus', [[$bumbuA, 2]]);
        $this->assertSame('cocok', $this->svc->rencana($this->a->id, [$p2->id], $this->b->id)['bumbu'][$bumbuA->id]['status']);
        $hasil = $this->svc->jalankan($this->a->id, [$p2->id], $this->b->id, null, [], []);
        $this->assertSame([], $hasil['bumbu_baru']);
        $this->assertSame($bumbuB->id, $this->salinan($p2)->resep->items->first()->resep_bumbu_ref_id);
    }

    public function test_varian_dan_foto_ikut_disalin_foto_file_sendiri(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('produk/asli.jpg', 'gambar');
        $k = uniqid();
        $p = $this->produk($this->a, "PV-$k", 'Dimsum Varian', [], ['punya_varian' => true, 'foto' => 'produk/asli.jpg']);
        $attr = ItemAttribute::create(['item_id' => $p->id, 'nama' => 'Ukuran', 'urutan' => 0]);
        $s = ItemAttributeValue::create(['item_attribute_id' => $attr->id, 'nilai' => 'S', 'urutan' => 0]);
        $l = ItemAttributeValue::create(['item_attribute_id' => $attr->id, 'nilai' => 'L', 'urutan' => 1]);
        ItemVariant::create(['item_id' => $p->id, 'harga_override' => 12000, 'is_active' => true, 'urutan' => 0])->attributeValues()->sync([$s->id]);
        ItemVariant::create(['item_id' => $p->id, 'harga_override' => 20000, 'is_active' => true, 'urutan' => 1])->attributeValues()->sync([$l->id]);

        $this->svc->jalankan($this->a->id, [$p->id], $this->b->id, null, [], []);
        $baru = $this->salinan($p);
        $this->assertTrue($baru->punya_varian);
        $varian = ItemVariant::with('attributeValues')->where('item_id', $baru->id)->orderBy('urutan')->get();
        $this->assertCount(2, $varian);
        $this->assertSame('L', $varian[1]->attributeValues->first()->nilai);
        $this->assertEquals(20000, $varian[1]->harga_override);
        $this->assertNotSame('produk/asli.jpg', $baru->foto);
        Storage::disk('public')->assertExists($baru->foto);
    }

    public function test_pilihan_bahan_milik_cabang_lain_ditolak(): void
    {
        $k = uniqid();
        $c = Cabang::create(['nama_cabang' => 'Outlet AP3', 'kode_cabang' => 'AR' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true]);
        $bahanA = $this->bahan($this->a, "BH-$k", 'Garam');
        $bahanC = $this->bahan($c, "BH2-$k", 'Garam C');
        $p = $this->produk($this->a, "PG-$k", 'Kentang', [[$bahanA, 1]]);

        $this->expectException(ValidationException::class);
        $this->svc->jalankan($this->a->id, [$p->id], $this->b->id, null, [$bahanA->id => $bahanC->id], []);
    }

    public function test_bahan_data_lama_tanpa_cabang_dipakai_apa_adanya(): void
    {
        $k = uniqid();
        $lama = Item::create(['cabang_id' => null, 'kode_item' => "LM-$k", 'nama_item' => 'Minyak Lama', 'tipe' => 'bahan_baku', 'satuan' => 'liter', 'is_active' => true, 'track_stok' => true]);
        $p = $this->produk($this->a, "PL-$k", 'Goreng', [[$lama, 1]]);

        $this->assertSame('umum', $this->svc->rencana($this->a->id, [$p->id], $this->b->id)['bahan'][$lama->id]['status']);
        $this->svc->jalankan($this->a->id, [$p->id], $this->b->id, null, [], []);
        $this->assertSame($lama->id, $this->salinan($p)->resep->items->first()->item_id);
    }
}
