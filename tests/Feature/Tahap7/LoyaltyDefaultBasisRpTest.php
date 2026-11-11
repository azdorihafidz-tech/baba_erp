<?php

namespace Tests\Feature\Tahap7;

use App\Models\LoyaltyProgram;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Bug fix 2026-09-21 (laporan Owner): form "Tambah Program Loyalty" —
 * dropdown "Tipe Program" (Auto-Track) berlabel "kumulatif kg giling",
 * dan basis default sistem (fallback saat `sumber_data` tidak dikirim,
 * DB column default) masih 'orders.berat_daging_kg' warisan Berkah Mulyo
 * — padahal D'mentai (retail dimsum) TIDAK PERNAH pakai basis kg.
 *
 * Fix (murni sinkronisasi wording + default, TIDAK menghapus opsi kg
 * demi backward compat kalau ada program lama pakai basis itu):
 * - Migration `2026_09_21_900001` — DEFAULT kolom `sumber_data`/`satuan_qty`
 *   di DB diubah dari kg → Rp (orders.total_bayar / 'Rp').
 * - `LoyaltyProgramController::store()` — fallback PHP-level ikut diubah
 *   ke 'orders.total_bayar'.
 * - View create/edit — label & helper text tipe_program tidak lagi
 *   hardcode "kg giling"/`orders.berat_daging_kg`.
 * - View show — info alert & tooltip "Order Tanpa Data" dinamis sesuai
 *   `sumber_data` program yang sedang dilihat, bukan hardcode kg.
 */
class LoyaltyDefaultBasisRpTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_kolom_db_default_sumber_data_dan_satuan_qty_sekarang_rp(): void
    {
        $sumberData = \DB::selectOne("SHOW COLUMNS FROM loyalty_programs WHERE Field='sumber_data'")->Default;
        $satuanQty = \DB::selectOne("SHOW COLUMNS FROM loyalty_programs WHERE Field='satuan_qty'")->Default;

        $this->assertEquals('orders.total_bayar', $sumberData);
        $this->assertEquals('Rp', $satuanQty);
    }

    public function test_submit_tanpa_sumber_data_fallback_ke_rp_bukan_kg(): void
    {
        $admin = $this->buatUser('owner');

        $response = $this->actingAs($admin)->post(route('loyalty-program.store'), [
            'nama' => 'Program Fallback Test',
            'tipe_program' => 'auto_track',
            'target_qty_kg' => '100000',
            'tipe_item' => 'penjualan',
            // sumber_data SENGAJA tidak dikirim -- test fallback backend.
            'hadiah' => 'Voucher Rp 10.000',
            'status' => 'aktif',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('loyalty_programs', [
            'nama' => 'Program Fallback Test',
            'sumber_data' => 'orders.total_bayar',
            'satuan_qty' => 'Rp',
        ]);
    }

    public function test_form_create_label_tidak_lagi_sebut_kg_giling(): void
    {
        $admin = $this->buatUser('owner');

        $response = $this->actingAs($admin)->get(route('loyalty-program.create'));

        $response->assertOk();
        $response->assertDontSee('kumulatif kg giling');
        $response->assertDontSee('orders.berat_daging_kg</code>', false);
        $response->assertSee('kumulatif otomatis dari transaksi pelanggan');
        $response->assertSee('Total Belanja (Rp)');
    }

    public function test_form_edit_label_tidak_lagi_sebut_kg_giling(): void
    {
        $admin = $this->buatUser('owner');
        $program = LoyaltyProgram::create([
            'nama' => 'Program Edit Label Test', 'tipe_program' => 'auto_track',
            'target_qty_kg' => 200000, 'satuan_qty' => 'Rp', 'sumber_data' => 'orders.total_bayar',
            'tipe_item' => 'penjualan', 'hadiah' => 'Voucher', 'berulang' => false, 'status' => 'aktif',
        ]);

        $response = $this->actingAs($admin)->get(route('loyalty-program.edit', $program));

        $response->assertOk();
        $response->assertDontSee('kumulatif kg giling');
        $response->assertDontSee('orders.berat_daging_kg</code>', false);
    }

    public function test_show_basis_rp_tidak_tampilkan_teks_berat_gilingan(): void
    {
        $admin = $this->buatUser('owner');
        $program = LoyaltyProgram::create([
            'nama' => 'Program Show Rp Test', 'tipe_program' => 'auto_track',
            'target_qty_kg' => 300000, 'satuan_qty' => 'Rp', 'sumber_data' => 'orders.total_bayar',
            'tipe_item' => 'penjualan', 'hadiah' => 'Voucher', 'berulang' => false, 'status' => 'aktif',
        ]);

        $response = $this->actingAs($admin)->get(route('loyalty-program.show', $program));

        $response->assertOk();
        $response->assertSee('total belanja (orders.total_bayar)');
        $response->assertDontSee('berat gilingan-nya dianggap');
    }

    public function test_show_basis_kg_legacy_masih_tampilkan_teks_kg_apa_adanya(): void
    {
        // Regresi: program LAMA yang genuinely masih pakai basis kg (kalau
        // ada) tetap menampilkan teks yang benar utk basis itu -- opsi kg
        // TIDAK dihapus, cuma bukan default lagi.
        $admin = $this->buatUser('owner');
        $program = LoyaltyProgram::create([
            'nama' => 'Program Show Kg Legacy Test', 'tipe_program' => 'auto_track',
            'target_qty_kg' => 500, 'satuan_qty' => 'kg', 'sumber_data' => 'orders.berat_daging_kg',
            'tipe_item' => 'jasa_giling', 'hadiah' => 'Beras 5kg', 'berulang' => false, 'status' => 'aktif',
        ]);

        $response = $this->actingAs($admin)->get(route('loyalty-program.show', $program));

        $response->assertOk();
        $response->assertSee('berat gilingan (orders.berat_daging_kg, legacy)');
    }

    // ===== Regresi =====

    public function test_regresi_submit_eksplisit_basis_kg_masih_bisa_disimpan(): void
    {
        $admin = $this->buatUser('owner');

        $response = $this->actingAs($admin)->post(route('loyalty-program.store'), [
            'nama' => 'Program Kg Eksplisit Test',
            'tipe_program' => 'auto_track',
            'target_qty_kg' => '500',
            'tipe_item' => 'jasa_giling',
            'sumber_data' => 'orders.berat_daging_kg',
            'hadiah' => 'Beras 5kg',
            'status' => 'aktif',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('loyalty_programs', [
            'nama' => 'Program Kg Eksplisit Test',
            'sumber_data' => 'orders.berat_daging_kg',
            'satuan_qty' => 'kg',
        ]);
    }

    public function test_regresi_submit_basis_total_bayar_eksplisit_tetap_normal(): void
    {
        $admin = $this->buatUser('owner');

        $response = $this->actingAs($admin)->post(route('loyalty-program.store'), [
            'nama' => 'Program Rp Eksplisit Test',
            'tipe_program' => 'auto_track',
            'target_qty_kg' => '250000',
            'tipe_item' => 'penjualan',
            'sumber_data' => 'orders.total_bayar',
            'hadiah' => 'Voucher Rp 25.000',
            'status' => 'aktif',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('loyalty_programs', [
            'nama' => 'Program Rp Eksplisit Test',
            'sumber_data' => 'orders.total_bayar',
            'satuan_qty' => 'Rp',
        ]);
    }
}
