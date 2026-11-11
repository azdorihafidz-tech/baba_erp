<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Tooltip extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'title', 'content', 'modul', 'aktif', 'urutan'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    // ── Scopes ──────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('aktif', true);
    }

    public function scopeByModul($query, string $modul)
    {
        return $query->where('modul', $modul);
    }

    // ── Static helper ────────────────────────────────

    /**
     * Ambil tooltip by key. Return null kalau tidak ada / tidak aktif.
     * Di-cache 1 jam. Graceful: return null kalau tabel belum ada.
     */
    public static function get(string $key): ?static
    {
        try {
            return Cache::remember("tooltip:{$key}", 3600, function () use ($key) {
                return static::where('key', $key)->where('aktif', true)->first();
            });
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Cache invalidation ───────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::saved(function (Tooltip $tooltip) {
            Cache::forget("tooltip:{$tooltip->key}");
            // Invalidate key lama juga kalau key-nya berubah
            $oldKey = $tooltip->getOriginal('key');
            if ($oldKey && $oldKey !== $tooltip->key) {
                Cache::forget("tooltip:{$oldKey}");
            }
        });

        static::deleted(function (Tooltip $tooltip) {
            Cache::forget("tooltip:{$tooltip->key}");
        });
    }
}
