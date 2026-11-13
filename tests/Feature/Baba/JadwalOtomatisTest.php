<?php

namespace Tests\Feature\Baba;

use App\Models\JadwalOtomatis;
use App\Services\DashboardStokOpnameService;
use App\Services\JadwalOtomatisService;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** ERP BABA Tahap 8 — Jadwal Otomatis: tugas di dalam proses (tanpa proc_open), saklar & jam, detak cron, cadangan, halaman Owner. */
class JadwalOtomatisTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private JadwalOtomatisService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(JadwalOtomatisService::class);
        JadwalOtomatis::query()->delete();
        $this->svc->pastikan();
    }

    /** @return array<string, CallbackEvent> */
    private function events(): array
    {
        Artisan::call('list'); // memuat routes/console.php
        $hasil = [];
        foreach (app(Schedule::class)->events() as $e) {
            $hasil[$e->description] = $e;
        }

        return $hasil;
    }

    public function test_bawaan_dan_semua_tugas_tanpa_proses_luar(): void
    {
        $this->assertSame(['ro_kirim' => true, 'pesanan_online' => false, 'recurring' => true, 'stok_opname_pengingat' => false, 'aset_depresiasi' => false, 'loyalty' => false],
            $this->svc->semua()->map(fn ($r) => $r->aktif)->all());
        $ev = $this->events();
        foreach (['jadwal:detak', 'jadwal:ro_kirim', 'jadwal:pesanan_online', 'jadwal:recurring', 'jadwal:stok_opname_pengingat', 'jadwal:aset_depresiasi', 'jadwal:loyalty'] as $nama) {
            $this->assertArrayHasKey($nama, $ev);
        }
        foreach (app(Schedule::class)->events() as $e) {
            $this->assertInstanceOf(CallbackEvent::class, $e, "tugas {$e->description} tidak boleh membuka proses baru (hosting tanpa proc_open)");
        }
        $this->assertSame(['* * * * *', '0 * * * *', '1 0 * * *', '0 8 * * *', '0 1 1 * *'], [$ev['jadwal:ro_kirim']->expression, $ev['jadwal:pesanan_online']->expression,
            $ev['jadwal:recurring']->expression, $ev['jadwal:stok_opname_pengingat']->expression, $ev['jadwal:aset_depresiasi']->expression]);
        // saklar: tugas mati tidak lolos filter, hidup lolos
        $this->assertFalse($ev['jadwal:pesanan_online']->filtersPass($this->app));
        $this->assertTrue($ev['jadwal:ro_kirim']->filtersPass($this->app));
        JadwalOtomatis::where('kode', 'pesanan_online')->update(['aktif' => true]);
        $this->assertTrue($ev['jadwal:pesanan_online']->filtersPass($this->app));
    }

    public function test_simpan_jam_hari_dan_tugas_wajib(): void
    {
        $ubah = $this->svc->simpan(['aktif' => ['stok_opname_pengingat' => 1, 'loyalty' => 1], 'jam' => ['stok_opname_pengingat' => '07:30', 'loyalty' => '04:15'], 'hari_sebelum' => 5]);
        $this->assertContains('Pengingat stok opname akhir bulan: dihidupkan', $ubah);
        $this->assertContains('Pengingat stok opname akhir bulan: mulai H-5', $ubah);
        $this->assertSame([true, '07:30', 5], [$this->svc->aktif('stok_opname_pengingat'), JadwalOtomatisService::pengaturanJadwal()['stok_opname_pengingat']['jam'], JadwalOtomatisService::hariPengingat()]);
        $this->assertSame('04:15', JadwalOtomatisService::pengaturanJadwal()['loyalty']['jam'], 'dibaca setiap schedule:run (proses baru tiap menit)');
        // H-5: 30 Nov → mulai 25 Nov
        $this->assertTrue(DashboardStokOpnameService::masaPengingat(Carbon::parse('2026-11-25')));
        $this->assertFalse(DashboardStokOpnameService::masaPengingat(Carbon::parse('2026-11-24')));
        // Kirim RO tidak bisa dimatikan
        $this->svc->simpan(['aktif' => []]);
        $this->assertTrue($this->svc->aktif('ro_kirim'));
        $this->assertFalse($this->svc->aktif('recurring'));
        try {
            $this->svc->simpan(['aktif' => [], 'jam' => ['loyalty' => '25:00']]);
            $this->fail('jam salah harus ditolak');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('format JJ:MM', $e->getMessage());
        }
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->simpan(['aktif' => [], 'hari_sebelum' => 15]);
    }

    public function test_jalankan_detak_cadangan_dan_baris_cron(): void
    {
        $h = $this->svc->jalankan('ro_kirim', 'manual');
        $this->assertSame('berhasil', $h['status']);
        $r = JadwalOtomatis::where('kode', 'ro_kirim')->first();
        $this->assertSame(['berhasil', 'manual'], [$r->terakhir_status, $r->terakhir_oleh]);
        $this->assertStringContainsString('RO terkirim', $r->terakhir_pesan);

        DB::table('pengaturan_umums')->update(['cron_terakhir' => now()->subMinutes(10)]);
        $this->assertFalse($this->svc->cronHidup());
        $this->svc->detak();
        $this->assertTrue($this->svc->cronHidup());

        $b = $this->svc->barisCron();
        $this->assertStringContainsString('artisan schedule:run', $b['jadwal']);
        $this->assertStringContainsString(config('database.connections.mysql.database'), $b['cadangan']);
        $this->assertStringContainsString('erp-$(date +\%Y\%m\%d-\%H\%M).sql.gz', $b['cadangan'], 'tanda % di crontab wajib di-escape');
        $this->assertStringContainsString('-mtime +14 -delete', $b['cadangan']);
        $this->assertStringContainsString('chmod 600', $b['kunci']);
    }

    public function test_halaman_owner_cadangan_unduh_dan_pita(): void
    {
        $owner = $this->buatUser('owner');
        $folder = $this->svc->folderCadangan();
        @mkdir($folder, 0775, true);
        $nama = 'erp-' . now()->format('Ymd-Hi') . '.sql.gz';
        file_put_contents($folder . DIRECTORY_SEPARATOR . $nama, random_bytes(4096));
        try {
            $this->assertTrue($this->svc->cadanganSehat());
            $this->actingAs($owner)->get(route('pengaturan.jadwal'))->assertOk()->assertSee('data-uji="tugas-ro_kirim"', false)->assertSee($nama === '' ? 'x' : 'Unduh')
                ->assertSee('selalu hidup');
            $this->actingAs($owner)->get(route('pengaturan.jadwal.cadangan', $nama))->assertOk()->assertHeader('content-type', 'application/gzip');
            $this->actingAs($owner)->get(route('pengaturan.jadwal.cadangan', '..%2F.env'))->assertNotFound();
            $this->actingAs($owner)->put(route('pengaturan.jadwal.simpan'), ['aktif' => ['loyalty' => 1], 'jam' => ['loyalty' => '03:00', 'stok_opname_pengingat' => '08:00'], 'hari_sebelum' => 3])
                ->assertSessionHas('success');
            $this->assertTrue($this->svc->aktif('loyalty'));
            $this->actingAs($owner)->post(route('pengaturan.jadwal.jalankan', 'ro_kirim'))->assertSessionHas('success');
            $this->actingAs($owner)->post(route('pengaturan.jadwal.jalankan', 'tidak-ada'))->assertNotFound();
            $this->actingAs($this->buatUser('admin_pusat'))->get(route('pengaturan.jadwal'))->assertForbidden();
            // pita merah hanya di hosting (production) & bila cron berhenti
            DB::table('pengaturan_umums')->update(['cron_terakhir' => null]);
            $this->actingAs($owner)->get(route('pengaturan.umum'))->assertDontSee('data-uji="pita-cron"', false);
            $this->app['env'] = 'production';
            $this->actingAs($owner)->get(route('pengaturan.umum'))->assertSee('data-uji="pita-cron"', false);
            $this->svc->detak();
            $this->actingAs($owner)->get(route('pengaturan.umum'))->assertDontSee('data-uji="pita-cron"', false)->assertDontSee('data-uji="pita-cadangan"', false);
        } finally {
            $this->app['env'] = 'testing';
            @unlink($folder . DIRECTORY_SEPARATOR . $nama);
        }
    }
}
