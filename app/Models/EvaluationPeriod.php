<?php

namespace App\Models;

use App\Enums\StatusEvaluasiPeriode;
use App\Traits\HasCabang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EvaluationPeriod extends Model
{
    use HasFactory, HasCabang;

    protected $fillable = [
        'nama_periode',
        'cabang_id',
        'tanggal_mulai',
        'tanggal_selesai',
        'deadline_pengisian',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusEvaluasiPeriode::class,
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'deadline_pengisian' => 'date',
        ];
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
