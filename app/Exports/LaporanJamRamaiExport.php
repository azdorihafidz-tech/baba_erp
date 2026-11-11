<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Export Excel Laporan Analisa Jam Ramai (Sprint 3 Batch 2, 2026-09-22). */
class LaporanJamRamaiExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    use HasLaporanStyles;

    public function __construct(private array $analisa, private ?string $namaUser = null)
    {
    }

    public function collection(): Collection
    {
        return collect($this->analisa['per_jam']);
    }

    public function headings(): array
    {
        return ['Jam', 'Jumlah Transaksi', 'Total Nominal'];
    }

    public function map($row): array
    {
        return [
            $row['label'],
            $row['jumlah_transaksi'],
            (float) $row['total_nominal'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A1:C1');
        $this->styleRupiahColumn($sheet, 'C');
        $this->autoSizeAllColumns($sheet, 'A', 'C');

        $jumlahBaris = count($this->analisa['per_jam']);
        $jamPuncak = $this->analisa['jam_puncak']['jam'] ?? null;
        if ($jamPuncak !== null) {
            $baris = $jamPuncak + 2;
            $sheet->getStyle('A' . $baris . ':C' . $baris)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D1FAE5');
        }

        $barisFooter = $jumlahBaris + 3;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
