<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Export Excel Laporan Penyusutan Aset (Sprint 3 Batch 2, 2026-09-21). */
class LaporanPenyusutanExport implements FromCollection, WithHeadings, WithMapping, WithStyles
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
        return ['Kode Aset', 'Nama Aset', 'Kategori', 'Lokasi', 'Nilai Buku Awal', 'Penyusutan', 'Akumulasi', 'Nilai Buku Akhir'];
    }

    public function map($d): array
    {
        return [
            $d->asset?->kode_aset ?? '-',
            $d->asset?->nama_aset ?? '-',
            $d->asset?->kategori?->nama_kategori ?? '-',
            $d->asset?->lokasi?->nama_cabang ?? '-',
            (float) $d->nilai_buku_awal,
            (float) $d->jumlah_penyusutan,
            (float) $d->akumulasi_penyusutan,
            (float) $d->nilai_buku_akhir,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A1:H1');
        foreach (['E', 'F', 'G', 'H'] as $col) {
            $this->styleRupiahColumn($sheet, $col);
        }
        $this->autoSizeAllColumns($sheet, 'A', 'H');

        $barisFooter = $this->rows->count() + 3;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
