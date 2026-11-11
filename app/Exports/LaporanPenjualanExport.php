<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use App\Models\Order;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel Laporan Penjualan (Sprint 3 Batch 1a, 2026-09-21).
 * Ganti `fputcsv()` manual (CSV bertopeng .xls, "1 kolom banyak isi" kalau
 * locale Excel beda) jadi xlsx sungguhan -- pola sama TransaksiKeuanganExport.
 */
class LaporanPenjualanExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    use HasLaporanStyles;

    public function __construct(private Collection $orders, private ?string $namaUser = null)
    {
    }

    public function collection(): Collection
    {
        return $this->orders;
    }

    public function headings(): array
    {
        return ['No. Order', 'Tanggal', 'Cabang', 'Pelanggan', 'Tipe', 'Total Bayar', 'Status'];
    }

    public function map($order): array
    {
        /** @var Order $order */
        return [
            $order->nomor_order,
            $order->tanggal_order?->format('d/m/Y') ?? '-',
            $order->cabang?->nama_cabang ?? '-',
            $order->nama_pelanggan ?? $order->pelanggan?->nama ?? 'Umum',
            $order->tipe_order?->label() ?? '-',
            (float) $order->total_bayar,
            $order->status?->label() ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A1:G1');
        $this->styleRupiahColumn($sheet, 'F');
        $this->autoSizeAllColumns($sheet, 'A', 'G');

        $barisFooter = $this->orders->count() + 3;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
