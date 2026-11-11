<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel Laporan Evaluasi (HR/SDM, Sprint 3 Batch 2, 2026-09-21) --
 * menu ini SEBELUMNYA tidak punya export sama sekali.
 */
class LaporanEvaluasiExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    use HasLaporanStyles;

    public function __construct(private Collection $rows, private ?string $namaUser = null)
    {
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['Karyawan', 'Cabang', 'Skor Akhir', 'Predikat'];
    }

    public function map($e): array
    {
        return [
            $e->karyawan?->nama_lengkap ?? '-',
            $e->cabang?->nama_cabang ?? '-',
            (float) $e->skor_akhir,
            $e->predikat?->label() ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A1:D1');
        $this->autoSizeAllColumns($sheet, 'A', 'D');

        $barisFooter = $this->rows->count() + 3;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
