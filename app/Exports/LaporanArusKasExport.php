<?php

namespace App\Exports;

use App\Models\TransaksiKeuangan;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel Laporan Arus Kas (menu Laporan → Keuangan → Arus Kas).
 * Bug fix 2026-09-21: dulu fputcsv() manual, delimiter ";" bikin Excel
 * salah parse (semua kolom kebaca jadi 1 kolom kalau locale beda) -- lihat
 * penjelasan lengkap di LaporanLabaRugiExport. Diganti xlsx sungguhan.
 */
class LaporanArusKasExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private Collection $transaksis)
    {
    }

    public function collection(): Collection
    {
        return $this->transaksis;
    }

    public function headings(): array
    {
        return ['Tanggal', 'No. Transaksi', 'Tipe', 'Kategori', 'Keterangan', 'Jumlah (Rp)', 'Cabang'];
    }

    public function map($t): array
    {
        /** @var TransaksiKeuangan $t */
        return [
            $t->tanggal_transaksi?->format('d-m-Y') ?? '-',
            $t->nomor_transaksi,
            $t->tipe?->label() ?? '-',
            $t->label_kategori,
            $t->keterangan,
            (float) $t->jumlah,
            $t->cabang?->nama_cabang ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->getStyle('A1:G1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E4E8EE');
        $sheet->getStyle('F:F')->getNumberFormat()->setFormatCode('#,##0');
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}
