<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;

class NeracaSetting extends Model
{
    use HasAuditLog;

    protected $fillable = [
        'modal_owner',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'modal_owner' => 'decimal:2',
        ];
    }

    /**
     * Singleton getter — mirip pola PengaturanGaji::getSetting().
     */
    public static function getSetting(): static
    {
        return static::firstOrCreate([], [
            'modal_owner' => 0,
        ]);
    }
}
