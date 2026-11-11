<?php

namespace Tests\Feature\Tahap7;

use App\Enums\StatusOrder;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use App\Models\ResepBumbu;
use App\Models\ResepBumbuItem;
use App\Models\Stock;
use App\Services\PenjualanService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap 7 D'mentai — Fitur "Import dari Bumbu Pusat": link (bukan copy) 1
 * baris resep produk ke sebuah Master Bumbu Pusat (ResepBumbu tanpa
 * item_id). HPP & potong stok dihitung LIVE (expand ke bahan mentah di
 * dalam bumbu), tidak ada snapshot/cache — auto-update kalau bumbu diedit.
 */
class ImportBumbuPusatTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function bahanBaku(string $nama, string $satuan = 'gram'): Item
    {
        return Item::create([
            'kode_item' => 'BB-TEST-' . strtoupper(uniqid()), 'nama_item' => $nama, 'tipe' => 'bahan_baku',
            'satuan' => 'kg', 'harga_jual' => 0, 'harga_beli_terakhir' => 100000, 'is_active' => true,
        ]);
    }

    private function buatMasterBumbu(string $nama, array $bahanList): ResepBumbu
    {
        $admin = $this->buatUser('owner');
        $bumbu = ResepBumbu::create([
            'nama' => $nama, 'kode' => 'BMB-' . strtoupper(uniqid()), 'item_id' => null,
            'is_active' => true, 'dibuat_oleh' => $admin->id,
        ]);
        foreach ($bahanList as $i => $b) {
            ResepBumbuItem::create([
                'resep_bumbu_id' => $bumbu->id, 'item_id' => $b['item']->id,
                'qty_per_unit' => $b['qty'], 'satuan' => $b['satuan'] ?? 'gram',
                'is_wajib' => true, 'mode_harga' => $b['mode_harga'] ?? 'pakai_master', 'urutan' => $i,
            ]);
        }
        return $bumbu;
    }

    // ===== 1. Struktur data baru (migration) =====

    public function test_kolom_resep_bumbu_ref_id_ada_dan_nullable(): void
    {
        $col = collect(\Illuminate\Support\Facades\DB::select('SHOW COLUMNS FROM resep_bumbu_items WHERE Field = "resep_bumbu_ref_id"'))->first();
        $this->assertNotNull($col);
        $this->assertEquals('YES', $col->Null);

        $itemIdCol = collect(\Illuminate\Support\Facades\DB::select('SHOW COLUMNS FROM resep_bumbu_items WHERE Field = "item_id"'))->first();
        $this->assertEquals('YES', $itemIdCol->Null, 'item_id harus nullable sekarang (baris linked tidak punya item_id).');
    }

    // ===== 2. Modal picker: render, search, list =====

    public function test_picker_list_bumbu_pusat_hanya_tampilkan_yang_aktif_dan_murni(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $bahan = $this->bahanBaku('Kecap Manis');
        $bumbuAktif = $this->buatMasterBumbu('Bumbu Kecap Aktif', [['item' => $bahan, 'qty' => 10]]);
        $bumbuNonaktif = $this->buatMasterBumbu('Bumbu Nonaktif', [['item' => $bahan, 'qty' => 5]]);
        $bumbuNonaktif->update(['is_active' => false]);

        $item = Item::where('tipe', 'produk_jual')->first();
        $resepPunyaItemId = ResepBumbu::create(['nama' => 'Resep Produk Lain', 'kode' => 'RPL-' . uniqid(), 'item_id' => $item->id, 'is_active' => true]);

        $response = $this->actingAs($admin)->getJson('/master/produk-jual/bumbu-pusat/list');

        $response->assertOk();
        $namaList = collect($response->json('data'))->pluck('nama');
        $this->assertContains('Bumbu Kecap Aktif', $namaList);
        $this->assertNotContains('Bumbu Nonaktif', $namaList, 'Bumbu nonaktif tidak boleh muncul di picker.');
        $this->assertNotContains('Resep Produk Lain', $namaList, 'Resep yang punya item_id (bukan master bumbu murni) tidak boleh muncul di picker.');
    }

    public function test_picker_search_by_nama(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $bahan = $this->bahanBaku('Gula');
        $this->buatMasterBumbu('Bumbu Kecap Manis', [['item' => $bahan, 'qty' => 5]]);
        $this->buatMasterBumbu('Bumbu Pedas Original', [['item' => $bahan, 'qty' => 3]]);

        $response = $this->actingAs($admin)->getJson('/master/produk-jual/bumbu-pusat/list?search=Kecap');

        $response->assertOk();
        $namaList = collect($response->json('data'))->pluck('nama');
        $this->assertContains('Bumbu Kecap Manis', $namaList);
        $this->assertNotContains('Bumbu Pedas Original', $namaList);
    }

    public function test_picker_permission_ditolak_tanpa_produk_jual_edit(): void
    {
        $kasir = $this->buatUser('kasir');

        $response = $this->actingAs($kasir)->getJson('/master/produk-jual/bumbu-pusat/list');

        $response->assertForbidden();
    }

    // ===== 3. Import behavior: link tersimpan, bukan copy =====

    public function test_import_bumbu_tersimpan_sebagai_link_bukan_copy(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $bahan = $this->bahanBaku('Bawang Putih');
        $bumbu = $this->buatMasterBumbu('Bumbu Test Link', [['item' => $bahan, 'qty' => 20]]);

        $response = $this->actingAs($admin)->post('/master/produk-jual', [
            'kode_item' => 'PJ-BUMBU-001', 'nama_item' => 'Produk Import Bumbu', 'tipe' => 'produk_jual',
            'satuan' => 'porsi', 'harga_jual' => '15000', 'is_active' => '1',
            'resep' => [
                ['resep_bumbu_ref_id' => $bumbu->id, 'qty_per_unit' => '2', 'is_wajib' => '1'],
            ],
        ]);

        $response->assertRedirect(route('master.produk-jual.index'));
        $item = Item::where('kode_item', 'PJ-BUMBU-001')->firstOrFail();
        $resepItem = $item->resep->items->first();
        $this->assertNotNull($resepItem);
        $this->assertTrue($resepItem->isLinked());
        $this->assertEquals($bumbu->id, $resepItem->resep_bumbu_ref_id);
        $this->assertNull($resepItem->item_id, 'Baris linked TIDAK boleh punya item_id.');
        $this->assertEquals(2, (float) $resepItem->qty_per_unit);

        // Bukti "link bukan copy": bumbu masternya TIDAK bertambah baris/berubah.
        $bumbu->refresh();
        $this->assertEquals(1, $bumbu->items()->count());
    }

    public function test_resep_mix_bahan_manual_dan_bumbu_linked(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $bahanManual = $this->bahanBaku('Kulit Dimsum');
        $bahanBumbu = $this->bahanBaku('Kecap Asin');
        $bumbu = $this->buatMasterBumbu('Bumbu Campuran', [['item' => $bahanBumbu, 'qty' => 10]]);

        $response = $this->actingAs($admin)->post('/master/produk-jual', [
            'kode_item' => 'PJ-MIX-001', 'nama_item' => 'Produk Mix', 'tipe' => 'produk_jual',
            'satuan' => 'porsi', 'harga_jual' => '15000', 'is_active' => '1',
            'resep' => [
                ['item_id' => $bahanManual->id, 'qty_per_unit' => '3', 'satuan' => 'pcs', 'is_wajib' => '1', 'mode_harga' => 'gratis'],
                ['resep_bumbu_ref_id' => $bumbu->id, 'qty_per_unit' => '1', 'is_wajib' => '1'],
            ],
        ]);

        $response->assertRedirect();
        $item = Item::where('kode_item', 'PJ-MIX-001')->firstOrFail();
        $this->assertEquals(2, $item->resep->items()->count());
        $this->assertEquals(1, $item->resep->items()->whereNotNull('item_id')->count());
        $this->assertEquals(1, $item->resep->items()->whereNotNull('resep_bumbu_ref_id')->count());
    }

    // ===== 4. HPP calculation (kalkulator) =====

    public function test_kalkulator_resep_hpp_akurat_untuk_baris_linked(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $bahanBumbu = $this->bahanBaku('Merica'); // harga_beli_terakhir = 100000/kg
        $bumbu = $this->buatMasterBumbu('Bumbu Merica', [['item' => $bahanBumbu, 'qty' => 100, 'satuan' => 'gram', 'mode_harga' => 'pakai_master']]);
        // 100 gram = 0.1 kg -> HPP per porsi bumbu = 0.1 * 100000 = 10000

        $item = Item::create(['kode_item' => 'PJ-KALK-001', 'nama_item' => 'Produk Kalkulator', 'tipe' => 'produk_jual', 'satuan' => 'porsi', 'harga_jual' => 20000, 'is_active' => true]);
        $resep = ResepBumbu::create(['nama' => $item->nama_item, 'kode' => 'R-' . uniqid(), 'item_id' => $item->id, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $resep->id, 'resep_bumbu_ref_id' => $bumbu->id, 'qty_per_unit' => 2, 'satuan' => 'porsi', 'is_wajib' => true, 'mode_harga' => 'gratis', 'urutan' => 0]);
        // 2 porsi bumbu per unit produk -> HPP = 2 * 10000 = 20000 utk 1 pcs produksi

        $response = $this->actingAs($admin)->getJson("/master/produk-jual/{$item->id}/kalkulator-resep?jumlah=1");

        $response->assertOk();
        $this->assertEqualsWithDelta(20000.0, (float) $response->json('total_hpp'), 0.01);
        $response->assertJsonFragment(['linked' => true]);
    }

    public function test_auto_update_hpp_saat_bumbu_diedit(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $bahanBumbu = $this->bahanBaku('Gula Pasir');
        $bumbu = $this->buatMasterBumbu('Bumbu Auto Update', [['item' => $bahanBumbu, 'qty' => 100, 'satuan' => 'gram', 'mode_harga' => 'pakai_master']]);

        $item = Item::create(['kode_item' => 'PJ-AUTO-001', 'nama_item' => 'Produk Auto Update', 'tipe' => 'produk_jual', 'satuan' => 'porsi', 'harga_jual' => 20000, 'is_active' => true]);
        $resep = ResepBumbu::create(['nama' => $item->nama_item, 'kode' => 'R-' . uniqid(), 'item_id' => $item->id, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $resep->id, 'resep_bumbu_ref_id' => $bumbu->id, 'qty_per_unit' => 1, 'satuan' => 'porsi', 'is_wajib' => true, 'mode_harga' => 'gratis', 'urutan' => 0]);

        $hppSebelum = $this->actingAs($admin)->getJson("/master/produk-jual/{$item->id}/kalkulator-resep?jumlah=1")->json('total_hpp');
        $this->assertEquals(10000.0, $hppSebelum); // 100gram=0.1kg * 100000

        // Edit komposisi bumbu di Master Bumbu Pusat (tanpa sentuh produk sama sekali)
        $bumbu->items()->first()->update(['qty_per_unit' => 200]); // 200gram = 0.2kg -> 20000

        $hppSesudah = $this->actingAs($admin)->getJson("/master/produk-jual/{$item->id}/kalkulator-resep?jumlah=1")->json('total_hpp');
        $this->assertEquals(20000.0, $hppSesudah, 'HPP produk harus otomatis berubah tanpa edit apapun di form produk.');
    }

    // ===== 5. Delete link dari resep produk =====

    public function test_hapus_baris_bumbu_dari_resep_produk_tidak_hapus_master(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $bahan = $this->bahanBaku('Saus Tiram');
        $bumbu = $this->buatMasterBumbu('Bumbu Akan Diunlink', [['item' => $bahan, 'qty' => 5]]);

        $item = Item::create(['kode_item' => 'PJ-UNLINK-001', 'nama_item' => 'Produk Unlink', 'tipe' => 'produk_jual', 'satuan' => 'porsi', 'harga_jual' => 15000, 'is_active' => true]);
        $resep = ResepBumbu::create(['nama' => $item->nama_item, 'kode' => 'R-' . uniqid(), 'item_id' => $item->id, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $resep->id, 'resep_bumbu_ref_id' => $bumbu->id, 'qty_per_unit' => 1, 'satuan' => 'porsi', 'is_wajib' => true, 'mode_harga' => 'gratis', 'urutan' => 0]);

        // Update produk TANPA baris resep sama sekali (hapus link)
        $response = $this->actingAs($admin)->put("/master/produk-jual/{$item->id}", [
            'kode_item' => 'PJ-UNLINK-001', 'nama_item' => 'Produk Unlink', 'tipe' => 'produk_jual',
            'satuan' => 'porsi', 'harga_jual' => '15000', 'is_active' => '1',
        ]);

        $response->assertRedirect();
        $item->refresh();
        $this->assertNull($item->resep, 'Resep produk (yang cuma isi 1 link, lalu dihapus) ikut terhapus krn kosong -- sesuai perilaku syncResep() existing.');
        $bumbu->refresh();
        $this->assertNotNull($bumbu);
        $this->assertTrue($bumbu->is_active);
        $this->assertEquals(1, $bumbu->items()->count(), 'Master Bumbu Pusat & isinya TIDAK BOLEH terhapus.');
    }

    // ===== 6. Edge cases =====

    public function test_bumbu_dinonaktifkan_setelah_linked_produk_tetap_normal(): void
    {
        $cabang = $this->cabangPertama();
        $admin = $this->buatUser('admin_pusat', $cabang->id);
        $bahanBumbu = $this->bahanBaku('Minyak Wijen');
        Stock::updateOrCreate(['item_id' => $bahanBumbu->id, 'lokasi_id' => $cabang->id], ['qty' => 1000, 'qty_minimum' => 0]);
        $bumbu = $this->buatMasterBumbu('Bumbu Akan Nonaktif', [['item' => $bahanBumbu, 'qty' => 10, 'satuan' => 'gram']]);

        $item = Item::create(['kode_item' => 'PJ-NONAKTIF-001', 'nama_item' => 'Produk Bumbu Nonaktif', 'tipe' => 'produk_jual', 'satuan' => 'porsi', 'harga_jual' => 15000, 'is_active' => true]);
        Kas::firstOrCreate(['cabang_id' => $cabang->id, 'default_untuk' => 'tunai'], ['nama_kas' => 'Kas Test', 'tipe_kas' => 'tunai', 'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true]);
        $resep = ResepBumbu::create(['nama' => $item->nama_item, 'kode' => 'R-' . uniqid(), 'item_id' => $item->id, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $resep->id, 'resep_bumbu_ref_id' => $bumbu->id, 'qty_per_unit' => 1, 'satuan' => 'porsi', 'is_wajib' => true, 'mode_harga' => 'gratis', 'urutan' => 0]);

        $bumbu->update(['is_active' => false]);

        // Produk yang SUDAH linked tetap harus bisa checkout normal di POS.
        $kas = Kas::where('cabang_id', $cabang->id)->first();
        $order = app(PenjualanService::class)->buatOrder([
            'kasir_id' => $admin->id, 'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway',
            'nama_pelanggan' => 'Test', 'items' => [['item_id' => $item->id, 'qty' => 1, 'harga_satuan' => 15000, 'satuan' => 'porsi', 'nama_item' => $item->nama_item]],
            'payments' => [['metode' => 'tunai', 'jumlah' => 15000, 'kas_id' => $kas->id]],
            'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => 15000,
        ], $cabang->id);

        $this->assertNotNull($order);
        $stokSesudah = Stock::where('item_id', $bahanBumbu->id)->where('lokasi_id', $cabang->id)->value('qty');
        $this->assertEquals(999.99, (float) $stokSesudah, '10 gram = 0.01kg terpotong dari 1000.');
    }

    public function test_cyclic_reference_tidak_bisa_link_ke_resep_produk_lain(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $itemLain = Item::where('tipe', 'produk_jual')->first();
        $bahan = $this->bahanBaku('Bahan X');
        $resepProdukLain = ResepBumbu::create(['nama' => 'Resep Produk Lain', 'kode' => 'RPL-' . uniqid(), 'item_id' => $itemLain->id, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $resepProdukLain->id, 'item_id' => $bahan->id, 'qty_per_unit' => 5, 'satuan' => 'gram', 'is_wajib' => true, 'mode_harga' => 'gratis', 'urutan' => 0]);

        // Coba import "resep produk lain" (item_id-nya TERISI, bukan master bumbu murni) sebagai bumbu.
        $response = $this->actingAs($admin)->post('/master/produk-jual', [
            'kode_item' => 'PJ-CYCLIC-001', 'nama_item' => 'Produk Cyclic Test', 'tipe' => 'produk_jual',
            'satuan' => 'porsi', 'harga_jual' => '15000', 'is_active' => '1',
            'resep' => [['resep_bumbu_ref_id' => $resepProdukLain->id, 'qty_per_unit' => '1', 'is_wajib' => '1']],
        ]);

        $response->assertRedirect();
        $item = Item::where('kode_item', 'PJ-CYCLIC-001')->firstOrFail();
        // Baris ditolak/di-skip di syncResep() (bumbuValidIds check) -- resep tetap
        // dibuat (krn ada payload non-kosong) tapi items-nya kosong, bukan tersimpan.
        $this->assertEquals(0, $item->resep?->items()->count() ?? 0, 'Link ke resep produk lain (bukan master bumbu murni) harus ditolak/di-skip.');
    }

    public function test_master_bumbu_pusat_tidak_bisa_diberi_item_bertipe_link(): void
    {
        // storeItem() di MasterResepBumbuController TIDAK PERNAH menerima
        // field resep_bumbu_ref_id -- anti cyclic-reference by construction.
        $owner = $this->buatUser('owner');
        $bahanA = $this->bahanBaku('Bahan A');
        $bumbuA = $this->buatMasterBumbu('Bumbu A', [['item' => $bahanA, 'qty' => 5]]);
        $bumbuB = ResepBumbu::create(['nama' => 'Bumbu B', 'kode' => 'BMB-B-' . uniqid(), 'item_id' => null, 'is_active' => true]);

        $response = $this->actingAs($owner)->post("/master/resep-bumbu/{$bumbuB->id}/items", [
            'item_id' => $bahanA->id, // storeItem cuma terima ini
            'resep_bumbu_ref_id' => $bumbuA->id, // field ini TIDAK ADA di validasi controller, harus diabaikan
            'qty_per_unit' => 5, 'satuan' => 'gram', 'mode_harga' => 'gratis',
        ]);

        $response->assertRedirect();
        $bumbuB->refresh();
        $inserted = $bumbuB->items()->first();
        $this->assertNotNull($inserted);
        $this->assertNull($inserted->resep_bumbu_ref_id, 'Master Bumbu Pusat tidak boleh punya item ber-link ke bumbu lain (cegah cycle).');
    }

    // ===== 7. Permission (reuse master.produk_jual.edit) =====

    public function test_kasir_tidak_bisa_akses_form_produk_jual_sama_sekali(): void
    {
        $kasir = $this->buatUser('kasir');

        $this->actingAs($kasir)->get('/master/produk-jual/create')->assertForbidden();
        $this->actingAs($kasir)->getJson('/master/produk-jual/bumbu-pusat/list')->assertForbidden();
    }

    // ===== 8. Panduan + Tooltip + Cara Pakai =====

    public function test_panduan_produk_jual_berisi_section_import_bumbu(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/panduan/produk-jual');

        $response->assertOk();
        $response->assertSee('Import dari Bumbu Pusat');
        $response->assertSee('Bumbu Kecap Manis Standar');
    }

    public function test_tooltip_import_bumbu_dan_baris_linked_muncul_di_form(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $response = $this->actingAs($admin)->get('/master/produk-jual/create');

        $response->assertOk();
        // Tooltip di-render sebagai data-bs-title berisi judul+konten (lihat components/tooltip.blade.php)
        $response->assertSee('Pakai ini kalau bumbu/campuran bahan sudah didaftarkan', false);
    }

    public function test_cara_pakai_tetap_ada_di_halaman_produk_jual(): void
    {
        $admin = $this->buatUser('admin_pusat');

        $this->actingAs($admin)->get('/master/produk-jual/create')
            ->assertSee('data-bs-target="#panduanModal-produk-jual"', false);
    }

    // ===== 9. Fitur existing terdampak — regresi =====

    public function test_regresi_create_produk_tanpa_import_bahan_manual_masih_normal(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $bahan = $this->bahanBaku('Bahan Manual Saja');

        $response = $this->actingAs($admin)->post('/master/produk-jual', [
            'kode_item' => 'PJ-REG-001', 'nama_item' => 'Produk Regresi Manual', 'tipe' => 'produk_jual',
            'satuan' => 'porsi', 'harga_jual' => '15000', 'is_active' => '1',
            'resep' => [['item_id' => $bahan->id, 'qty_per_unit' => '2', 'satuan' => 'pcs', 'is_wajib' => '1', 'mode_harga' => 'gratis']],
        ]);

        $response->assertRedirect();
        $item = Item::where('kode_item', 'PJ-REG-001')->firstOrFail();
        $this->assertNotNull($item->resep);
        $this->assertFalse($item->resep->items->first()->isLinked());
    }

    public function test_regresi_edit_produk_dengan_varian_masih_normal(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $item = Item::create(['kode_item' => 'PJ-REG-VAR-001', 'nama_item' => 'Produk Varian Regresi', 'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 10000, 'is_active' => true]);

        $response = $this->actingAs($admin)->put("/master/produk-jual/{$item->id}", [
            'kode_item' => 'PJ-REG-VAR-001', 'nama_item' => 'Produk Varian Regresi', 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => '10000', 'is_active' => '1',
            'punya_varian' => '1', 'atribut' => [0 => ['nama' => 'Size', 'nilai' => 'S, M, L']],
        ]);

        $response->assertRedirect();
        $item->refresh();
        $this->assertTrue($item->punya_varian);
        $this->assertEquals(3, $item->variants()->count());
    }

    public function test_regresi_upload_foto_masih_normal(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $foto = \Illuminate\Http\UploadedFile::fake()->image('produk.jpg', 500, 500);

        $response = $this->actingAs($admin)->post('/master/produk-jual', [
            'kode_item' => 'PJ-REG-FOTO-001', 'nama_item' => 'Produk Foto Regresi', 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => '10000', 'is_active' => '1', 'foto' => $foto,
        ]);

        $response->assertRedirect();
        $item = Item::where('kode_item', 'PJ-REG-FOTO-001')->firstOrFail();
        $this->assertNotNull($item->foto);
    }

    public function test_regresi_master_bumbu_pusat_crud_masih_normal(): void
    {
        $owner = $this->buatUser('owner');

        $storeResp = $this->actingAs($owner)->post('/master/resep-bumbu', [
            'nama' => 'Bumbu CRUD Regresi', 'kode' => 'BMB-' . uniqid(),
        ]);
        $storeResp->assertRedirect();
        $bumbu = ResepBumbu::where('nama', 'Bumbu CRUD Regresi')->firstOrFail();

        $editResp = $this->actingAs($owner)->get("/master/resep-bumbu/{$bumbu->id}/edit");
        $editResp->assertOk();

        $destroyResp = $this->actingAs($owner)->delete("/master/resep-bumbu/{$bumbu->id}");
        $destroyResp->assertRedirect();
        $bumbu->refresh();
        $this->assertFalse($bumbu->is_active);
    }

    public function test_pos_checkout_produk_dengan_bumbu_linked_stok_terpotong_benar(): void
    {
        $cabang = $this->cabangPertama();
        $kasir = $this->buatUser('kasir', $cabang->id);
        $bahanManual = $this->bahanBaku('Kulit Manual');
        $bahanBumbu = $this->bahanBaku('Bahan Dalam Bumbu');
        Stock::updateOrCreate(['item_id' => $bahanManual->id, 'lokasi_id' => $cabang->id], ['qty' => 100, 'qty_minimum' => 0]);
        Stock::updateOrCreate(['item_id' => $bahanBumbu->id, 'lokasi_id' => $cabang->id], ['qty' => 100, 'qty_minimum' => 0]);
        Kas::firstOrCreate(['cabang_id' => $cabang->id, 'default_untuk' => 'tunai'], ['nama_kas' => 'Kas', 'tipe_kas' => 'tunai', 'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true]);

        $bumbu = $this->buatMasterBumbu('Bumbu POS Test', [['item' => $bahanBumbu, 'qty' => 50, 'satuan' => 'gram', 'mode_harga' => 'pakai_master']]);
        $item = Item::create(['kode_item' => 'PJ-POS-BUMBU-001', 'nama_item' => 'Produk POS Bumbu', 'tipe' => 'produk_jual', 'satuan' => 'porsi', 'harga_jual' => 20000, 'is_active' => true]);
        $resep = ResepBumbu::create(['nama' => $item->nama_item, 'kode' => 'R-' . uniqid(), 'item_id' => $item->id, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $resep->id, 'item_id' => $bahanManual->id, 'qty_per_unit' => 3, 'satuan' => 'pcs', 'is_wajib' => true, 'mode_harga' => 'gratis', 'urutan' => 0]);
        ResepBumbuItem::create(['resep_bumbu_id' => $resep->id, 'resep_bumbu_ref_id' => $bumbu->id, 'qty_per_unit' => 2, 'satuan' => 'porsi', 'is_wajib' => true, 'mode_harga' => 'gratis', 'urutan' => 1]);

        $kas = Kas::where('cabang_id', $cabang->id)->first();
        $order = app(PenjualanService::class)->buatOrder([
            'kasir_id' => $kasir->id, 'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway',
            'nama_pelanggan' => 'Test', 'items' => [['item_id' => $item->id, 'qty' => 1, 'harga_satuan' => 20000, 'satuan' => 'porsi', 'nama_item' => $item->nama_item]],
            'payments' => [['metode' => 'tunai', 'jumlah' => 20000, 'kas_id' => $kas->id]],
            'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => 20000,
        ], $cabang->id);

        $this->assertNotNull($order);
        $stokManualSesudah = Stock::where('item_id', $bahanManual->id)->where('lokasi_id', $cabang->id)->value('qty');
        $stokBumbuSesudah = Stock::where('item_id', $bahanBumbu->id)->where('lokasi_id', $cabang->id)->value('qty');
        $this->assertEquals(97, (float) $stokManualSesudah, '3 pcs manual terpotong dari 100.');
        // 50 gram/porsi * 2 porsi = 100 gram = 0.1 kg terpotong dari 100.
        $this->assertEquals(99.9, (float) $stokBumbuSesudah, 'Bahan di dalam bumbu terlink harus ikut terpotong (0.1kg dari 100kg).');
    }
}
