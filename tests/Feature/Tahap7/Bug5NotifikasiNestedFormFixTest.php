<?php

namespace Tests\Feature\Tahap7;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap 7 D'mentai — Bug 5 (bonus temuan audit sync Bug 4): form "Tandai
 * Semua Dibaca" nested di dalam form filter GET notifikasi.index. Root
 * cause SAMA PERSIS dengan Bug 3 (nested <form>), ditemukan saat scan
 * seluruh blade project, BUKAN salah satu dari 4 bug asli yang dilaporkan.
 */
class Bug5NotifikasiNestedFormFixTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_halaman_notifikasi_tidak_ada_nested_form(): void
    {
        $user = $this->buatUser('admin_pusat');

        $response = $this->actingAs($user)->get('/notifikasi');

        $response->assertOk();
        preg_match_all('/<form\b|<\/form>/i', $response->getContent(), $matches);
        $depth = 0; $maxDepth = 0;
        foreach ($matches[0] as $tag) {
            $depth += stripos($tag, '</form>') === 0 ? -1 : 1;
            $maxDepth = max($maxDepth, $depth);
        }
        $this->assertLessThanOrEqual(1, $maxDepth, 'Tidak boleh ada nested <form> di halaman notifikasi.');
    }

    public function test_tandai_semua_dibaca_beneran_memanggil_endpoint_post_bukan_reload_filter(): void
    {
        $user = $this->buatUser('admin_pusat');
        \Illuminate\Notifications\DatabaseNotification::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\PenjualanNotification',
            'notifiable_type' => get_class($user),
            'notifiable_id' => $user->id,
            'data' => ['message' => 'Test notif 2'],
            'read_at' => null,
        ]);

        $belumDibaca = $user->unreadNotifications()->count();
        $this->assertGreaterThan(0, $belumDibaca, 'Prasyarat: harus ada notifikasi belum dibaca.');

        $response = $this->actingAs($user)->post('/notifikasi/read-all');

        $response->assertRedirect();
        $this->assertEquals(0, $user->unreadNotifications()->count(), 'Semua notifikasi harus jadi "sudah dibaca" setelah panggil endpoint read-all.');
    }
}
