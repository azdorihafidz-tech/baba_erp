<?php

namespace Tests\Feature\Tahap25;

use App\Models\Item;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tahap 2.5 D'mentai — verifikasi migration baru (item_cabang, extend enum
 * tipe) dan klasifikasi ulang 21 item dummy existing.
 *
 * Reversibilitas migration ini SUDAH diverifikasi manual sebelum test ini
 * ditulis (migrate -> rollback --step=2 -> migrate ulang terhadap database
 * disposable `erp_dimsum_test`, dicek langsung via SHOW COLUMNS/SHOW TABLES)
 * — DDL (ALTER TABLE/CREATE/DROP TABLE) auto-commit di MySQL sehingga TIDAK
 * aman diulang-ulang di dalam test transaction (transaction tidak melindungi
 * DDL). Test di file ini murni assert STATE SETELAH migration, bukan
 * menjalankan up()/down() lagi.
 */
class SchemaAndKlasifikasiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_item_cabang_table_exists_dengan_kolom_dan_unique_yang_benar(): void
    {
        $this->assertTrue(Schema::hasTable('item_cabang'));
        $this->assertTrue(Schema::hasColumns('item_cabang', [
            'id', 'item_id', 'cabang_id', 'harga_override', 'is_active', 'created_at', 'updated_at',
        ]));
    }

    public function test_item_cabang_unique_constraint_item_dan_cabang(): void
    {
        $item = Item::where('kode_item', 'PJ-DIM-001')->firstOrFail();
        $cabangId = $this->cabangIdPertama();

        DB::table('item_cabang')->insert(['item_id' => $item->id, 'cabang_id' => $cabangId, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('item_cabang')->insert(['item_id' => $item->id, 'cabang_id' => $cabangId, 'is_active' => false, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_items_tipe_enum_terisi_5_nilai_baru(): void
    {
        $tipes = DB::table('items')->select('tipe')->distinct()->pluck('tipe')->sort()->values()->all();

        foreach (['bahan_baku', 'kemasan', 'tambahan_gratis', 'produk_jual', 'produk_tambahan'] as $expected) {
            $this->assertContains($expected, $tipes, "Tipe '$expected' tidak ditemukan di data items — reklasifikasi gagal.");
        }
    }

    public function test_klasifikasi_21_item_dummy_sesuai_yang_disetujui(): void
    {
        $expected = [
            'PJ-DIM-001' => 'produk_jual', 'PJ-DIM-002' => 'produk_jual', 'PJ-DIM-003' => 'produk_jual', 'PJ-DIM-004' => 'produk_jual',
            'PJ-GYZ-001' => 'produk_jual', 'PJ-GYZ-002' => 'produk_jual',
            'PJ-MNM-001' => 'produk_jual', 'PJ-MNM-002' => 'produk_jual',
            'PJ-FRZ-001' => 'produk_jual', 'PJ-FRZ-002' => 'produk_jual',
            'BB-BHN-001' => 'bahan_baku', 'BB-BHN-002' => 'bahan_baku', 'BB-BHN-003' => 'bahan_baku',
            'BB-BHN-004' => 'bahan_baku', 'BB-BHN-005' => 'bahan_baku', 'BB-BHN-006' => 'bahan_baku',
            'KM-KMS-001' => 'kemasan', 'KM-KMS-002' => 'kemasan', 'KM-KMS-003' => 'kemasan',
            'TB-TMB-001' => 'tambahan_gratis',
            'TB-TMB-002' => 'produk_tambahan',
        ];

        foreach ($expected as $kode => $tipe) {
            $actual = Item::where('kode_item', $kode)->value('tipe');
            $this->assertSame($tipe, $actual, "Item {$kode} seharusnya tipe={$tipe}, ditemukan={$actual}");
        }

        $this->assertCount(21, $expected);
    }

    public function test_item_variant_dan_resep_bumbu_link_tidak_rusak_oleh_reklasifikasi(): void
    {
        $dimsumMentai = Item::where('kode_item', 'PJ-DIM-003')->firstOrFail();
        $this->assertTrue($dimsumMentai->punya_varian);
        $this->assertGreaterThan(0, $dimsumMentai->variants()->count());
        $this->assertNotNull($dimsumMentai->resep, 'PJ-DIM-003 harus tetap punya resep setelah tipe direklasifikasi.');
    }

    private function cabangIdPertama(): int
    {
        return (int) DB::table('cabangs')->orderBy('id')->value('id');
    }
}
