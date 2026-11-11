<?php

namespace App\Models;

use App\Enums\PredikatEvaluasi;
use App\Enums\StatusEvaluasi;
use App\Traits\HasCabang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Evaluation extends Model
{
    use HasFactory, HasCabang;

    protected $fillable = [
        'evaluation_period_id',
        'karyawan_id',
        'cabang_id',
        'skor_akhir',
        'predikat',
        'catatan_manajer',
        'status',
        'finalized_by',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'predikat' => PredikatEvaluasi::class,
            'status' => StatusEvaluasi::class,
            'skor_akhir' => 'decimal:4',
            'finalized_at' => 'datetime',
        ];
    }

    public function period()
    {
        return $this->belongsTo(EvaluationPeriod::class, 'evaluation_period_id');
    }

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    public function reviewers()
    {
        return $this->hasMany(EvaluationReviewer::class);
    }

    public function summaries()
    {
        return $this->hasMany(EvaluationSummary::class);
    }

    public function finalizedBy()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
