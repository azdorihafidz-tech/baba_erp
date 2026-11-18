<?php

namespace Tests\Feature\Tahap7;

use App\Enums\StatusOrder;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\JenisOlahan;
use App\Models\Kas;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Pelanggan;
use App\Models\Stock;
use App\Models\TransaksiKeuangan;
use App\Services\CascadeDeleteService;
use App\Services\KetersediaanProdukService;
use App\Services\LaporanLabaRugiService;
use App\Services\PenjualanService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * 4.50 — perbaikan 5 temuan audit Kamus Menu:
 * 1 Edit order dibayar mengunci diskon/metode (kas & Setoran Kasir tidak rusak)
 * 2 Hapus pelanggan tidak menghapus order-nya + saklar Aktif
 * 3 Hapus barang: riwayat tetap, ditolak kalau masih dipakai, foto tidak dihapus
 * 4 Tambahan Gratis (Lacak Stok) & Produk Tambahan memotong stok di POS
 * 5 Jenis Menu di Produk Jual → Laba Rugi "Per Jenis Menu"
 */
class Perbaikan450Test extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    protected function setUp(): void
    {
        parent::setUp();
        Cabang::query()->update(['wajib_bukti_transfer' => false, 'wajib_bukti_qris' => false]);
    }

    private function cabang(): Cabang
    {
        $cabang = Cabang::where('tipe', 'cabang')->orderBy('id')->firstOrFail();
        foreach (['tunai', 'transfer', 'qris'] as $jenis) {
            Kas::firstOrCreate(
                ['cabang_id' => $cabang->id, 'default_untuk' => $jenis],
                ['nama_kas' => "Kas {$jenis}", 'tipe_kas' => $jenis === 'tunai' ? 'tunai' : 'bank', 'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true]
            );
        }

        return $cabang;
    }

    private function produk(Cabang $cabang, array $attr = []): Item
    {
        $item = Item::create(array_merge([
            'cabang_id' => $cabang->id, 'kode_item' => 'T450-' . uniqid(), 'nama_item' => 'Produk T450',
            'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 10000, 'is_active' => true, 'track_stok' => false,
        ], $attr));
        Stock::updateOrCreate(['item_id' => $item->id, 'lokasi_id' => $cabang->id], ['qty' => 0, 'qty_minimum' => 0]);

        return $item;
    }

    private function isiStok(Item $item, Cabang $cabang, float $qty, float $harga = 1000): void
    {
        app(StokService::class)->masuk($item->id, $cabang->id, $qty, 'stok test 450', 'test', null, $harga);
    }

    private function jual(Cabang $cabang, array $baris, array $payments, array $extra = []): Order
    {
        $kasir = $this->buatUser('kasir', $cabang->id);
        $this->actingAs($kasir);
        $items = array_map(fn ($b) => ['item_id' => $b[0]->id, 'qty' => $b[1], 'harga_satuan' => (float) $b[0]->harga_jual, 'satuan' => $b[0]->satuan, 'nama_item' => $b[0]->nama_item], $baris);

        return app(PenjualanService::class)->buatOrder(array_merge([
            'kasir_id' => $kasir->id, 'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway',
            'nama_pelanggan' => 'Pembeli 450', 'items' => $items, 'payments' => $payments,
            'tipe_pembayaran' => $payments[0]['metode'], 'jumlah_bayar' => array_sum(array_column($payments, 'jumlah')),
        ], $extra), $cabang->id);
    }

    // ---------------- 1. Edit order ----------------

    public function test_edit_order_dibayar_menolak_ubah_diskon_dan_metode(): void
    {
        $cabang = $this->cabang();
        $p = $this->produk($cabang);
        $this->isiStok($p, $cabang, 10);
        $kas = Kas::where('cabang_id', $cabang->id)->where('default_untuk', 'tunai')->first();
        $order = $this->jual($cabang, [[$p, 1]], [['metode' => 'tunai', 'jumlah' => 10000, 'kas_id' => $kas->id]]);
        $saldo = (float) $kas->fresh()->saldo_sekarang;
        $owner = $this->buatUser('owner');

        $this->actingAs($owner)->put(route('penjualan.update', $order), [
            'nama_pelanggan' => 'X', 'tipe_pembayaran' => 'tunai', 'diskon' => '2000',
        ])->assertSessionHas('error');
        $this->assertEquals(10000, (float) $order->fresh()->total_bayar);

        $this->actingAs($owner)->put(route('penjualan.update', $order), [
            'nama_pelanggan' => 'X', 'tipe_pembayaran' => 'qris', 'diskon' => '0',
        ])->assertSessionHas('error');

        // nama & catatan tetap boleh — keterangan transaksi ikut, jumlah & saldo tidak berubah
        $this->actingAs($owner)->put(route('penjualan.update', $order), [
            'nama_pelanggan' => 'Nama Baru', 'catatan' => 'ok',
        ])->assertRedirect(route('penjualan.show', $order));
        $this->assertSame('Nama Baru', $order->fresh()->nama_pelanggan);
        $tk = TransaksiKeuangan::where('referensi_type', 'order')->where('referensi_id', $order->id)->first();
        $this->assertEquals(10000, (float) $tk->jumlah);
        $this->assertStringContainsString('Nama Baru', $tk->keterangan);
        $this->assertEquals($saldo, (float) $kas->fresh()->saldo_sekarang);

        $this->actingAs($owner)->get(route('penjualan.edit', $order))->assertOk()->assertSee('Diskon dan metode bayar dikunci');
    }

    public function test_ganti_nama_order_split_tidak_menimpa_jumlah_tiap_pembayaran(): void
    {
        $cabang = $this->cabang();
        $p = $this->produk($cabang);
        $this->isiStok($p, $cabang, 10);
        $tunai = Kas::where('cabang_id', $cabang->id)->where('default_untuk', 'tunai')->first();
        $transfer = Kas::where('cabang_id', $cabang->id)->where('default_untuk', 'transfer')->first();
        $order = $this->jual($cabang, [[$p, 1]], [
            ['metode' => 'transfer', 'jumlah' => 4000, 'kas_id' => $transfer->id],
            ['metode' => 'tunai', 'jumlah' => 6000, 'kas_id' => $tunai->id],
        ]);

        $this->actingAs($this->buatUser('owner'))->put(route('penjualan.update', $order), ['nama_pelanggan' => 'Ganti'])->assertRedirect();

        $jumlah = TransaksiKeuangan::where('referensi_type', 'order')->where('referensi_id', $order->id)->orderBy('jumlah')->pluck('jumlah')->map(fn ($j) => (float) $j)->all();
        $this->assertSame([4000.0, 6000.0], $jumlah);
        $this->assertEquals(10000, (float) OrderPayment::where('order_id', $order->id)->sum('jumlah'));
        $this->assertTrue(TransaksiKeuangan::where('referensi_id', $order->id)->where('referensi_type', 'order')->where('keterangan', 'like', '%(Tunai)')->exists());
    }

    public function test_edit_bill_pending_masih_bisa_ubah_diskon_dan_service_charge_ikut(): void
    {
        $cabang = $this->cabang();
        $order = Order::create([
            'nomor_order' => 'T450-PEND-' . uniqid(), 'cabang_id' => $cabang->id, 'tanggal_order' => today(),
            'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway', 'status' => StatusOrder::Pending,
            'total_harga' => 20000, 'diskon' => 0, 'service_charge' => 2000, 'take_away_fee' => 1000, 'total_bayar' => 23000,
            'tipe_pembayaran' => 'tunai', 'kasir_id' => $this->buatUser('kasir', $cabang->id)->id,
        ]);
        $this->actingAs($this->buatUser('owner'))->put(route('penjualan.update', $order), [
            'tipe_pembayaran' => 'qris', 'diskon' => '10000',
        ])->assertRedirect();
        $o = $order->fresh();
        $this->assertEquals(10000, (float) $o->diskon);
        $this->assertEquals(1000, (float) $o->service_charge);   // 10% dari 10.000
        $this->assertEquals(12000, (float) $o->total_bayar);     // 10.000 + 1.000 + 1.000
        $this->assertSame('qris', $o->tipe_pembayaran->value);
    }

    // ---------------- 2. Pelanggan ----------------

    public function test_hapus_pelanggan_tidak_menghapus_order_dan_restore_hanya_yang_ikut_terhapus(): void
    {
        $cabang = $this->cabang();
        $p = $this->produk($cabang);
        $this->isiStok($p, $cabang, 10);
        $pel = Pelanggan::create(['kode_pelanggan' => 'P450' . rand(100, 999), 'nama_pelanggan' => 'Pel 450', 'is_active' => true]);
        $kas = Kas::where('cabang_id', $cabang->id)->where('default_untuk', 'tunai')->first();
        $order = $this->jual($cabang, [[$p, 1]], [['metode' => 'tunai', 'jumlah' => 10000, 'kas_id' => $kas->id]], ['pelanggan_id' => $pel->id]);

        app(CascadeDeleteService::class)->deletePelangganCascade($pel);
        $this->assertSoftDeleted('pelanggans', ['id' => $pel->id]);
        $this->assertNull($order->fresh()->deleted_at);
        $this->assertSame('Pel 450', $order->fresh()->pelanggan?->nama_pelanggan);

        // order lain yang dihapus sendiri TIDAK ikut kembali saat pelanggan dipulihkan
        $lain = Order::create([
            'nomor_order' => 'T450-L-' . uniqid(), 'cabang_id' => $cabang->id, 'tanggal_order' => today(), 'tipe_order' => 'penjualan',
            'tipe_transaksi' => 'takeaway', 'status' => StatusOrder::Dibatalkan, 'total_harga' => 0, 'diskon' => 0, 'total_bayar' => 0,
            'pelanggan_id' => $pel->id, 'kasir_id' => $order->kasir_id,
        ]);
        DB::table('orders')->where('id', $lain->id)->update(['deleted_at' => now()->subDay()]);
        app(CascadeDeleteService::class)->restorePelangganCascade($pel);
        $this->assertNotNull(DB::table('orders')->where('id', $lain->id)->value('deleted_at'));
        $this->assertNull(DB::table('pelanggans')->where('id', $pel->id)->value('deleted_at'));
    }

    public function test_pelanggan_bisa_dinonaktifkan_dan_hilang_dari_pos(): void
    {
        $pel = Pelanggan::create(['kode_pelanggan' => 'P450N' . rand(100, 999), 'nama_pelanggan' => 'Pel Nonaktif 450', 'is_active' => true]);
        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->put(route('pelanggan.update', $pel), [
            'kode_pelanggan' => $pel->kode_pelanggan, 'nama_pelanggan' => $pel->nama_pelanggan,
        ])->assertRedirect();
        $this->assertFalse((bool) $pel->fresh()->is_active);
        $this->assertFalse(Pelanggan::aktif()->whereKey($pel->id)->exists());

        $this->actingAs($owner)->put(route('pelanggan.update', $pel), [
            'kode_pelanggan' => $pel->kode_pelanggan, 'nama_pelanggan' => $pel->nama_pelanggan, 'is_active' => '1',
        ])->assertRedirect();
        $this->assertTrue((bool) $pel->fresh()->is_active);
        $this->actingAs($owner)->get(route('pelanggan.edit', $pel))->assertOk()->assertSee('pelangganAktif', false);
    }

    // ---------------- 3. Hapus barang ----------------

    public function test_hapus_barang_ditolak_kalau_masih_dipakai_dan_riwayat_tetap(): void
    {
        $cabang = $this->cabang();
        $bahan = $this->produk($cabang, ['tipe' => 'bahan_baku', 'track_stok' => true, 'nama_item' => 'Bahan T450']);
        $this->isiStok($bahan, $cabang, 5);
        $alasan = app(CascadeDeleteService::class)->alasanItemTidakBolehDihapus($bahan);
        $this->assertNotEmpty($alasan);
        $this->assertStringContainsString('masih ada stok', $alasan[0]);

        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->withSession(['active_cabang_id' => $cabang->id])
            ->delete(route('master.bahan-baku.destroy', $bahan))->assertSessionHas('error');
        $this->assertNull($bahan->fresh()->deleted_at);

        // produk jual yang sudah terjual: boleh dihapus, tapi baris order TIDAK ikut terhapus
        $p = $this->produk($cabang);
        $this->isiStok($p, $cabang, 1);
        $kas = Kas::where('cabang_id', $cabang->id)->where('default_untuk', 'tunai')->first();
        $order = $this->jual($cabang, [[$p, 1]], [['metode' => 'tunai', 'jumlah' => 10000, 'kas_id' => $kas->id]]);
        app(CascadeDeleteService::class)->deleteItemCascade($p->fresh());
        $this->assertSoftDeleted('items', ['id' => $p->id]);
        $this->assertSame(0, DB::table('order_items')->where('order_id', $order->id)->whereNotNull('deleted_at')->count());
    }

    public function test_hapus_bahan_yang_dipakai_komposisi_ditolak(): void
    {
        $cabang = $this->cabang();
        $bahan = $this->produk($cabang, ['tipe' => 'bahan_baku', 'nama_item' => 'Bahan Komposisi T450']);
        $p = $this->produk($cabang, ['nama_item' => 'Produk Pakai Bahan T450']);
        $resep = \App\Models\ResepBumbu::create(['cabang_id' => $cabang->id, 'nama' => 'Resep T450', 'kode' => 'R450-' . uniqid(), 'item_id' => $p->id, 'is_active' => true]);
        \App\Models\ResepBumbuItem::create(['resep_bumbu_id' => $resep->id, 'item_id' => $bahan->id, 'qty_per_unit' => 1, 'satuan' => 'pcs']);

        $this->expectExceptionMessage('dipakai di komposisi: Produk Pakai Bahan T450');
        app(CascadeDeleteService::class)->deleteItemCascade($bahan);
    }

    public function test_hapus_produk_jual_tidak_menghapus_file_foto(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('produk/foto450.jpg', 'x');
        $cabang = $this->cabang();
        $p = $this->produk($cabang, ['foto' => 'produk/foto450.jpg']);
        $this->actingAs($this->buatUser('owner'))->withSession(['active_cabang_id' => $cabang->id])
            ->delete(route('master.produk-jual.destroy', $p))->assertRedirect(route('master.produk-jual.index'));
        $this->assertSoftDeleted('items', ['id' => $p->id]);
        Storage::disk('public')->assertExists('produk/foto450.jpg');
    }

    // ---------------- 4. Tambahan memotong stok ----------------

    public function test_tambahan_gratis_lacak_stok_dipotong_dan_habis_kalau_kosong(): void
    {
        $cabang = $this->cabang();
        $sumpit = $this->produk($cabang, ['tipe' => 'tambahan_gratis', 'harga_jual' => 0, 'track_stok' => true, 'nama_item' => 'Sumpit T450', 'satuan' => 'pasang']);
        $this->assertFalse(app(KetersediaanProdukService::class)->untuk(collect([$sumpit]), $cabang->id)[$sumpit->id]['bisa_dijual']);

        $this->isiStok($sumpit, $cabang, 3, 200);
        $p = $this->produk($cabang);
        $this->isiStok($p, $cabang, 5);
        $kas = Kas::where('cabang_id', $cabang->id)->where('default_untuk', 'tunai')->first();
        $this->jual($cabang, [[$p, 1], [$sumpit, 2]], [['metode' => 'tunai', 'jumlah' => 10000, 'kas_id' => $kas->id]]);
        $this->assertEquals(1, app(StokService::class)->getStok($sumpit->id, $cabang->id));

        $this->expectExceptionMessage('Sumpit T450');
        $this->jual($cabang, [[$sumpit, 2]], [['metode' => 'tunai', 'jumlah' => 0, 'kas_id' => $kas->id]]);
    }

    public function test_tambahan_gratis_tanpa_lacak_stok_tidak_dipotong_dan_produk_tambahan_dipotong(): void
    {
        $cabang = $this->cabang();
        $saus = $this->produk($cabang, ['tipe' => 'tambahan_gratis', 'harga_jual' => 0, 'track_stok' => false, 'nama_item' => 'Saus T450']);
        $this->assertTrue(app(KetersediaanProdukService::class)->untuk(collect([$saus]), $cabang->id)[$saus->id]['bisa_dijual']);
        $this->assertFalse($saus->dipotongStokSendiri());

        $extra = $this->produk($cabang, ['tipe' => 'produk_tambahan', 'harga_jual' => 2000, 'nama_item' => 'Extra T450']);
        $this->assertTrue($extra->dipotongStokSendiri());
        $this->isiStok($extra, $cabang, 2);
        $kas = Kas::where('cabang_id', $cabang->id)->where('default_untuk', 'tunai')->first();
        $this->jual($cabang, [[$extra, 1], [$saus, 3]], [['metode' => 'tunai', 'jumlah' => 2000, 'kas_id' => $kas->id]]);
        $this->assertEquals(1, app(StokService::class)->getStok($extra->id, $cabang->id));
    }

    // ---------------- 5. Jenis Menu ----------------

    public function test_jenis_menu_tersimpan_di_order_dan_laba_rugi_per_jenis_terisi(): void
    {
        $cabang = $this->cabang();
        $jenis = JenisOlahan::create(['nama' => 'Jenis T450', 'slug' => 'jenis-t450-' . uniqid(), 'is_active' => true]);
        $p = $this->produk($cabang, ['jenis_olahan_id' => $jenis->id]);
        $this->isiStok($p, $cabang, 5);
        $kas = Kas::where('cabang_id', $cabang->id)->where('default_untuk', 'tunai')->first();
        $order = $this->jual($cabang, [[$p, 2]], [['metode' => 'tunai', 'jumlah' => 20000, 'kas_id' => $kas->id]]);
        $this->assertSame('Jenis T450', DB::table('order_items')->where('order_id', $order->id)->value('jenis_olahan'));

        // order lama tanpa snapshot → memakai Jenis Menu produk sekarang
        DB::table('order_items')->where('order_id', $order->id)->update(['jenis_olahan' => null]);
        $rows = app(LaporanLabaRugiService::class)->getBreakdownPerJenisOlahan(today()->startOfDay(), today()->endOfDay(), $cabang->id);
        $this->assertTrue(collect($rows)->contains(fn ($r) => $r->jenis_olahan === 'Jenis T450' && (float) $r->total_omzet === 20000.0));
    }

    public function test_form_produk_jual_punya_pilihan_jenis_menu_dan_tersimpan(): void
    {
        $cabang = $this->cabang();
        $jenis = JenisOlahan::create(['nama' => 'Jenis Form 450', 'slug' => 'jenis-form-450-' . uniqid(), 'is_active' => true]);
        $p = $this->produk($cabang);
        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->withSession(['active_cabang_id' => $cabang->id])
            ->get(route('master.produk-jual.edit', $p))->assertOk()->assertSee('name="jenis_olahan_id"', false)->assertSee('Jenis Form 450');

        $this->actingAs($owner)->withSession(['active_cabang_id' => $cabang->id])->put(route('master.produk-jual.update', $p), [
            'kode_item' => $p->kode_item, 'nama_item' => $p->nama_item, 'tipe' => 'produk_jual', 'satuan' => 'pcs',
            'harga_jual' => 10000, 'is_active' => 1, 'jenis_olahan_id' => $jenis->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($jenis->id, (int) $p->fresh()->jenis_olahan_id);
    }

    // ---------------- Keamanan dashboard (ditemukan saat Kamus Tahap 2) ----------------

    public function test_dashboard_semua_cabang_dan_gudang_tidak_bisa_dibuka_kasir_lewat_alamat(): void
    {
        $cabang = $this->cabang();
        $kasir = $this->buatUser('kasir', $cabang->id);
        $this->actingAs($kasir)->get(route('dashboard.pusat'))->assertRedirect(route('dashboard'));
        if (Cabang::gudangPusat()->exists()) {
            $this->actingAs($kasir)->get(route('dashboard.gudang'))->assertRedirect(route('dashboard.cabang'));
        }
        $this->actingAs($this->buatUser('owner'))->get(route('dashboard.pusat'))->assertOk();
    }

    // ---------------- Karyawan ↔ akun login (ditemukan saat Kamus Tahap 2) ----------------

    private function karyawanDenganUser(\App\Models\User $user, Cabang $cabang): \App\Models\Karyawan
    {
        return \App\Models\Karyawan::create([
            'cabang_id' => $cabang->id, 'user_id' => $user->id, 'nik' => 'NIK450' . rand(10000, 99999), 'nama_lengkap' => 'Karyawan T450', 'jenis_kelamin' => 'L',
            'jabatan' => 'Staf', 'tipe_karyawan' => 'tetap', 'tanggal_masuk' => today(), 'gaji_pokok' => 1000000, 'status' => 'aktif',
        ]);
    }

    private function dataKaryawan(\App\Models\Karyawan $k, array $extra = []): array
    {
        return array_merge([
            'cabang_id' => $k->cabang_id, 'nik' => $k->nik, 'nama_lengkap' => $k->nama_lengkap, 'jenis_kelamin' => 'L', 'jabatan' => 'Staf',
            'tipe_karyawan' => 'tetap', 'tanggal_masuk' => today()->toDateString(), 'gaji_pokok' => 1000000, 'status' => 'aktif',
        ], $extra);
    }

    public function test_edit_karyawan_milik_owner_tidak_menurunkan_role_owner(): void
    {
        $cabang = $this->cabang();
        $ownerAkun = $this->buatUser('owner');
        $k = $this->karyawanDenganUser($ownerAkun, $cabang);
        $admin = $this->buatUser('admin_pusat');

        // form tidak menampilkan pilihan role untuk akun Owner
        $this->actingAs($admin)->get(route('karyawan.edit', $k))->assertOk()->assertDontSee('name="user_role"', false)->assertSee('Ini akun Owner');

        // simpan biasa (tanpa field akun) → role Owner tetap
        $this->actingAs($admin)->put(route('karyawan.update', $k), $this->dataKaryawan($k, ['jabatan' => 'Pemilik']))->assertRedirect(route('karyawan.show', $k));
        $this->assertSame('owner', $ownerAkun->fresh()->role->value);

        // memaksa kirim role lain → ditolak, role tetap Owner
        $this->actingAs($admin)->put(route('karyawan.update', $k), $this->dataKaryawan($k, ['user_role' => 'kasir']))->assertSessionHas('error');
        $this->assertSame('owner', $ownerAkun->fresh()->role->value);
    }

    public function test_ubah_role_akun_karyawan_butuh_izin_edit_user(): void
    {
        $cabang = $this->cabang();
        $kasirAkun = $this->buatUser('kasir', $cabang->id);
        $k = $this->karyawanDenganUser($kasirAkun, $cabang);
        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->put(route('karyawan.update', $k), $this->dataKaryawan($k, ['user_role' => 'manajer_cabang']))->assertRedirect();
        $this->assertSame('manajer_cabang', $kasirAkun->fresh()->role->value);
    }
}
