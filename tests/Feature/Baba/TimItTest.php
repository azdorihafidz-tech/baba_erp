<?php

namespace Tests\Feature\Baba;

use App\Models\Cabang;
use App\Models\User;
use App\Services\StokOpnameService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** Porting Dimsum 4.53 Tahap A ke BABA — akun Tim IT (Teknisi) & Aktivitas Tim IT. */
class TimItTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_teknisi_akses_penuh_termasuk_halaman_khusus_owner(): void
    {
        $t = $this->buatUser('teknisi');
        $this->assertTrue($t->aksesPenuh());
        $this->assertTrue($t->hasPermission('stok_opname.setujui'));
        $this->assertTrue($t->can('cabang.delete'));
        $this->assertTrue($t->canAccessAllBranches());
        $this->actingAs($t)->get(route('role.index'))->assertOk();
        $this->actingAs($t)->get(route('pengaturan.umum'))->assertOk();
        $this->actingAs($t)->get(route('pengaturan.jadwal'))->assertOk(); // BABA Tahap 8 (role:owner)
        $this->actingAs($t)->get(route('tim-it.index'))->assertOk();
        $this->actingAs($t)->followingRedirects()->get(route('dashboard'))->assertOk()->assertSee(route('tim-it.index'), false)->assertSee(route('pengaturan.jadwal'), false);
    }

    public function test_teknisi_tersembunyi_dari_pelanggan(): void
    {
        $t = $this->buatUser('teknisi');
        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->get(route('user.index'))->assertOk()->assertDontSee($t->email);
        $this->actingAs($owner)->get(route('user.edit', $t))->assertNotFound();
        $this->actingAs($owner)->patch(route('user.toggle-aktif', $t))->assertNotFound();
        $this->actingAs($owner)->get(route('role.index'))->assertOk()->assertDontSee('Tim IT</');
        $this->assertNotContains('teknisi', \App\Support\Roles::values());
        $this->actingAs($owner)->post(route('user.store'), [
            'name' => 'Palsu', 'email' => 'palsu' . uniqid() . '@contoh.id', 'role' => 'teknisi',
            'password' => 'RahasiaSekali123', 'password_confirmation' => 'RahasiaSekali123',
        ])->assertSessionHasErrors('role');
        $this->actingAs($owner)->get(route('tim-it.index'))->assertForbidden();
        // pilihan kepala lokasi (Cabang & Gudang)
        $t->update(['name' => 'Teknisi Rahasia ' . uniqid()]);
        $this->actingAs($owner)->get(route('cabang.create'))->assertOk()->assertDontSee($t->name);
    }

    public function test_toko_mitra_tim_it_hanya_melihat_dan_tidak_menerima_lonceng(): void
    {
        $t = $this->buatUser('teknisi');
        $mitra = Cabang::create(['nama_cabang' => 'Mitra Uji ' . uniqid(), 'kode_cabang' => 'MU' . rand(10000, 99999), 'tipe' => 'cabang',
            'is_active' => true, 'jenis_toko' => 'kemitraan']);
        $svc = app(StokOpnameService::class);
        $this->assertFalse($svc->bolehUbahLokasi($t, $mitra), 'Tim IT seperti Owner BABA: tidak mengerjakan opname toko mitra');
        $this->assertTrue($svc->bolehUbahLokasi($t, $this->cabangPertama()));
        // penerima lonceng pengguna (pola pesanan online / retur / opname) tidak memuat Tim IT
        $penerima = User::pelanggan()->where('is_active', true)->pluck('id')->all();
        $this->assertNotContains($t->id, $penerima);
    }

    public function test_kelola_akun_tim_it(): void
    {
        $t = $this->buatUser('teknisi');
        $this->actingAs($t)->post(route('tim-it.store'), ['name' => 'IT Dua', 'email' => 'it2' . uniqid() . '@contoh.id',
            'password' => 'pendek1', 'password_confirmation' => 'pendek1'])->assertSessionHasErrors('password');
        $email = 'it2' . uniqid() . '@contoh.id';
        $this->actingAs($t)->post(route('tim-it.store'), ['name' => 'IT Dua', 'email' => $email,
            'password' => 'KataSandiPanjang12', 'password_confirmation' => 'KataSandiPanjang12'])->assertSessionHas('success');
        $baru = User::where('email', $email)->firstOrFail();
        $this->assertTrue($baru->isTeknisi());

        $this->actingAs($t)->post(route('tim-it.toggle', $t))->assertSessionHas('error');
        $this->actingAs($t)->post(route('tim-it.toggle', $baru))->assertSessionHas('success');
        $this->assertFalse($baru->fresh()->is_active);
        $this->actingAs($t)->post(route('tim-it.toggle', $this->buatUser('kasir')))->assertNotFound();
    }

    public function test_aktivitas_tim_it_tercatat_dan_hanya_tim_it_yang_melihat(): void
    {
        $t = $this->buatUser('teknisi');
        $mulai = DB::table('activity_log')->where('log_name', 'TimIT')->count();
        $this->actingAs($t)->post(route('tim-it.store'), ['name' => 'IT Tiga', 'email' => 'it3' . uniqid() . '@contoh.id',
            'password' => 'KataSandiPanjang12', 'password_confirmation' => 'KataSandiPanjang12']);
        $tindakan = DB::table('activity_log')->where('log_name', 'TimIT')->where('causer_id', $t->id)->where('description', 'like', 'Tindakan:%')->latest('id')->first();
        $this->assertNotNull($tindakan);
        $this->assertStringNotContainsString('KataSandiPanjang12', (string) $tindakan->properties, 'isi formulir tidak disimpan');
        $this->assertGreaterThan($mulai, DB::table('activity_log')->where('log_name', 'TimIT')->count());

        \Illuminate\Support\Facades\Event::dispatch(new \Illuminate\Auth\Events\Login('web', $t, false));
        $this->assertTrue(DB::table('activity_log')->where('log_name', 'TimIT')->where('causer_id', $t->id)->where('description', 'Login')->exists());

        $this->actingAs($t)->get(route('aktivitas-tim-it.index'))->assertOk()->assertSee($t->name);
        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->get(route('aktivitas-tim-it.index'))->assertForbidden();
        $this->actingAs($this->buatUser('admin_pusat'))->get(route('aktivitas-tim-it.index'))->assertForbidden();
        $this->actingAs($owner)->followingRedirects()->get(route('dashboard'))->assertOk()->assertSee(route('pengaturan.jadwal'), false)->assertDontSee(route('aktivitas-tim-it.index'), false)->assertDontSee(route('tim-it.index'), false);

        $this->actingAs($owner)->get(route('audit-log.index', ['log_name' => 'TimIT']))->assertOk()->assertDontSee('Tindakan: tim-it.store');
        $this->actingAs($owner)->get(route('audit-log.index'))->assertOk()->assertDontSee('it3');
        $this->actingAs($owner)->get(route('audit-log.show', $tindakan->id))->assertNotFound();
        $this->actingAs($t)->get(route('audit-log.show', $tindakan->id))->assertOk();
        $this->actingAs($t)->get(route('audit-log.index', ['log_name' => 'TimIT']))->assertOk()->assertSee('Tindakan: tim-it.store');
        $jumlah = DB::table('activity_log')->where('log_name', 'TimIT')->count();
        $this->actingAs($this->buatUser('owner'))->post(route('pengaturan.umum.update'), ['nama_perusahaan' => 'BABA PARFUME Indonesia']);
        $this->assertSame($jumlah, DB::table('activity_log')->where('log_name', 'TimIT')->count());
    }

    public function test_perintah_tim_it_buat_dari_file_env(): void
    {
        $file = storage_path('framework/testing-env-timit');
        @mkdir(dirname($file), 0775, true);
        $email = 'timit' . uniqid() . '@contoh.id';
        try {
            file_put_contents($file, "TEKNISI_NAMA=\"Tim IT Uji\"\nTEKNISI_EMAIL={$email}\nTEKNISI_PASSWORD=pendek\n");
            $this->assertSame(1, Artisan::call('tim-it:buat', ['--file' => $file]));
            $this->assertFalse(User::where('email', $email)->exists());

            file_put_contents($file, "TEKNISI_NAMA=\"Tim IT Uji\"\nTEKNISI_EMAIL={$email}\nTEKNISI_PASSWORD=SandiTimIt2026Aman\n");
            $this->assertSame(0, Artisan::call('tim-it:buat', ['--file' => $file]));
            $this->assertStringNotContainsString('SandiTimIt2026Aman', Artisan::output(), 'kata sandi tidak dicetak');
            $u = User::where('email', $email)->firstOrFail();
            $this->assertTrue($u->isTeknisi());
            $this->assertTrue(\Illuminate\Support\Facades\Hash::check('SandiTimIt2026Aman', $u->password));
            // jalan kedua: tidak membuat dobel
            $this->assertSame(0, Artisan::call('tim-it:buat', ['--file' => $file]));
            $this->assertSame(1, User::where('email', $email)->count());
            // email milik pengguna BABA ditolak
            $kasir = $this->buatUser('kasir');
            file_put_contents($file, "TEKNISI_NAMA=X\nTEKNISI_EMAIL={$kasir->email}\nTEKNISI_PASSWORD=SandiTimIt2026Aman\n");
            $this->assertSame(1, Artisan::call('tim-it:buat', ['--file' => $file]));
            $this->assertFalse($kasir->fresh()->isTeknisi());
        } finally {
            @unlink($file);
        }
    }
}
