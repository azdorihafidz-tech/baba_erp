<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Export Excel Laporan Pemakaian Perlengkapan (Sprint 3 Batch 2, 2026-09-22). */
class LaporanPerlengkapanExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    use HasLaporanStyles;

    public function __construct(private Collection $detail, private array $ringkasan, private ?string $namaUser = null)
    {
    }

    public function collection(): Collection
    {
        return $this->detail;
    }

    public function headings(): array
    {
        return ['Tanggal', 'Item', 'Cabang', 'Qty', 'Satuan', 'Nilai', 'Keterangan'];
    }

    public function map($row): array
    {
        return [
            \Carbon\Carbon::parse($row->tanggal_pemakaian)->format('d/m/Y'),
            $row->nama_item,
            $row->nama_cabang,
            (float) $row->qty,
            $row->satuan,
            (float) $row->nilai,
            $row->keterangan ?: '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A1:G1');
        $this->styleRupiahColumn($sheet, 'F');
        $this->autoSizeAllColumns($sheet, 'A', 'G');

        $barisRingkasan = $this->detail->count() + 3;
        $sheet->setCellValue('A' . $barisRingkasan, 'Total Nilai');
        $sheet->setCellValue('B' . $barisRingkasan, 'Rp ' . number_format($this->ringkasan['total_nilai'], 0, ',', '.'));
        $sheet->setCellValue('A' . ($barisRingkasan + 1), 'Jumlah Kejadian');
        $sheet->setCellValue('B' . ($barisRingkasan + 1), $this->ringkasan['jumlah_kejadian']);
        $sheet->setCellValue('A' . ($barisRingkasan + 2), 'Item Berbeda Terpakai');
        $sheet->setCellValue('B' . ($barisRingkasan + 2), $this->ringkasan['jumlah_item']);

        $barisFooter = $barisRingkasan + 4;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
