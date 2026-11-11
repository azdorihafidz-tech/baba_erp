<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Export Excel Laporan Audit Bukti Transaksi (Sprint 3 Batch 2, 2026-09-22). */
class LaporanAuditBuktiExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    use HasLaporanStyles;

    public function __construct(private Collection $rows, private int $threshold, private ?string $namaUser = null)
    {
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['Tanggal', 'No. Transaksi', 'Keterangan', 'Kategori', 'Cabang', 'Jumlah', 'Status Bukti'];
    }

    public function map($r): array
    {
        return [
            optional($r->tanggal_transaksi)->format('d/m/Y'),
            $r->nomor_transaksi,
            $r->keterangan,
            $r->kategoriDinamis?->nama ?? '-',
            $r->cabang?->nama_cabang ?? '-',
            (float) $r->jumlah,
            $r->bukti_path ? 'Sudah Upload' : 'BELUM UPLOAD',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A1:G1');
        $this->styleRupiahColumn($sheet, 'F');
        $this->autoSizeAllColumns($sheet, 'A', 'G');

        foreach ($this->rows as $idx => $r) {
            if (!$r->bukti_path) {
                $baris = $idx + 2;
                $sheet->getStyle('A' . $baris . ':G' . $baris)->getFont()->getColor()->setRGB('DC2626');
            }
        }

        $barisFooter = $this->rows->count() + 3;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
