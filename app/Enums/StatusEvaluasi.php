<?php

namespace App\Enums;

enum StatusEvaluasi: string
{
    case Proses = 'proses';
    case Selesai = 'selesai';
    case Final = 'final';

    public function label(): string
    {
        return match($this) {
            StatusEvaluasi::Proses => 'Dalam Proses',
            StatusEvaluasi::Selesai => 'Selesai',
            StatusEvaluasi::Final => 'Final',
        };
    }
}
