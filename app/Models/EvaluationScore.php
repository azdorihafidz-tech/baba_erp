<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EvaluationScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_reviewer_id',
        'evaluation_aspect_id',
        'skor',
        'komentar',
    ];

    protected function casts(): array
    {
        return [
            'skor' => 'integer',
        ];
    }

    public function evaluationReviewer()
    {
        return $this->belongsTo(EvaluationReviewer::class);
    }

    public function aspect()
    {
        return $this->belongsTo(EvaluationAspect::class, 'evaluation_aspect_id');
    }
}
