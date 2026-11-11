<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ResepBumbu;
use App\Models\ResepBumbuItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * CLAUDE.md 4.42 Tahap 2 — halaman "Ambil dari Cabang Lain" + layar
 * pemeriksaan komposisi + simpan (HTTP).
 */
class AmbilProdukCabangHttpTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $a;
    private Cabang $b;
    private string $k;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Cabang::create(['nama_cabang' => 'Outlet HA', 'kode_cabang' => 'HA' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true]);
        $this->b = Cabang::create(['nama_cabang' => 'Outlet HB', 'kode_cabang' => 'HB' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true]);
        $this->k = strtoupper(substr(uniqid(), -6));
    }

    protected function tearDown(): void
    {
        Cache::forget('role_permissions_manajer_cabang');
        parent::tearDown();
    }

    private function bahan(Cabang $c, string $kode, string $nama): Item
    {
        return Item::create(['cabang_id' => $c->id, 'kode_item' => $kode, 'nama_item' => $nama, 'tipe' => 'bahan_baku',
            'satuan' => 'pcs', 'harga_beli_terakhir' => 1000, 'is_active' => true, 'track_stok' => true]);
    }

    private function produk(Cabang $c, string $kode, string $nama, array $bahan = []): Item
    {
        $p = Item::create(['cabang_id' => $c->id, 'kode_item' => $kode, 'nama_item' => $nama, 'tipe' => 'produk_jual',
            'satuan' => 'porsi', 'harga_jual' => 15000, 'is_active' => true, 'track_stok' => false]);
        if ($bahan) {
            $r = ResepBumbu::create(['cabang_id' => $c->id, 'nama' => $nama, 'kode' => 'R-' . substr(md5($kode), 0, 12), 'item_id' => $p->id, 'is_active' => true]);
            foreach ($bahan as $i => $b) {
                ResepBumbuItem::create(['resep_bumbu_id' => $r->id, 'item_id' => $b->id, 'qty_per_unit' => 1, 'satuan' => 'pcs', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => $i]);
            }
        }
        return $p;
    }

    /** Manajer cabang B dgn izin tambah produk (default role tidak punya). */
    private function manajerB()
    {
        $perm = DB::table('permissions')->where('name', 'master.produk_jual.create')->value('id');
        DB::table('role_permissions')->insertOrIgnore(['role' => 'manajer_cabang', 'permission_id' => $perm]);
        Cache::forget('role_permissions_manajer_cabang');

        return $this->actingAs($this->buatUser('manajer_cabang', $this->b->id))->withSession(['active_cabang_id' => $this->b->id]);
    }

    public function test_tombol_di_daftar_produk_dan_tanpa_izin_ditolak(): void
    {
        $this->actingAs($this->buatUser('owner'))->get(route('master.produk-jual.index'))->assertOk()
            ->assertSee('Ambil dari Cabang Lain');
        $this->actingAs($this->buatUser('kasir', $this->b->id))->get(route('master.produk-jual.ambil.index'))->assertForbidden();
    }

    public function test_halaman_ambil_tampilkan_produk_sumber_dan_tandai_yang_sudah_ada(): void
    {
        $this->produk($this->a, "LP-{$this->k}", 'Lumpia HA');
        $this->produk($this->a, "HK-{$this->k}", 'Hakau HA');
        $this->produk($this->b, "LP-{$this->k}", 'Lumpia Milik HB');

        $this->manajerB()->get(route('master.produk-jual.ambil.index'))->assertOk()
            ->assertSee('Pilih cabang sumber')
            ->assertSee('Outlet HA')
            ->assertDontSee('<option value="' . $this->b->id . '"', false); // cabang sendiri bukan sumber

        $this->manajerB()->get(route('master.produk-jual.ambil.index', ['sumber' => $this->a->id]))->assertOk()
            ->assertSee('Lumpia HA')->assertSee('Hakau HA')
            ->assertSee('Sudah ada')->assertSee('Belum ada');
    }

    public function test_layar_pemeriksaan_tampilkan_status_pencocokan(): void
    {
        $sosisA = $this->bahan($this->a, "SOS-{$this->k}", 'Sosis');
        $this->bahan($this->b, "SOS-{$this->k}", 'Sosis');
        $kulitA = $this->bahan($this->a, "DM01-{$this->k}", 'Kulit Dimsum');
        $this->bahan($this->b, "DM03-{$this->k}", 'Kulit Dimsum');
        $sausA = $this->bahan($this->a, "SAU-{$this->k}", 'Saus Mentai');
        $this->bahan($this->b, "SAU-{$this->k}", 'Saus Sambal');
        $tusukA = $this->bahan($this->a, "TSK-{$this->k}", 'Tusuk');
        $p = $this->produk($this->a, "SB-{$this->k}", 'Sosis Bakar HA', [$sosisA, $kulitA, $sausA, $tusukA]);

        $this->manajerB()->get(route('master.produk-jual.ambil.periksa', ['sumber' => $this->a->id, 'produk' => [$p->id]]))->assertOk()
            ->assertSee('Pemeriksaan Komposisi')
            ->assertSee('Ke cabang: Outlet HB')
            ->assertSee('Cocok')
            ->assertSee('Kode beda, nama sama')
            ->assertSee('Kode sama, nama beda')
            ->assertSee('Belum ada')
            ->assertSee('wajib dipilih dulu')
            ->assertSee('name="bahan[' . $this->b->id . '][' . $sausA->id . ']"', false);

        // tanpa produk → kembali ke langkah 1
        $this->manajerB()->get(route('master.produk-jual.ambil.periksa', ['sumber' => $this->a->id]))
            ->assertRedirect(route('master.produk-jual.ambil.index'));
    }

    public function test_simpan_salin_produk_ke_cabang_aktif(): void
    {
        $sosisA = $this->bahan($this->a, "SOS-{$this->k}", 'Sosis');
        $sosisB = $this->bahan($this->b, "SOS-{$this->k}", 'Sosis');
        $p = $this->produk($this->a, "SB-{$this->k}", 'Sosis Bakar HA', [$sosisA]);

        $this->manajerB()->post(route('master.produk-jual.ambil.simpan'), [
            'sumber' => $this->a->id, 'produk' => [$p->id], 'tujuan' => [$this->b->id],
            'salin' => [$this->b->id => [$p->id => 1]],
            'bahan' => [$this->b->id => [$sosisA->id => $sosisB->id]],
        ])->assertRedirect(route('master.produk-jual.index'))->assertSessionHas('success');

        $baru = Item::with('resep.items')->where('cabang_id', $this->b->id)->where('kode_item', "SB-{$this->k}")->first();
        $this->assertNotNull($baru);
        $this->assertSame($sosisB->id, $baru->resep->items->first()->item_id);
        $this->assertSame(1, Item::where('cabang_id', $this->a->id)->where('kode_item', "SB-{$this->k}")->count()); // sumber utuh
    }

    public function test_simpan_tanpa_pilihan_kode_sama_ditolak_tidak_ada_yang_tersalin(): void
    {
        $sausA = $this->bahan($this->a, "SAU-{$this->k}", 'Saus Mentai');
        $this->bahan($this->b, "SAU-{$this->k}", 'Saus Sambal');
        $p = $this->produk($this->a, "MT-{$this->k}", 'Mentai HA', [$sausA]);

        $this->manajerB()->from(route('master.produk-jual.ambil.periksa', ['sumber' => $this->a->id, 'produk' => [$p->id]]))
            ->post(route('master.produk-jual.ambil.simpan'), [
                'sumber' => $this->a->id, 'produk' => [$p->id], 'tujuan' => [$this->b->id],
                'salin' => [$this->b->id => [$p->id => 1]], 'bahan' => [$this->b->id => [$sausA->id => '']],
            ])->assertSessionHasErrors('keputusan');
        $this->assertFalse(Item::where('cabang_id', $this->b->id)->where('kode_item', "MT-{$this->k}")->exists());
    }

    public function test_manajer_tidak_boleh_menyalin_ke_cabang_lain(): void
    {
        $c = Cabang::create(['nama_cabang' => 'Outlet HC', 'kode_cabang' => 'HC' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true]);
        $p = $this->produk($this->a, "XX-{$this->k}", 'Produk HA');

        $this->manajerB()->post(route('master.produk-jual.ambil.simpan'), [
            'sumber' => $this->a->id, 'produk' => [$p->id], 'tujuan' => [$c->id], 'salin' => [$c->id => [$p->id => 1]],
        ])->assertForbidden();
        $this->assertFalse(Item::where('cabang_id', $c->id)->exists());
    }

    public function test_panduan_tooltip_dan_tombol_cara_pakai(): void
    {
        $this->seed(\Database\Seeders\Perbaikan438PanduanSeeder::class);
        $konten = \App\Models\Panduan::where('slug', 'produk-jual')->value('konten');
        $this->assertSame(1, substr_count($konten, '<!-- AMBIL-PRODUK-442 -->'));
        $this->assertStringContainsString('Ambil dari Cabang Lain', $konten);
        $this->assertSame(4, \App\Models\Tooltip::where('key', 'like', 'ambil_produk.%')->count());

        // Panduan lengkap di halaman tersendiri (panduan Produk Jual cukup ringkasan)
        $lengkap = \App\Models\Panduan::where('slug', 'ambil-produk-cabang')->firstOrFail();
        $this->assertStringContainsString('Kode sama, nama beda', $lengkap->konten);
        $this->actingAs($this->buatUser('owner'))->get(route('panduan.show', 'ambil-produk-cabang'))->assertOk()
            ->assertSee('Ambil Produk dari Cabang Lain');

        $p = $this->produk($this->a, "TP-{$this->k}", 'Produk Tooltip', [$this->bahan($this->a, "TB-{$this->k}", 'Bahan Tooltip')]);
        $this->manajerB()->get(route('master.produk-jual.ambil.index', ['sumber' => $this->a->id]))->assertOk()
            ->assertSee('Ambil dari Cabang')->assertSee('panduan', false);
        $this->manajerB()->get(route('master.produk-jual.ambil.periksa', ['sumber' => $this->a->id, 'produk' => [$p->id]]))->assertOk()
            ->assertSee('Pencocokan Bahan');
        foreach (['ambil.blade.php', 'ambil-periksa.blade.php'] as $v) {
            $this->assertStringContainsString('<x-panduan-button slug="ambil-produk-cabang" />', file_get_contents(resource_path("views/master/produk-jual/{$v}")));
        }
    }

    public function test_owner_mode_semua_cabang_pilih_tujuan_dulu(): void
    {
        $owner = $this->buatUser('owner');
        $p = $this->produk($this->a, "OW-{$this->k}", 'Produk Owner');

        $this->actingAs($owner)->withSession(['active_cabang_id' => null])->get(route('master.produk-jual.ambil.index'))->assertOk()
            ->assertSee('Pilih cabang tujuan dulu');

        $this->actingAs($owner)->withSession(['active_cabang_id' => null])->post(route('master.produk-jual.ambil.simpan'), [
            'sumber' => $this->a->id, 'produk' => [$p->id], 'tujuan' => [$this->b->id], 'salin' => [$this->b->id => [$p->id => 1]],
        ])->assertRedirect(route('master.produk-jual.index'));
        $this->assertTrue(Item::where('cabang_id', $this->b->id)->where('kode_item', "OW-{$this->k}")->exists());
    }
}
