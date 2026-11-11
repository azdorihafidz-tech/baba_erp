<?php

namespace Tests\Feature\Tahap7;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap 7 D'mentai (Bug 4 audit sync) — smoke test SEMUA route GET tanpa
 * parameter (187 route) sebagai Owner (bypass semua permission via
 * Gate::before), memastikan TIDAK ADA yang 500. Bukan test fungsional detail
 * (itu sudah dicover test per-modul lain) -- murni "menu ini bisa dibuka
 * tanpa crash", sesuai permintaan audit "cek semua menu bisa dibuka tanpa
 * error".
 */
class SmokeTestSemuaMenuTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    /** Route yang SENGAJA di-skip: side-effect (cleanup/logout), bukan halaman HTML, atau butuh state khusus yang di luar scope smoke test. */
    private const SKIP = [
        'admin/cleanup-orphan-cascade', // side-effect mutation, bukan passive read
        'logout', // POST-only secara semantik, GET logout = state-changing
        'broadcasting/auth', // bukan halaman, endpoint auth Pusher
    ];

    public function test_semua_route_get_tanpa_parameter_tidak_500(): void
    {
        $owner = $this->buatUser('owner');
        $cabang = $this->cabangPertama();

        $routes = collect(Route::getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods()) && ! str_contains($r->uri(), '{'))
            ->map(fn ($r) => $r->uri())
            ->reject(fn ($uri) => in_array($uri, self::SKIP, true))
            ->reject(fn ($uri) => str_starts_with($uri, 'api/'))
            ->unique()
            ->values();

        $this->assertGreaterThan(100, $routes->count(), 'Prasyarat: harus banyak route ke-detect, kalau sedikit kemungkinan Route::getRoutes() salah scope.');

        $error500 = [];

        foreach ($routes as $uri) {
            $response = $this->actingAs($owner)->withSession(['active_cabang_id' => $cabang->id])->get('/' . $uri);
            $status = $response->baseResponse->getStatusCode();
            if ($status >= 500) {
                $error500[] = $uri . ' -> ' . $status;
            }
        }

        $this->assertEmpty($error500, "Route berikut mengembalikan 5xx (crash):\n" . implode("\n", $error500));
    }
}
