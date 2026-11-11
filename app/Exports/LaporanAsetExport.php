<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Export Excel Laporan Aset (Sprint 3 Batch 2, 2026-09-21). */
class LaporanAsetExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    use HasLaporanStyles;

    public function __construct(private Collection $assets, private ?string $namaUser = null)
    {
    }

    public function collection(): Collection
    {
        return $this->assets;
    }

    public function headings(): array
    {
        return ['Kode Aset', 'Nama Aset', 'Kategori', 'Lokasi', 'Tgl Perolehan', 'Harga Perolehan', 'Nilai Buku', 'Kondisi', 'Status'];
    }

    public function map($a): array
    {
        return [
            $a->kode_aset,
            $a->nama_aset,
            $a->kategori?->nama_kategori ?? '-',
            $a->lokasi?->nama_cabang ?? '-',
            $a->tanggal_perolehan?->format('d/m/Y') ?? '-',
            (float) $a->harga_perolehan,
            (float) $a->nilai_buku,
            $a->kondisi?->value ?? '-',
            $a->status?->value ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A1:I1');
        $this->styleRupiahColumn($sheet, 'F');
        $this->styleRupiahColumn($sheet, 'G');
        $this->autoSizeAllColumns($sheet, 'A', 'I');

        $barisFooter = $this->assets->count() + 3;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
