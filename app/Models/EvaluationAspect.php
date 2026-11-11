<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EvaluationAspect extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_aspek',
        'deskripsi',
        'bobot_persen',
        'urutan',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'bobot_persen' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scores()
    {
        return $this->hasMany(EvaluationScore::class);
    }

    public function summaries()
    {
        return $this->hasMany(EvaluationSummary::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true)->orderBy('urutan');
    }
}
