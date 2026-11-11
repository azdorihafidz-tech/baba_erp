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
 * Export Excel Laporan Rekap Harian (Sprint 3 Batch 1b/1c, 2026-09-21) --
 * 1 baris per Tanggal+Cabang. Sengaja BUKAN nama "Setoran Kasir" (beda
 * sumber data dgn menu Laporan Setoran Kasir, lihat [[4.24]]/[[4.25]]).
 */
class LaporanRekapHarianExport implements FromCollection, WithHeadings, WithMapping, WithStyles
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
        return ['Tanggal', 'Cabang', 'Total Order', 'Total Pemasukan', 'Total Pengeluaran', 'Kas Bersih'];
    }

    public function map($row): array
    {
        return [
            \Carbon\Carbon::parse($row->tanggal)->format('d/m/Y'),
            $row->cabang_nama,
            $row->jumlah_order,
            (float) $row->total_pemasukan,
            (float) $row->total_pengeluaran,
            (float) $row->kas_bersih,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A1:F1');
        $this->styleRupiahColumn($sheet, 'D');
        $this->styleRupiahColumn($sheet, 'E');
        $this->styleRupiahColumn($sheet, 'F');
        $this->autoSizeAllColumns($sheet, 'A', 'F');

        $barisFooter = $this->rows->count() + 3;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
