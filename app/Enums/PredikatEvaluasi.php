<?php

namespace App\Enums;

enum PredikatEvaluasi: string
{
    case SangatBaik = 'sangat_baik';
    case Baik = 'baik';
    case Cukup = 'cukup';
    case Kurang = 'kurang';
    case SangatKurang = 'sangat_kurang';

    public function label(): string
    {
        return match($this) {
            PredikatEvaluasi::SangatBaik => 'Sangat Baik',
            PredikatEvaluasi::Baik => 'Baik',
            PredikatEvaluasi::Cukup => 'Cukup',
            PredikatEvaluasi::Kurang => 'Kurang',
            PredikatEvaluasi::SangatKurang => 'Sangat Kurang',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            PredikatEvaluasi::SangatBaik => 'bg-success',
            PredikatEvaluasi::Baik => 'bg-primary',
            PredikatEvaluasi::Cukup => 'bg-warning',
            PredikatEvaluasi::Kurang => 'bg-orange',
            PredikatEvaluasi::SangatKurang => 'bg-danger',
        };
    }

    public static function fromSkor(float $skor): self
    {
        return match(true) {
            $skor >= 4.5 => self::SangatBaik,
            $skor >= 3.5 => self::Baik,
            $skor >= 2.5 => self::Cukup,
            $skor >= 1.5 => self::Kurang,
            default => self::SangatKurang,
        };
    }
}
