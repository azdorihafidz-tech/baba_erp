<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class Panduan extends Model
{
    use HasFactory;

    protected $table = 'panduan';

    protected $fillable = ['slug', 'judul', 'konten', 'modul', 'urutan', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function scopeActive($query)
    {
        return $query->where('aktif', true);
    }

    public function scopeByModul($query, string $modul)
    {
        return $query->where('modul', $modul);
    }

    public static function getBySlug(string $slug): ?static
    {
        try {
            return Cache::remember("panduan:{$slug}", 3600, function () use ($slug) {
                return static::where('slug', $slug)->where('aktif', true)->first();
            });
        } catch (\Throwable) {
            return null;
        }
    }

    public function getKontenHtmlAttribute(): string
    {
        try {
            $converter = new GithubFlavoredMarkdownConverter([
                'html_input'         => 'strip',
                'allow_unsafe_links' => false,
            ]);
            return $converter->convert($this->konten)->getContent();
        } catch (\Throwable) {
            return e($this->konten);
        }
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saved(function (Panduan $panduan) {
            Cache::forget("panduan:{$panduan->slug}");
            $oldSlug = $panduan->getOriginal('slug');
            if ($oldSlug && $oldSlug !== $panduan->slug) {
                Cache::forget("panduan:{$oldSlug}");
            }
        });

        static::deleted(function (Panduan $panduan) {
            Cache::forget("panduan:{$panduan->slug}");
        });
    }
}
