<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use App\Models\Stock;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel Laporan Stok (index -- snapshot stok saat ini). Sprint 3
 * Batch 1b/1c, 2026-09-21. Ganti fputcsv() manual jadi xlsx sungguhan.
 */
class LaporanStokExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    use HasLaporanStyles;

    public function __construct(private Collection $stocks, private ?string $namaUser = null)
    {
    }

    public function collection(): Collection
    {
        return $this->stocks;
    }

    public function headings(): array
    {
        return ['Kode', 'Nama', 'Kategori', 'Satuan', 'Stok', 'Stok Minimum', 'Status'];
    }

    public function map($s): array
    {
        /** @var Stock $s */
        return [
            $s->item?->kode_item ?? '-',
            $s->item?->nama_item ?? '-',
            $s->item?->category?->nama_kategori ?? '-',
            $s->item?->satuan ?? '-',
            (float) $s->qty,
            (float) $s->qty_minimum,
            $this->labelStatus($s),
        ];
    }

    private function labelStatus(Stock $s): string
    {
        if ($s->qty <= 0) return 'Habis';
        if ($s->isBelowMinimum()) return 'Minim';
        return 'Aman';
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A1:G1');
        $this->autoSizeAllColumns($sheet, 'A', 'G');

        $barisFooter = $this->stocks->count() + 3;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
