<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Export Excel Laporan Transfer/Perpindahan Dana (Sprint 3 Batch 2, 2026-09-21). */
class LaporanSetoranExport implements FromCollection, WithHeadings, WithMapping, WithStyles
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
        return ['Tanggal', 'No. Transaksi', 'Cabang Asal', 'Kas Asal', 'Jumlah', 'Status', 'Keterangan'];
    }

    public function map($r): array
    {
        $status = !is_null($r->deleted_at) ? ($r->status_setoran ?? 'dihapus') : ($r->status_setoran ?? '-');

        return [
            optional($r->tanggal_transaksi)->format('d/m/Y') ?? '-',
            $r->nomor_transaksi,
            $r->cabang?->nama_cabang ?? '-',
            $r->kas?->nama_kas ?? '-',
            (float) $r->jumlah,
            ucfirst(str_replace('_', ' ', $status)),
            $r->keterangan,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A1:G1');
        $this->styleRupiahColumn($sheet, 'E');
        $this->autoSizeAllColumns($sheet, 'A', 'G');

        $barisFooter = $this->rows->count() + 3;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
