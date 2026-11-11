<?php

namespace Tests\Feature\Tahap25\Concerns;

use App\Models\Cabang;
use App\Models\User;

/**
 * Tahap 2.5 D'mentai — helper bareng untuk Feature test HTTP (actingAs).
 * UserSeeder bawaan project SENGAJA TIDAK dipakai di sini karena ditemukan
 * error pre-existing (cabang_id kosong) saat testing sesi ini yang di luar
 * scope Tahap 2.5 — dibuat user minimal langsung via factory supaya tidak
 * terganggu bug itu.
 */
trait CreatesTahap25Users
{
    protected function buatUser(string $role, ?int $cabangId = null): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);

        if ($cabangId) {
            $user->cabangs()->attach($cabangId, ['is_default' => true]);
        }

        return $user;
    }

    protected function cabangPertama(): Cabang
    {
        return Cabang::orderBy('id')->firstOrFail();
    }

    protected function cabangKedua(): Cabang
    {
        return Cabang::orderBy('id')->skip(1)->firstOrFail();
    }
}
