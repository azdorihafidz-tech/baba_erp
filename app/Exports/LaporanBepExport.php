<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Export Excel Laporan BEP per Produk (Sprint 3 Batch 2, 2026-09-22). */
class LaporanBepExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    use HasLaporanStyles;

    public function __construct(private Collection $products, private ?string $namaUser = null)
    {
    }

    public function collection(): Collection
    {
        return $this->products;
    }

    public function headings(): array
    {
        return ['Produk', 'Harga Jual/Unit', 'Biaya Variabel/Unit', 'BEP (Unit)', 'BEP (Rupiah)'];
    }

    public function map($p): array
    {
        return [
            $p->nama_produk,
            (float) $p->harga_jual_per_unit,
            (float) $p->biaya_variabel_per_unit,
            (float) $p->bep_unit,
            (float) $p->bep_rupiah,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A1:E1');
        foreach (['B', 'C', 'E'] as $col) {
            $this->styleRupiahColumn($sheet, $col);
        }
        $this->autoSizeAllColumns($sheet, 'A', 'E');

        $barisFooter = $this->products->count() + 3;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
