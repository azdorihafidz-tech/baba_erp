<?php

namespace App\Enums;

enum StatusEvaluasiPeriode: string
{
    case Draft = 'draft';
    case Dibuka = 'dibuka';
    case Ditutup = 'ditutup';
    case Final = 'final';

    public function label(): string
    {
        return match($this) {
            StatusEvaluasiPeriode::Draft => 'Draft',
            StatusEvaluasiPeriode::Dibuka => 'Dibuka',
            StatusEvaluasiPeriode::Ditutup => 'Ditutup',
            StatusEvaluasiPeriode::Final => 'Final',
        };
    }
}
