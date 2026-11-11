<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EvaluationSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_id',
        'evaluation_aspect_id',
        'skor_atasan',
        'skor_rekan_rata',
        'skor_self',
        'skor_tertimbang',
        'skor_final',
    ];

    protected function casts(): array
    {
        return [
            'skor_atasan' => 'decimal:4',
            'skor_rekan_rata' => 'decimal:4',
            'skor_self' => 'decimal:4',
            'skor_tertimbang' => 'decimal:4',
            'skor_final' => 'decimal:4',
        ];
    }

    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function aspect()
    {
        return $this->belongsTo(EvaluationAspect::class, 'evaluation_aspect_id');
    }
}
