<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Export Excel Laporan BEP per Cabang (Sprint 3 Batch 2, 2026-09-22). */
class LaporanBepCabangExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    use HasLaporanStyles;

    public function __construct(private Collection $dataCabang, private ?string $namaUser = null)
    {
    }

    public function collection(): Collection
    {
        return $this->dataCabang;
    }

    public function headings(): array
    {
        return ['Cabang', 'Pendapatan', 'Biaya Tetap', 'BEP (Rupiah)', '% Capaian BEP', 'Status'];
    }

    public function map($row): array
    {
        return [
            $row['cabang']->nama_cabang,
            (float) $row['pendapatan'],
            (float) $row['biaya_tetap'],
            (float) $row['bep_rupiah'],
            round((float) $row['pct_bep'], 1),
            $row['tercapai'] ? 'Tercapai' : 'Belum Tercapai',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A1:F1');
        foreach (['B', 'C', 'D'] as $col) {
            $this->styleRupiahColumn($sheet, $col);
        }
        $this->autoSizeAllColumns($sheet, 'A', 'F');

        $barisFooter = $this->dataCabang->count() + 3;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
