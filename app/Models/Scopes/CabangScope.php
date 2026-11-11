<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CabangScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Hanya apply scope jika user sudah login
        if (!auth()->check()) {
            return;
        }

        $user = auth()->user();

        // Owner dan Admin Pusat bisa lihat semua cabang
        if ($user->canAccessAllBranches()) {
            // Jika ada filter cabang aktif dari session, terapkan
            if (session('active_cabang_id')) {
                $builder->where($model->getTable() . '.cabang_id', session('active_cabang_id'));
            }
            // Jika tidak ada filter, tampilkan semua (mode konsolidasi)
            return;
        }

        // User biasa hanya bisa lihat data cabang mereka
        $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();

        if ($cabangId) {
            $builder->where($model->getTable() . '.cabang_id', $cabangId);
        }
    }
}
