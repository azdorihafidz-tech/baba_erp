<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * CLAUDE.md 4.42 Tahap 3 — centang "Buat juga di cabang" di form Tambah
 * Produk Jual → simpan di cabang pemilik → layar pemeriksaan untuk cabang
 * yang dicentang → salin.
 */
class BuatJugaDiCabangTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $a;
    private Cabang $b;
    private string $k;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Cabang::create(['nama_cabang' => 'Outlet BJ1', 'kode_cabang' => 'BJ' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true]);
        $this->b = Cabang::create(['nama_cabang' => 'Outlet BJ2', 'kode_cabang' => 'BK' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true]);
        $this->k = strtoupper(substr(uniqid(), -6));
    }

    private function owner()
    {
        return $this->actingAs($this->buatUser('owner'))->withSession(['active_cabang_id' => $this->a->id]);
    }

    private function payload(array $extra = []): array
    {
        $bahan = Item::create(['cabang_id' => $this->a->id, 'kode_item' => "SS-{$this->k}", 'nama_item' => 'Sosis BJ', 'tipe' => 'bahan_baku',
            'satuan' => 'pcs', 'harga_beli_terakhir' => 2000, 'is_active' => true, 'track_stok' => true]);

        return array_merge([
            'kode_item' => "BJ-{$this->k}", 'nama_item' => 'Sosis Bakar BJ', 'tipe' => 'produk_jual', 'satuan' => 'porsi', 'harga_jual' => '12.000', 'is_active' => 1,
            'resep' => [['item_id' => $bahan->id, 'qty_per_unit' => 2, 'satuan' => 'pcs']],
        ], $extra);
    }

    public function test_form_tambah_tampilkan_cabang_lain_bukan_cabang_pemilik(): void
    {
        $this->owner()->get(route('master.produk-jual.create'))->assertOk()
            ->assertSee('Buat juga di cabang')
            ->assertSee('name="salin_ke_cabang[]" value="' . $this->b->id . '"', false)
            ->assertDontSee('name="salin_ke_cabang[]" value="' . $this->a->id . '"', false);
    }

    public function test_form_edit_tidak_tampilkan_pilihan_cabang_lain(): void
    {
        $p = Item::create(['cabang_id' => $this->a->id, 'kode_item' => "ED-{$this->k}", 'nama_item' => 'Edit BJ', 'tipe' => 'produk_jual', 'satuan' => 'porsi', 'harga_jual' => 1000, 'is_active' => true]);
        $this->owner()->get(route('master.produk-jual.edit', $p))->assertOk()->assertDontSee('id="cardSalinCabangLain"', false);
    }

    public function test_simpan_dengan_centang_lanjut_ke_pemeriksaan_lalu_salin(): void
    {
        $res = $this->owner()->post(route('master.produk-jual.store'), $this->payload(['salin_ke_cabang' => [$this->b->id]]));
        $produk = Item::where('cabang_id', $this->a->id)->where('kode_item', "BJ-{$this->k}")->firstOrFail();
        $res->assertRedirect(route('master.produk-jual.ambil.periksa', ['sumber' => $this->a->id, 'produk' => [$produk->id], 'tujuan' => [$this->b->id]]));

        $this->owner()->get(route('master.produk-jual.ambil.periksa', ['sumber' => $this->a->id, 'produk' => [$produk->id], 'tujuan' => [$this->b->id]]))
            ->assertOk()->assertSee('Ke cabang: Outlet BJ2')->assertSee('Sosis BJ')->assertSee('Belum ada');

        $this->owner()->post(route('master.produk-jual.ambil.simpan'), [
            'sumber' => $this->a->id, 'produk' => [$produk->id], 'tujuan' => [$this->b->id],
            'salin' => [$this->b->id => [$produk->id => 1]],
        ])->assertRedirect(route('master.produk-jual.index'));

        $salinan = Item::with('resep.items.item')->where('cabang_id', $this->b->id)->where('kode_item', "BJ-{$this->k}")->first();
        $this->assertNotNull($salinan);
        $this->assertEquals(12000, $salinan->harga_jual);
        $bahan = $salinan->resep->items->first()->item;
        $this->assertSame($this->b->id, $bahan->cabang_id);
        $this->assertSame("SS-{$this->k}", $bahan->kode_item);
        $this->assertEquals(2, $salinan->resep->items->first()->qty_per_unit);
    }

    public function test_simpan_tanpa_centang_tetap_ke_daftar(): void
    {
        $this->owner()->post(route('master.produk-jual.store'), $this->payload())->assertRedirect(route('master.produk-jual.index'));
        $this->assertFalse(Item::where('cabang_id', $this->b->id)->where('kode_item', "BJ-{$this->k}")->exists());
    }

    public function test_cabang_yang_tidak_boleh_diakses_diabaikan(): void
    {
        $manajer = $this->buatUser('admin_pusat');
        $c = Cabang::create(['nama_cabang' => 'Outlet Nonaktif BJ', 'kode_cabang' => 'BN' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => false]);

        $this->actingAs($manajer)->withSession(['active_cabang_id' => $this->a->id])
            ->post(route('master.produk-jual.store'), $this->payload(['salin_ke_cabang' => [$c->id, $this->a->id]]))
            ->assertRedirect(route('master.produk-jual.index'));
    }
}
