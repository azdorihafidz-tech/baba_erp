<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Export Excel Laporan Absensi (HR/SDM, Sprint 3 Batch 2, 2026-09-21). */
class LaporanAbsensiExport implements FromCollection, WithHeadings, WithMapping, WithStyles
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
        return ['Tanggal', 'Karyawan', 'Cabang', 'Status', 'Jam Masuk', 'Jam Keluar', 'Lembur (jam)', 'Keterangan'];
    }

    public function map($a): array
    {
        return [
            $a->tanggal?->format('d/m/Y') ?? '-',
            $a->karyawan?->nama_lengkap ?? '-',
            $a->cabang?->nama_cabang ?? '-',
            ucfirst($a->status),
            $a->jam_masuk ?? '-',
            $a->jam_keluar ?? '-',
            (float) ($a->jam_lembur ?? 0),
            $a->keterangan ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A1:H1');
        $this->autoSizeAllColumns($sheet, 'A', 'H');

        $barisFooter = $this->rows->count() + 3;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
