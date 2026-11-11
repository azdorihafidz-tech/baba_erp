<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class JenisOlahan extends Model
{
    protected $fillable = ['nama', 'slug', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function boot(): void
    {
        parent::boot();

        // Auto-generate slug dari nama saat create; immutable setelah itu.
        static::creating(function (self $model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->nama, '_');
            }
        });
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }
}
