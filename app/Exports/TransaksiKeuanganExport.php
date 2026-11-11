<?php

namespace App\Exports;

use App\Models\TransaksiKeuangan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel Kas & Transaksi — TIDAK PERNAH membangun query sendiri,
 * selalu terima Builder yang SUDAH difilter dari
 * KeuanganController::buildFilteredQuery() (dipakai bareng oleh index()),
 * supaya export selalu 100% konsisten dengan apa yang sedang dilihat di
 * halaman (filter tanggal/cabang/tipe/kategori/search).
 */
class TransaksiKeuanganExport implements FromQuery, WithHeadings, WithMapping, WithStyles
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
            'Tanggal Transaksi',
            'No. Referensi',
            'Tipe',
            'Kategori',
            'Kas',
            'Cabang',
            'Jumlah (Rp)',
            'Keterangan',
            'Dibuat Oleh',
        ];
    }

    public function map($transaksi): array
    {
        /** @var TransaksiKeuangan $transaksi */
        return [
            $transaksi->tanggal_transaksi?->format('d-m-Y') ?? '-',
            $transaksi->nomor_transaksi,
            $transaksi->tipe?->label() ?? '-',
            $transaksi->label_kategori,
            $transaksi->kas?->nama_kas ?? '-',
            $transaksi->cabang?->nama_cabang ?? '-',
            (float) $transaksi->jumlah,
            $transaksi->keterangan,
            $transaksi->createdBy?->name ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->getStyle('A1:I1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E4E8EE');
        $sheet->getStyle('G:G')->getNumberFormat()->setFormatCode('#,##0');
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}
