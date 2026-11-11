<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Export Excel Laporan Penggajian (HR/SDM, Sprint 3 Batch 2, 2026-09-21). */
class LaporanPenggajianExport implements FromCollection, WithHeadings, WithMapping, WithStyles
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
        return ['Karyawan', 'Cabang', 'Gaji Pokok', 'Tunjangan', 'Lembur', 'Bonus', 'Potongan', 'Total Gaji', 'Status'];
    }

    public function map($p): array
    {
        return [
            $p->karyawan?->nama_lengkap ?? '-',
            $p->cabang?->nama_cabang ?? '-',
            (float) $p->gaji_pokok,
            (float) $p->tunjangan,
            (float) $p->uang_lembur,
            (float) $p->bonus,
            (float) ($p->potongan_absensi + $p->potongan_lain),
            (float) $p->total_gaji,
            ucfirst(str_replace('_', ' ', $p->status)),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A1:I1');
        foreach (['C', 'D', 'E', 'F', 'G', 'H'] as $col) {
            $this->styleRupiahColumn($sheet, $col);
        }
        $this->autoSizeAllColumns($sheet, 'A', 'I');

        $barisFooter = $this->rows->count() + 3;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
