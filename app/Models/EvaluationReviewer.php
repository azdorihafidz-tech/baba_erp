<?php

namespace App\Models;

use App\Enums\TipeReviewer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EvaluationReviewer extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_id',
        'reviewer_id',
        'tipe_reviewer',
        'bobot_reviewer_persen',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'tipe_reviewer' => TipeReviewer::class,
            'bobot_reviewer_persen' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function scores()
    {
        return $this->hasMany(EvaluationScore::class);
    }
}
