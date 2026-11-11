<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Export Excel Laporan Per Kategori (Sprint 3 Batch 2, 2026-09-21). */
class LaporanKategoriExport implements FromCollection, WithHeadings, WithMapping, WithStyles
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
        return ['Parent Kategori', 'Kategori', 'Tipe', 'Jumlah Transaksi', 'Total'];
    }

    public function map($r): array
    {
        return [
            $r->parent_nama ?? '-',
            $r->kategori_nama ?? 'Lainnya',
            ucfirst($r->tipe),
            (int) $r->jumlah_transaksi,
            (float) $r->total,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A1:E1');
        $this->styleRupiahColumn($sheet, 'E');
        $this->autoSizeAllColumns($sheet, 'A', 'E');

        $barisFooter = $this->rows->count() + 3;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
