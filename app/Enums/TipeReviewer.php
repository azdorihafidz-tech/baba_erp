<?php

namespace App\Enums;

enum TipeReviewer: string
{
    case Atasan = 'atasan';
    case RekanKerja = 'rekan_kerja';
    case SelfAssessment = 'self_assessment';

    public function label(): string
    {
        return match($this) {
            TipeReviewer::Atasan => 'Atasan Langsung',
            TipeReviewer::RekanKerja => 'Rekan Kerja',
            TipeReviewer::SelfAssessment => 'Self Assessment',
        };
    }

    public function bobot(): int
    {
        return match($this) {
            TipeReviewer::Atasan => 50,
            TipeReviewer::RekanKerja => 30,
            TipeReviewer::SelfAssessment => 20,
        };
    }
}
