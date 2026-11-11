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
 * Export Excel Laporan Stok Minimum (Sprint 3 Batch 1b/1c, 2026-09-21) --
 * menu ini SEBELUMNYA tidak punya export sama sekali. "Rekomendasi Beli"
 * = selisih (qty_minimum - qty) + buffer 20%, dibulatkan ke atas -- dipakai
 * utk bikin Purchase Order.
 */
class LaporanStokMinimumExport implements FromCollection, WithHeadings, WithMapping, WithStyles
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
        return ['Kode', 'Nama', 'Kategori', 'Stok Sekarang', 'Stok Minimum', 'Selisih', 'Rekomendasi Beli'];
    }

    public function map($s): array
    {
        /** @var Stock $s */
        $selisih = max(0, (float) $s->qty_minimum - (float) $s->qty);

        return [
            $s->item?->kode_item ?? '-',
            $s->item?->nama_item ?? '-',
            $s->item?->category?->nama_kategori ?? '-',
            (float) $s->qty,
            (float) $s->qty_minimum,
            $selisih,
            ceil($selisih * 1.2),
        ];
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
