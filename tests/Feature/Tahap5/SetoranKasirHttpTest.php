<?php

namespace Tests\Feature\Tahap5;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use App\Models\Setoran;
use App\Models\Stock;
use App\Models\TransaksiKeuangan;
use App\Services\PenjualanService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

class SetoranKasirHttpTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function kasCabang(Cabang $cabang, string $defaultUntuk = 'tunai', float $saldo = 0): Kas
    {
        return Kas::create([
            'cabang_id' => $cabang->id, 'nama_kas' => 'Kas Tunai ' . $cabang->nama_cabang,
            'tipe_kas' => 'tunai', 'default_untuk' => $defaultUntuk,
            'saldo_awal' => $saldo, 'saldo_sekarang' => $saldo, 'is_active' => true,
        ]);
    }

    private function hoCabang(): Cabang
    {
        return Cabang::where('tipe', 'gudang_pusat')->firstOrFail();
    }

    /** cabangPertama()/cabangKedua() dari trait mengambil ID terkecil TANPA
     * filter tipe — di seeder D'mentai cabang id=1 KEBETULAN adalah Gudang
     * Pusat (HO), bukan outlet operasional. Test approve butuh cabang ASAL
     * yang genuinely BUKAN HO, supaya tidak collision dgn kasCabang($ho). */
    private function cabangOperasional(): Cabang
    {
        return Cabang::where('tipe', 'cabang')->orderBy('id')->firstOrFail();
    }

    /** cabangKedua() dari trait (skip 1 baris ID terkecil) KEBETULAN juga
     * resolve ke cabang yang SAMA dengan cabangOperasional() di seeder ini
     * (id=1 HO, id=2 cabang operasional pertama) — pakai ini utk cabang
     * KEDUA yang genuinely beda dari cabangOperasional(). */
    private function cabangOperasionalKedua(): Cabang
    {
        return Cabang::where('tipe', 'cabang')->orderBy('id')->skip(1)->firstOrFail();
    }

    /** Bikin 1 order tunai lunas untuk cabang+tanggal tertentu (via PenjualanService asli, bukan insert manual). */
    private function buatOrderTunai(Cabang $cabang, int $kasirId, float $harga = 6000, int $qty = 2): void
    {
        $item = Item::where('kode_item', 'PJ-MNM-001')->firstOrFail(); // Es Teh Manis, tanpa resep
        Stock::updateOrCreate(['item_id' => $item->id, 'lokasi_id' => $cabang->id], ['qty' => 100, 'qty_minimum' => 0]);

        $kas = Kas::where('cabang_id', $cabang->id)->where('default_untuk', 'tunai')->first();
        $total = $item->harga_jual * $qty;

        app(PenjualanService::class)->buatOrder([
            'kasir_id' => $kasirId, 'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway',
            'nama_pelanggan' => 'Test QA',
            'items' => [['item_id' => $item->id, 'qty' => $qty, 'harga_satuan' => $item->harga_jual, 'satuan' => $item->satuan, 'nama_item' => $item->nama_item]],
            'payments' => [['metode' => 'tunai', 'jumlah' => $total, 'kas_id' => $kas->id]],
            'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => $total,
        ], $cabang->id);
    }

    public function test_kasir_submit_setoran_auto_hitung_dari_penjualan(): void
    {
        $cabang = $this->cabangOperasional();
        $kasCabang = $this->kasCabang($cabang);
        $kasir = $this->buatUser('kasir', $cabang->id);
        $this->buatOrderTunai($cabang, $kasir->id, 6000, 2); // 12.000

        $response = $this->actingAs($kasir)
            ->withSession(['active_cabang_id' => $cabang->id])
            ->post('/setoran-kasir', ['tanggal' => now()->format('Y-m-d'), 'total_disetor' => 12000]);

        $response->assertRedirect();
        $setoran = Setoran::where('cabang_id', $cabang->id)->first();
        $this->assertNotNull($setoran);
        $this->assertSame('menunggu', $setoran->status->value);
        $this->assertEquals(12000, $setoran->total_penjualan_sistem);
        $this->assertEquals(12000, $setoran->total_disetor);
        $this->assertEquals(0, $setoran->selisih);
        // Tahap 7 D'mentai (2026-09-18) — Gojek/Grab dihapus dari TipePembayaran
        // (keputusan Owner, fokus retail walk-in), sisa 3 metode.
        $this->assertCount(3, $setoran->details); // tunai/transfer/qris
    }

    public function test_ho_lihat_list_setoran_pending(): void
    {
        $cabang = $this->cabangOperasional();
        $this->kasCabang($cabang);
        $kasir = $this->buatUser('kasir', $cabang->id);
        $this->buatOrderTunai($cabang, $kasir->id);
        $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])
            ->post('/setoran-kasir', ['tanggal' => now()->format('Y-m-d'), 'total_disetor' => 12000]);

        $admin = $this->buatUser('admin_pusat');
        $response = $this->actingAs($admin)->get('/setoran-kasir?status=menunggu');

        $response->assertOk();
        $response->assertSee($cabang->nama_cabang);
    }

    public function test_ho_approve_memindahkan_kas_dan_membuat_transaksi_keuangan(): void
    {
        $cabang = $this->cabangOperasional();
        $kasCabang = $this->kasCabang($cabang, 'tunai', 50000);
        $ho = $this->hoCabang();
        $kasHo = $this->kasCabang($ho, 'tunai', 100000);
        $kasir = $this->buatUser('kasir', $cabang->id);
        $this->buatOrderTunai($cabang, $kasir->id, 6000, 2);

        $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])
            ->post('/setoran-kasir', ['tanggal' => now()->format('Y-m-d'), 'total_disetor' => 12000]);
        $setoran = Setoran::where('cabang_id', $cabang->id)->firstOrFail();

        $admin = $this->buatUser('admin_pusat');
        $response = $this->actingAs($admin)->post("/setoran-kasir/{$setoran->id}/approve", ['catatan_ho' => 'OK diterima']);

        $response->assertRedirect();
        $setoran->refresh();
        $this->assertSame('approved', $setoran->status->value);
        $this->assertNotNull($setoran->transaksi_out_id);
        $this->assertNotNull($setoran->transaksi_in_id);

        $kasCabang->refresh();
        $kasHo->refresh();
        // Order tunai MENAMBAH saldo kas cabang (pendapatan masuk): 50000+12000=62000.
        // Approve setoran lalu MENGURANGI 12000 lagi (uang keluar ke HO): 62000-12000=50000.
        $this->assertEquals(50000, $kasCabang->saldo_sekarang);
        $this->assertEquals(112000, $kasHo->saldo_sekarang);

        $this->assertDatabaseHas('transaksi_keuangans', [
            'id' => $setoran->transaksi_out_id, 'referensi_type' => 'setoran_kasir', 'referensi_id' => $setoran->id, 'tipe' => 'pengeluaran',
        ]);
        $this->assertDatabaseHas('transaksi_keuangans', [
            'id' => $setoran->transaksi_in_id, 'referensi_type' => 'setoran_kasir', 'referensi_id' => $setoran->id, 'tipe' => 'pemasukan',
        ]);
    }

    public function test_ho_reject_dengan_alasan_dan_kasir_bisa_revise(): void
    {
        $cabang = $this->cabangOperasional();
        $this->kasCabang($cabang);
        $kasir = $this->buatUser('kasir', $cabang->id);
        $this->buatOrderTunai($cabang, $kasir->id);
        $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])
            ->post('/setoran-kasir', ['tanggal' => now()->format('Y-m-d'), 'total_disetor' => 12000]);
        $setoran = Setoran::where('cabang_id', $cabang->id)->firstOrFail();

        $admin = $this->buatUser('admin_pusat');
        $reject = $this->actingAs($admin)->post("/setoran-kasir/{$setoran->id}/reject", ['alasan' => 'Nominal tidak sesuai laporan fisik']);
        $reject->assertRedirect();

        $setoran->refresh();
        $this->assertSame('rejected', $setoran->status->value);
        $this->assertSame('Nominal tidak sesuai laporan fisik', $setoran->ditolak_alasan);

        // Revise: submit ulang tanggal SAMA — harus update in place, bukan gagal unique constraint
        $revise = $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])
            ->post('/setoran-kasir', ['tanggal' => now()->format('Y-m-d'), 'total_disetor' => 11500, 'catatan_kasir' => 'Revisi setelah dihitung ulang']);
        $revise->assertRedirect();

        $setoran->refresh();
        $this->assertSame('menunggu', $setoran->status->value);
        $this->assertEquals(11500, $setoran->total_disetor);
        $this->assertSame(1, Setoran::where('cabang_id', $cabang->id)->count(), 'Revise harus update in place, bukan bikin baris baru.');
    }

    public function test_kasir_cabang_a_tidak_bisa_lihat_setoran_cabang_b(): void
    {
        $cabangA = $this->cabangOperasional();
        $cabangB = $this->cabangOperasionalKedua();
        $this->kasCabang($cabangA);
        $kasirA = $this->buatUser('kasir', $cabangA->id);
        $this->buatOrderTunai($cabangA, $kasirA->id);
        $this->actingAs($kasirA)->withSession(['active_cabang_id' => $cabangA->id])
            ->post('/setoran-kasir', ['tanggal' => now()->format('Y-m-d'), 'total_disetor' => 12000]);
        $setoranA = Setoran::where('cabang_id', $cabangA->id)->firstOrFail();

        $kasirB = $this->buatUser('kasir', $cabangB->id);
        $response = $this->actingAs($kasirB)->withSession(['active_cabang_id' => $cabangB->id])
            ->get("/setoran-kasir/{$setoranA->id}");

        $response->assertForbidden();
    }

    public function test_kasir_tidak_bisa_approve(): void
    {
        $cabang = $this->cabangOperasional();
        $this->kasCabang($cabang);
        $kasir = $this->buatUser('kasir', $cabang->id);
        $this->buatOrderTunai($cabang, $kasir->id);
        $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])
            ->post('/setoran-kasir', ['tanggal' => now()->format('Y-m-d'), 'total_disetor' => 12000]);
        $setoran = Setoran::where('cabang_id', $cabang->id)->firstOrFail();

        $response = $this->actingAs($kasir)->post("/setoran-kasir/{$setoran->id}/approve", []);

        $response->assertForbidden();
    }

    public function test_approve_dobel_ditolak(): void
    {
        $cabang = $this->cabangOperasional();
        $this->kasCabang($cabang, 'tunai', 50000);
        $ho = $this->hoCabang();
        $this->kasCabang($ho, 'tunai', 100000);
        $kasir = $this->buatUser('kasir', $cabang->id);
        $this->buatOrderTunai($cabang, $kasir->id);
        $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])
            ->post('/setoran-kasir', ['tanggal' => now()->format('Y-m-d'), 'total_disetor' => 12000]);
        $setoran = Setoran::where('cabang_id', $cabang->id)->firstOrFail();

        $admin = $this->buatUser('admin_pusat');
        $this->actingAs($admin)->post("/setoran-kasir/{$setoran->id}/approve", []);

        $response = $this->actingAs($admin)->post("/setoran-kasir/{$setoran->id}/approve", []);
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // ===== Regresi =====

    public function test_regresi_transfer_dana_existing_tetap_normal(): void
    {
        $cabangAsal = $this->cabangOperasional();
        $cabangTujuan = $this->cabangOperasionalKedua();
        $kasAsal = $this->kasCabang($cabangAsal, 'tunai', 100000);
        $kasTujuan = $this->kasCabang($cabangTujuan, 'tunai', 0);
        $manajer = $this->buatUser('manajer_cabang', $cabangAsal->id);

        $foto = \Illuminate\Http\UploadedFile::fake()->image('bukti.jpg');
        $response = $this->actingAs($manajer)->withSession(['active_cabang_id' => $cabangAsal->id])->post('/transfer-dana', [
            'tanggal' => now()->format('Y-m-d'), 'kas_asal_id' => $kasAsal->id,
            'cabang_tujuan_id' => $cabangTujuan->id, 'kas_tujuan_id' => $kasTujuan->id,
            'jumlah' => 20000, 'keterangan' => 'Test regresi transfer dana', 'bukti' => $foto,
        ]);

        $response->assertRedirect(route('setoran.index'));
        $kasAsal->refresh();
        $this->assertEquals(80000, $kasAsal->saldo_sekarang);
    }

    public function test_regresi_pos_tetap_normal(): void
    {
        $cabang = $this->cabangOperasional();
        $this->kasCabang($cabang);
        $kasir = $this->buatUser('kasir', $cabang->id);

        $response = $this->actingAs($kasir)->withSession(['active_cabang_id' => $cabang->id])->get('/penjualan/pos');

        $response->assertOk();
    }
}
