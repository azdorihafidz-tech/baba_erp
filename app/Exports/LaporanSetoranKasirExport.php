<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use App\Models\Setoran;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel Laporan Setoran Kasir (Sprint 3 Batch 1a, 2026-09-21).
 * Ganti `fputcsv()` manual jadi xlsx sungguhan -- pola sama
 * LaporanPenjualanExport/TransaksiKeuanganExport.
 */
class LaporanSetoranKasirExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    use HasLaporanStyles;

    public function __construct(private Collection $setorans, private ?string $namaUser = null)
    {
    }

    public function collection(): Collection
    {
        return $this->setorans;
    }

    public function headings(): array
    {
        return ['Tanggal', 'Cabang', 'Total Sistem', 'Total Disetor', 'Selisih', 'Status'];
    }

    public function map($setoran): array
    {
        /** @var Setoran $setoran */
        return [
            $setoran->tanggal->format('d/m/Y'),
            $setoran->cabang?->nama_cabang ?? '-',
            (float) $setoran->total_penjualan_sistem,
            (float) $setoran->total_disetor,
            (float) $setoran->selisih,
            $setoran->status->value,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A1:F1');
        $this->styleRupiahColumn($sheet, 'C');
        $this->styleRupiahColumn($sheet, 'D');
        $this->styleRupiahColumn($sheet, 'E');
        $this->autoSizeAllColumns($sheet, 'A', 'F');

        $barisFooter = $this->setorans->count() + 3;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
