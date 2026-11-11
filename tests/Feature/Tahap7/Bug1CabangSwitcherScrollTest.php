<?php

namespace Tests\Feature\Tahap7;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap 7 D'mentai — Bug 1 fix: dropdown "Pilih Cabang Aktif" harus bisa
 * discroll (max-height + overflow-y:auto) supaya outlet ke-3 dst tetap bisa
 * diakses berapapun jumlah outlet ke depan (CLAUDE.md 1.4 — fleksibilitas
 * jumlah outlet).
 */
class Bug1CabangSwitcherScrollTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_dropdown_cabang_switcher_punya_class_scroll(): void
    {
        $owner = $this->buatUser('owner');

        $response = $this->actingAs($owner)->get('/dashboard/pusat');

        $response->assertOk();
        $response->assertSee('cabang-switcher-menu', false);
        $response->assertSee('overflow-y: auto', false);
    }

    public function test_dropdown_menampilkan_semua_cabang_di_markup_tidak_terpotong_html(): void
    {
        // Verifikasi SEMUA cabang aktif genuinely ada di markup HTML (bukan
        // soal CSS doang) -- kalau markup sudah lengkap tapi CSS tidak bisa
        // discroll, user tetap tidak bisa akses outlet ke-3 dst walau
        // datanya ada. Test ini + test di atas saling melengkapi.
        $owner = $this->buatUser('owner');
        $semuaCabang = \App\Models\Cabang::aktif()->get();
        $this->assertGreaterThanOrEqual(3, $semuaCabang->count(), 'Data dev harus punya >=3 cabang aktif utk test ini bermakna.');

        $response = $this->actingAs($owner)->get('/dashboard/pusat');

        $response->assertOk();
        foreach ($semuaCabang as $cabang) {
            $response->assertSee(e($cabang->nama_cabang), false);
        }
    }

    /**
     * ROOT CAUSE ASLI Bug 1 (bukan cuma CSS): CabangMiddleware sebelum fix
     * filter $userCabangs ke $user->cabangs() (pivot cabang_user eksplisit)
     * BAHKAN untuk owner/admin_pusat -- dikonfirmasi data dev: Owner cuma
     * punya 3/6 pivot cabang. Fix: canAccessAllBranches() -> tampilkan
     * SEMUA cabang aktif. Test ini pakai user BARU tanpa pivot cabang_user
     * SAMA SEKALI, membuktikan fix bekerja independen dari data assignment.
     */
    public function test_owner_baru_tanpa_pivot_cabang_tetap_lihat_semua_cabang_di_switcher(): void
    {
        $owner = $this->buatUser('owner'); // buatUser TIDAK attach cabang_user pivot apapun
        $this->assertEquals(0, $owner->cabangs()->count(), 'Prasyarat test: owner ini genuinely tanpa pivot cabang_user.');

        $semuaCabang = \App\Models\Cabang::aktif()->get();

        $response = $this->actingAs($owner)->get('/dashboard/pusat');

        $response->assertOk();
        foreach ($semuaCabang as $cabang) {
            $response->assertSee(e($cabang->nama_cabang), false);
        }
    }

    public function test_manajer_cabang_tetap_dibatasi_cabang_yang_di_assign_bukan_semua(): void
    {
        // Regresi keamanan: role TANPA canAccessAllBranches() (mis. manajer
        // cabang) TIDAK BOLEH ikut kebagian "lihat semua cabang" dari fix ini.
        $cabangSatu = $this->cabangPertama();
        $manajer = $this->buatUser('manajer_cabang', $cabangSatu->id);
        $semuaCabang = \App\Models\Cabang::aktif()->get();
        $this->assertGreaterThan(1, $semuaCabang->count());

        $response = $this->actingAs($manajer)->withSession(['active_cabang_id' => $cabangSatu->id])->get('/dashboard');

        // manajer_cabang tanpa akses semua cabang -> hitung brp cabang yg dia punya pivot.
        $jumlahPivotManajer = $manajer->cabangs()->aktif()->count();
        $cabangLain = $semuaCabang->firstWhere('id', '!=', $cabangSatu->id);

        if ($cabangLain && $jumlahPivotManajer <= 1) {
            $response->assertDontSee(e($cabangLain->nama_cabang) . '</div>', false);
        }
    }
}
