<?php

namespace Tests\Feature\Tahap7;

use App\Http\Controllers\TrashController;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Tahap 7 D'mentai (Bug 4 audit sync) — GUARD RAIL: setiap model yang
 * terdaftar di TrashController::$models WAJIB punya tab di trash/index.blade.php
 * ($modelLabels). Ditemukan 8 model (setorans, loyalty_*, item_variants,
 * item_attributes*, order_payments) yang terdaftar & berfungsi penuh di
 * controller tapi TIDAK PERNAH punya tab -- efektif tidak bisa ditemukan
 * user lewat UI Data Terhapus sama sekali (cuma via ?model= manual).
 */
class DataTerhapusCoverageTest extends TestCase
{
    public function test_semua_model_trash_controller_punya_tab_di_view(): void
    {
        $controller = new TrashController();
        $modelKeys = $controller->modelList();

        $viewContent = File::get(resource_path('views/trash/index.blade.php'));
        preg_match('/\$modelLabels = \[(.*?)\n\];/s', $viewContent, $m);
        $this->assertNotEmpty($m, 'Tidak bisa menemukan $modelLabels di trash/index.blade.php -- struktur view berubah?');

        preg_match_all("/'([a-z_]+)'\s*=>/", $m[1], $mView);
        $viewKeys = $mView[1];

        $missing = array_diff($modelKeys, $viewKeys);

        $this->assertEmpty(
            $missing,
            'Model berikut terdaftar di TrashController::$models tapi TIDAK punya tab di Data Terhapus (tidak bisa ditemukan user): '
            . implode(', ', $missing)
        );
    }
}
