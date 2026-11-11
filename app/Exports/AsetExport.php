<?php

namespace App\Exports;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel Manajemen Aset — TIDAK PERNAH membangun query sendiri,
 * selalu terima Builder yang SUDAH difilter dari
 * AssetController::buildFilteredQuery() (dipakai bareng oleh index()),
 * supaya export selalu 100% konsisten dengan apa yang sedang dilihat di
 * halaman (filter lokasi/kategori/kondisi/status/search).
 */
class AsetExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private Builder $query)
    {
    }

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'Kode Aset',
            'Nama Aset',
            'Kategori',
            'Cabang',
            'Tanggal Perolehan',
            'Harga Perolehan (Rp)',
            'Nilai Residu (Rp)',
            'Umur Ekonomis (Bulan)',
            'Akumulasi Penyusutan (Rp)',
            'Nilai Buku (Rp)',
            'Status',
        ];
    }

    public function map($asset): array
    {
        /** @var Asset $asset */
        $akumulasiPenyusutan = (float) $asset->harga_perolehan - (float) $asset->nilai_buku;

        return [
            $asset->kode_aset,
            $asset->nama_aset,
            $asset->kategori?->nama_kategori ?? '-',
            $asset->lokasi?->nama_cabang ?? '-',
            $asset->tanggal_perolehan?->format('d-m-Y') ?? '-',
            (float) $asset->harga_perolehan,
            (float) $asset->nilai_residu,
            $asset->umur_ekonomis_bulan,
            $akumulasiPenyusutan,
            (float) $asset->nilai_buku,
            $asset->status?->label() ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        $sheet->getStyle('A1:K1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E4E8EE');
        foreach (['F', 'G', 'I', 'J'] as $col) {
            $sheet->getStyle($col . ':' . $col)->getNumberFormat()->setFormatCode('#,##0');
        }
        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}
