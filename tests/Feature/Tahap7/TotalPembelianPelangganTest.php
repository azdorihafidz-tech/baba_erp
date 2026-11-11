<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\LoyaltyProgram;
use App\Models\Pelanggan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Bug fix 2026-09-21 (lanjutan fix Loyalty Program): halaman detail
 * pelanggan (menu Pelanggan → klik 1 pelanggan) punya 2 widget beda
 * definisi -- "Total Belanja" (tanpa filter status/tanggal) dan "Total Kg
 * Giling" (SELALU 0 krn D'mentai tidak pernah pakai basis kg, dead-weight
 * warisan Berkah Mulyo). Diselaraskan jadi SATU widget "Total Pembelian"
 * (Pelanggan::getTotalPembelianAttribute() -- order status=selesai DAN
 * tanggal_order < hari ini, sesuai alur bisnis retail D'mentai: order POS
 * langsung lunas saat itu juga, tidak ada konsep "pending lama").
 *
 * Bonus fix: section "Program Loyalty" di halaman yang sama juga hardcode
 * teks "berat gilingan"/"kg" apapun basis program (sama bug yang sudah
 * difix di loyalty-program/show.blade.php sebelumnya) -- dibuat dinamis.
 */
class TotalPembelianPelangganTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function cabangOperasional(): Cabang
    {
        return Cabang::where('tipe', 'cabang')->orderBy('id')->firstOrFail();
    }

    private function insertOrder(Pelanggan $pelanggan, float $totalBayar, string $status, string $tanggalOrder, string $nomor): void
    {
        DB::table('orders')->insert([
            'cabang_id' => $this->cabangOperasional()->id, 'nomor_order' => $nomor,
            'tanggal_order' => $tanggalOrder, 'tipe_order' => 'penjualan', 'status' => $status,
            'pelanggan_id' => $pelanggan->id, 'total_bayar' => $totalBayar, 'jumlah_bayar' => $totalBayar, 'kembalian' => 0,
            'tipe_pembayaran' => 'tunai', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_accessor_total_pembelian_pelanggan_tanpa_transaksi_nol(): void
    {
        $pelanggan = Pelanggan::create(['nama_pelanggan' => 'Pelanggan Kosong', 'kode_pelanggan' => 'PEL-TP-001', 'is_active' => true]);

        $this->assertEquals(0, $pelanggan->total_pembelian);
    }

    public function test_accessor_total_pembelian_sum_3_transaksi_selesai_kemarin(): void
    {
        $pelanggan = Pelanggan::create(['nama_pelanggan' => 'Pelanggan 3 Transaksi', 'kode_pelanggan' => 'PEL-TP-002', 'is_active' => true]);
        $kemarin = now()->subDay()->toDateString();
        $this->insertOrder($pelanggan, 15000, 'selesai', $kemarin, 'TP-002-A');
        $this->insertOrder($pelanggan, 25000, 'selesai', $kemarin, 'TP-002-B');
        $this->insertOrder($pelanggan, 10000, 'selesai', now()->subDays(3)->toDateString(), 'TP-002-C');

        $this->assertEquals(50000, $pelanggan->fresh()->total_pembelian);
    }

    public function test_accessor_skip_order_pending_hari_ini_hanya_hitung_selesai_kemarin(): void
    {
        $pelanggan = Pelanggan::create(['nama_pelanggan' => 'Pelanggan Pending Hari Ini', 'kode_pelanggan' => 'PEL-TP-003', 'is_active' => true]);
        $this->insertOrder($pelanggan, 20000, 'selesai', now()->subDay()->toDateString(), 'TP-003-KEMARIN');
        $this->insertOrder($pelanggan, 99999, 'pending', now()->toDateString(), 'TP-003-PENDING-HARIINI');
        $this->insertOrder($pelanggan, 88888, 'selesai', now()->toDateString(), 'TP-003-SELESAI-HARIINI');

        // Cuma order kemarin yang status selesai DAN tanggal < hari ini yang dihitung.
        $this->assertEquals(20000, $pelanggan->fresh()->total_pembelian);
    }

    public function test_accessor_skip_order_dibatalkan(): void
    {
        $pelanggan = Pelanggan::create(['nama_pelanggan' => 'Pelanggan Dibatalkan', 'kode_pelanggan' => 'PEL-TP-004', 'is_active' => true]);
        $this->insertOrder($pelanggan, 30000, 'selesai', now()->subDay()->toDateString(), 'TP-004-OK');
        $this->insertOrder($pelanggan, 50000, 'dibatalkan', now()->subDay()->toDateString(), 'TP-004-BATAL');

        $this->assertEquals(30000, $pelanggan->fresh()->total_pembelian);
    }

    // ===== Rendering halaman detail pelanggan =====

    public function test_widget_total_pembelian_tampil_dengan_nilai_benar(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $pelanggan = Pelanggan::create(['nama_pelanggan' => 'Pelanggan Widget Test', 'kode_pelanggan' => 'PEL-TP-005', 'is_active' => true]);
        $this->insertOrder($pelanggan, 123000, 'selesai', now()->subDay()->toDateString(), 'TP-005-A');

        $response = $this->actingAs($admin)->get(route('pelanggan.show', $pelanggan));

        $response->assertOk();
        $response->assertSee('Total Pembelian');
        $response->assertSee('Rp 123.000', false);
    }

    public function test_widget_total_kg_giling_sudah_tidak_ada(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $pelanggan = Pelanggan::create(['nama_pelanggan' => 'Pelanggan Cek Kg Hilang', 'kode_pelanggan' => 'PEL-TP-006', 'is_active' => true]);

        $response = $this->actingAs($admin)->get(route('pelanggan.show', $pelanggan));

        $response->assertOk();
        $response->assertDontSee('Total Kg Giling');
        $response->assertDontSee('Total Belanja');
    }

    public function test_pelanggan_tanpa_transaksi_tampil_rp_0(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $pelanggan = Pelanggan::create(['nama_pelanggan' => 'Pelanggan Belum Transaksi', 'kode_pelanggan' => 'PEL-TP-007', 'is_active' => true]);

        $response = $this->actingAs($admin)->get(route('pelanggan.show', $pelanggan));

        $response->assertOk();
        $response->assertSee('Rp 0', false);
    }

    // ===== Bonus fix: section Program Loyalty tidak hardcode kg =====

    public function test_section_loyalty_di_halaman_pelanggan_tidak_hardcode_berat_gilingan_utk_basis_rp(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $pelanggan = Pelanggan::create(['nama_pelanggan' => 'Pelanggan Loyalty Rp', 'kode_pelanggan' => 'PEL-TP-008', 'is_active' => true]);
        LoyaltyProgram::create([
            'nama' => 'Loyalti Rp Pelanggan Test', 'tipe_program' => 'auto_track', 'target_qty_kg' => 100000,
            'satuan_qty' => 'Rp', 'sumber_data' => 'orders.total_bayar', 'tipe_item' => 'penjualan',
            'hadiah' => 'Voucher', 'berulang' => false, 'status' => 'aktif',
        ]);
        // Order tanpa total_bayar (0) supaya jumlah_order_tanpa_data > 0 dan trigger baris teks.
        $this->insertOrder($pelanggan, 0, 'selesai', now()->subDay()->toDateString(), 'TP-008-KOSONG');

        $response = $this->actingAs($admin)->get(route('pelanggan.show', $pelanggan));

        $response->assertOk();
        $response->assertDontSee('berat gilingan');
    }

    // ===== Regresi =====

    public function test_regresi_halaman_pelanggan_lain_masih_normal(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $pelanggan = Pelanggan::create(['nama_pelanggan' => 'Pelanggan Regresi', 'kode_pelanggan' => 'PEL-TP-009', 'is_active' => true]);

        $response = $this->actingAs($admin)->get(route('pelanggan.show', $pelanggan));

        $response->assertOk();
        $response->assertSee('Total Order');
        $response->assertSee('Order Terakhir');
        $response->assertSee('Rata-rata / Order');
    }
}
