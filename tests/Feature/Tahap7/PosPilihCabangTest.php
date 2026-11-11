<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Porting dari erp-coffeeshop (c72a84c + fe81503), 2026-11-03: POS dibuka saat
 * cabang aktif = "Semua Cabang" oleh user tanpa cabang default → 500
 * (Item::tersediaDiCabang(null)). Sekarang POS meminta pilih cabang dulu.
 * Beda dgn coffeeshop: Gudang Pusat IKUT jadi pilihan (POS gudang melayani
 * PO dari cabang, CLAUDE.md 4.38 Tahap C).
 */
class PosPilihCabangTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_pos_mode_semua_cabang_menampilkan_pilihan_cabang_bukan_error(): void
    {
        $owner = $this->buatUser('owner');
        $outlet = Cabang::where('tipe', 'cabang')->where('is_active', true)->firstOrFail();
        $gudang = Cabang::where('tipe', 'gudang_pusat')->where('is_active', true)->firstOrFail();

        $this->actingAs($owner)->withSession(['active_cabang_id' => null])
            ->get(route('penjualan.pos'))->assertOk()
            ->assertSee('Pilih cabang untuk berjualan')
            ->assertSee($outlet->nama_cabang)
            ->assertViewHas('pilihanCabang', fn ($c) => $c->contains('id', $outlet->id)
                && $c->contains('id', $gudang->id)
                && $c->every(fn ($x) => $x->is_active));
    }

    public function test_cabang_nonaktif_tidak_jadi_pilihan(): void
    {
        $owner = $this->buatUser('owner');
        $outlet = Cabang::where('tipe', 'cabang')->where('is_active', true)->firstOrFail();
        $outlet->update(['is_active' => false]);

        $this->actingAs($owner)->withSession(['active_cabang_id' => null])
            ->get(route('penjualan.pos'))->assertOk()
            ->assertViewHas('pilihanCabang', fn ($c) => ! $c->contains('id', $outlet->id));
    }

    public function test_owner_dengan_cabang_default_tetap_seperti_sebelumnya(): void
    {
        $outlet = Cabang::where('tipe', 'cabang')->where('is_active', true)->firstOrFail();
        $owner = $this->buatUser('owner', $outlet->id);

        // Perilaku lama dipertahankan: "Semua Cabang" + punya default → POS cabang default
        $this->actingAs($owner)->withSession(['active_cabang_id' => null])
            ->get(route('penjualan.pos'))->assertOk()
            ->assertDontSee('Pilih cabang untuk berjualan')
            ->assertViewHas('cabangId', $outlet->id);
    }

    public function test_pilih_cabang_lalu_pos_terbuka_untuk_cabang_itu(): void
    {
        $owner = $this->buatUser('owner');
        $outlet = Cabang::where('tipe', 'cabang')->where('is_active', true)->firstOrFail();

        $this->actingAs($owner)->withSession(['active_cabang_id' => null])
            ->from(route('penjualan.pos'))
            ->post(route('cabang.switch'), ['cabang_id' => $outlet->id])
            ->assertRedirect(route('penjualan.pos'));

        $this->actingAs($owner)->get(route('penjualan.pos'))->assertOk()
            ->assertDontSee('Pilih cabang untuk berjualan')
            ->assertViewHas('cabangId', $outlet->id);
    }

    private function tigaOutlet(): array
    {
        $outlet = Cabang::where('tipe', 'cabang')->where('is_active', true)->orderBy('id')->take(3)->get();
        $this->assertCount(3, $outlet, 'Butuh 3 outlet aktif di database test.');

        return $outlet->all();
    }

    public function test_kasir_dengan_cabang_default_langsung_masuk_pos_cabangnya(): void
    {
        [$a] = $this->tigaOutlet();
        $kasir = $this->buatUser('kasir', $a->id);

        $this->actingAs($kasir)->get(route('penjualan.pos'))->assertOk()
            ->assertDontSee('Pilih cabang untuk berjualan')
            ->assertViewHas('cabangId', $a->id);
    }

    public function test_kasir_tidak_bisa_pindah_ke_cabang_lain_atau_semua_cabang(): void
    {
        [$a, , $c] = $this->tigaOutlet();
        $kasir = $this->buatUser('kasir', $a->id);

        $this->actingAs($kasir)->post(route('cabang.switch'), ['cabang_id' => $c->id])->assertForbidden();
        $this->assertNotEquals($c->id, session('active_cabang_id'));

        $this->actingAs($kasir)->post(route('cabang.switch'), ['cabang_id' => null])->assertForbidden();

        $this->actingAs($kasir)->get(route('penjualan.pos'))->assertOk()->assertViewHas('cabangId', $a->id);
    }

    public function test_simpan_bill_tanpa_cabang_ditolak_dengan_pesan(): void
    {
        $owner = $this->buatUser('owner');

        $this->actingAs($owner)->withSession(['active_cabang_id' => null])
            ->postJson(route('penjualan.simpan-bill'), ['items' => [['nama_item' => 'X', 'qty' => 1, 'harga_satuan' => 1000]]])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Pilih cabang dulu (cabang aktif masih Semua Cabang), lalu ulangi transaksi.');
    }

    public function test_bayar_tanpa_cabang_ditolak_dengan_pesan(): void
    {
        $owner = $this->buatUser('owner');

        $this->actingAs($owner)->withSession(['active_cabang_id' => null])
            ->from(route('penjualan.pos'))
            ->post(route('penjualan.store'), [
                'tipe_transaksi' => 'takeaway',
                'payments'       => [['metode' => 'tunai', 'jumlah' => 1000]],
                'jumlah_bayar'   => 1000,
                'items'          => [['nama_item' => 'X', 'qty' => 1, 'harga_satuan' => 1000]],
            ])
            ->assertRedirect(route('penjualan.pos'))
            ->assertSessionHas('error', 'Pilih cabang dulu (cabang aktif masih Semua Cabang), lalu ulangi transaksi.');

        $this->assertDatabaseMissing('orders', ['kasir_id' => $owner->id]);
    }
}
