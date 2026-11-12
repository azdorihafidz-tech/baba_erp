<?php

namespace Tests\Feature\Baba;

use App\Models\Cabang;
use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** ERP BABA Tahap 5 — akun login karyawan: password awal acak (bukan "password123"), ditampilkan sekali, nama di pesan di-escape. */
class AkunKaryawanTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function data(Cabang $c, array $tambah = []): array
    {
        return array_merge(['nama_lengkap' => 'Budi <b>Uji</b>', 'nik' => (string) rand(1000000000000000, 9999999999999999), 'jenis_kelamin' => 'L',
            'cabang_id' => $c->id, 'jabatan' => 'Staf Gudang', 'tipe_karyawan' => 'tetap', 'tanggal_masuk' => '2026-01-05', 'gaji_pokok' => 3000000], $tambah);
    }

    public function test_akun_baru_password_acak_dan_pesan_aman(): void
    {
        $this->actingAs($this->buatUser('owner'));
        $c = Cabang::create(['nama_cabang' => 'Gudang AK ' . uniqid(), 'kode_cabang' => 'AK' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        $email = 'budi.' . uniqid() . '@babaparfume.local';
        $r = $this->post(route('karyawan.store'), $this->data($c, ['buat_akun_login' => '1', 'user_email' => $email, 'user_role' => 'operator_produksi']));
        $pesan = (string) session('success');
        $this->assertMatchesRegularExpression('/password awal: <code>([A-Za-z2-9]{10})<\/code>/', $pesan);
        preg_match('/<code>([^<]+)<\/code>/', $pesan, $m);
        $u = User::where('email', $email)->firstOrFail();
        $this->assertTrue(Hash::check($m[1], $u->password));
        $this->assertFalse(Hash::check('password123', $u->password));
        $this->assertStringContainsString('Budi &lt;b&gt;Uji&lt;/b&gt;', $pesan, 'nama di-escape di pesan HTML');
        $this->assertSame($u->id, (int) Karyawan::where('cabang_id', $c->id)->value('user_id'));

        // dua karyawan baru → password berbeda
        $email2 = 'budi2.' . uniqid() . '@babaparfume.local';
        $this->post(route('karyawan.store'), $this->data($c, ['buat_akun_login' => '1', 'user_email' => $email2, 'user_role' => 'kasir']));
        preg_match('/<code>([^<]+)<\/code>/', (string) session('success'), $m2);
        $this->assertNotSame($m[1], $m2[1]);

        // login dengan password awal berhasil
        auth()->logout();
        $this->post('/login', ['email' => $email, 'password' => $m[1]]);
        $this->assertAuthenticatedAs($u);
    }
}
