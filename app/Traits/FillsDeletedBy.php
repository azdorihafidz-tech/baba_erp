<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait FillsDeletedBy
{
    /**
     * Isi kolom deleted_by otomatis saat soft-delete — tidak menyentuh
     * trait SoftDeletes/HasAuditLog sama sekali, murni tambahan listener
     * event 'deleting'. Diabaikan saat forceDelete() (baris akan hilang
     * total, tidak ada gunanya diisi).
     */
    public static function bootFillsDeletedBy(): void
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
                return;
            }

            $table = $model->getTable();
            if (!Schema::hasColumn($table, 'deleted_by')) {
                return;
            }

            $userId = auth()->id();

            DB::table($table)
                ->where($model->getKeyName(), $model->getKey())
                ->update(['deleted_by' => $userId]);

            $model->deleted_by = $userId;
        });
    }
}
