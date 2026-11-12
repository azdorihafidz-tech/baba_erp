<?php

namespace Tests\Feature\Baba;

use App\Models\Cabang;
use App\Models\Karyawan;
use App\Models\Kas;
use App\Models\Penggajian;
use App\Models\TransaksiKeuangan;
use App\Services\KasBawaanService;
use App\Services\LabaRugiFormalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** ERP BABA Tahap 5 — slip gaji dibayar → pengeluaran Gaji (6-1101) dari kas lokasi, sekali saja; halaman slip. */
class JurnalGajiTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $gudang;
    private Karyawan $karyawan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->buatUser('owner'));
        $this->gudang = Cabang::create(['nama_cabang' => 'Gudang GJ ' . uniqid(), 'kode_cabang' => 'GJ' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        $this->karyawan = Karyawan::create(['cabang_id' => $this->gudang->id, 'nik' => (string) rand(1000000000, 9999999999), 'nama_lengkap' => 'Rina Uji Gaji',
            'jenis_kelamin' => 'P', 'jabatan' => 'Admin Gudang', 'tanggal_masuk' => today()->subYear(), 'gaji_pokok' => 3000000]);
    }

    private function slip(float $total, string $status = 'disetujui', int $mundur = 0): Penggajian
    {
        return Penggajian::create(['karyawan_id' => $this->karyawan->id, 'cabang_id' => $this->gudang->id, 'periode' => today()->subMonthsNoOverflow($mundur)->format('Y-m'),
            'gaji_pokok' => $total, 'total_gaji' => $total, 'status' => $status]);
    }

    public function test_bayar_mencatat_beban_gaji_sekali_dari_kas_transfer(): void
    {
        $s = $this->slip(3250000);
        $this->get(route('penggajian.show', $s))->assertOk()->assertSee('Tandai Dibayar')->assertSee('dibuat otomatis');
        $this->post(route('penggajian.bayar', $s))->assertSessionHas('success');
        $kas = app(KasBawaanService::class)->pastikan($this->gudang)['transfer']->fresh();
        $this->assertSame(-3250000.0, (float) $kas->saldo_sekarang);
        $t = TransaksiKeuangan::where('referensi_type', 'penggajian')->where('referensi_id', $s->id)->get();
        $this->assertCount(1, $t);
        $this->assertSame(['pengeluaran', 'gaji', 3250000.0, $kas->id], [$t[0]->tipe->value, $t[0]->kategori->value, (float) $t[0]->jumlah, (int) $t[0]->kas_id]);
        $this->assertSame('dibayar', $s->fresh()->status);
        $lr = app(LabaRugiFormalService::class)->hitungLabaRugi(today(), today(), $this->gudang->id);
        $this->assertSame(3250000.0, (float) collect($lr['beban_operasional']['detail'])->firstWhere('kode', '6-1101')['jumlah']);
        // dibayar dua kali → ditolak, tidak tercatat ulang
        $this->post(route('penggajian.bayar', $s))->assertSessionHas('error');
        $this->assertSame(1, TransaksiKeuangan::where('referensi_type', 'penggajian')->where('referensi_id', $s->id)->count());
        $this->get(route('penggajian.show', $s))->assertOk()->assertSee('dicatat sebagai pengeluaran Gaji');
    }

    public function test_pilih_kas_tunai_dan_tolak_kas_lokasi_lain(): void
    {
        $kas = app(KasBawaanService::class)->pastikan($this->gudang);
        $lain = Cabang::create(['nama_cabang' => 'Toko GJ ' . uniqid(), 'kode_cabang' => 'TG' . rand(10000, 99999), 'tipe' => 'cabang', 'is_active' => true]);
        $kasLain = app(KasBawaanService::class)->pastikan($lain)['tunai'];
        $s = $this->slip(1000000);
        $this->post(route('penggajian.bayar', $s), ['kas_id' => $kasLain->id])->assertSessionHas('error');
        $this->assertSame('disetujui', $s->fresh()->status);
        $this->assertSame(0.0, (float) $kasLain->fresh()->saldo_sekarang);
        $this->post(route('penggajian.bayar', $s), ['kas_id' => $kas['tunai']->id])->assertSessionHas('success');
        $this->assertSame(-1000000.0, (float) $kas['tunai']->fresh()->saldo_sekarang);
        $this->assertSame(0.0, (float) $kas['transfer']->fresh()->saldo_sekarang);
    }

    public function test_gaji_nol_hanya_status_dan_izin(): void
    {
        $s = $this->slip(0, 'draft');
        $this->post(route('penggajian.bayar', $s))->assertSessionHas('success');
        $this->assertSame('dibayar', $s->fresh()->status);
        $this->assertSame(0, TransaksiKeuangan::where('referensi_type', 'penggajian')->where('referensi_id', $s->id)->count());
        $s2 = $this->slip(500000, 'disetujui', 1);
        $this->actingAs($this->buatUser('kasir', $this->gudang->id))->post(route('penggajian.bayar', $s2))->assertForbidden();
        $this->assertSame(0, Kas::where('cabang_id', $this->gudang->id)->where('saldo_sekarang', '!=', 0)->count());
    }
}
