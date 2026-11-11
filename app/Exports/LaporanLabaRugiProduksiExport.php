<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel Laporan Laba Rugi PRODUKSI (Sprint 3 Batch 1b/1c, 2026-09-21)
 * -- BEDA dari LaporanLabaRugiExport (dipakai LaporanKeuanganController,
 * cash-flow style). Menu ini murni profit produksi (Omzet vs HPP bahan
 * dari order_items), BUKAN cash flow -- makanya nama class disengaja
 * dibedakan "...Produksi" spy tidak collision/tertukar, lihat CLAUDE.md.
 */
class LaporanLabaRugiProduksiExport implements FromArray, WithStyles
{
    use HasLaporanStyles;

    private int $totalBarisBreakdown;
    private int $totalBarisDetail = 0;

    public function __construct(
        private array $ringkasan,
        private Collection $breakdownPenuh,
        private string $level,
        private string $labelLevel,
        private $detail,
        private ?string $namaUser = null,
    ) {
        $this->totalBarisBreakdown = $breakdownPenuh->count();
    }

    public function array(): array
    {
        $rows = [
            ['LAPORAN LABA RUGI PRODUKSI'],
            ['Level Breakdown: ' . $this->labelLevel],
            [],
            ['RINGKASAN'],
            ['Total Order', $this->ringkasan['total_order']],
            ['Total Omzet', (float) $this->ringkasan['total_omzet']],
            ['Total HPP', (float) $this->ringkasan['total_hpp']],
            ['Total Untung (Laba Kotor)', (float) $this->ringkasan['total_untung']],
            ['Margin', $this->ringkasan['margin'] !== null ? $this->ringkasan['margin'] . '%' : '-'],
            [],
            ['BREAKDOWN PER ' . strtoupper($this->labelLevel)],
        ];

        $rows[] = $this->headingBreakdown();
        foreach ($this->breakdownPenuh as $b) {
            $rows[] = $this->mapBreakdown($b);
        }
        $rows[] = [];
        $rows[] = ['DETAIL TRANSAKSI'];
        $rows[] = ['Tanggal', 'Waktu', 'No Order', 'Kategori', 'Item', 'Qty', 'Satuan', 'Omzet', 'HPP', 'Untung', 'Kasir'];

        foreach ($this->detail as $tanggal => $baris) {
            foreach ($baris as $r) {
                $rows[] = [
                    $tanggal,
                    \Carbon\Carbon::parse($r->order_created_at)->format('H:i'),
                    $r->nomor_order,
                    ucfirst($r->kategori),
                    $r->nama_item,
                    (float) $r->qty,
                    $r->satuan,
                    (float) $r->omzet,
                    (float) $r->hpp,
                    (float) $r->untung,
                    $r->kasir_nama ?? '-',
                ];
                $this->totalBarisDetail++;
            }
        }

        return $rows;
    }

    private function headingBreakdown(): array
    {
        return match ($this->level) {
            'order' => ['No Order', 'Tanggal', 'Pelanggan', 'Kasir', 'Omzet', 'HPP', 'Untung', 'Margin'],
            'kategori' => ['Kategori', 'Omzet', 'HPP', 'Untung', 'Margin'],
            'jenis_olahan' => ['Jenis Menu', 'Omzet', 'HPP', 'Untung', 'Margin'],
            default => ['Item', 'Tipe', 'Qty', 'Satuan', 'Omzet', 'HPP', 'Untung', 'Margin'],
        };
    }

    private function mapBreakdown($b): array
    {
        $margin = $b->margin !== null ? $b->margin . '%' : '-';

        return match ($this->level) {
            'order' => [
                $b->nomor_order, \Carbon\Carbon::parse($b->tanggal_order)->format('d/m/Y'),
                $b->nama_pelanggan ?? 'Umum', $b->kasir_nama ?? '-',
                (float) $b->total_omzet, (float) $b->total_hpp, (float) $b->total_untung, $margin,
            ],
            'kategori' => [ucfirst($b->kategori), (float) $b->total_omzet, (float) $b->total_hpp, (float) $b->total_untung, $margin],
            'jenis_olahan' => [ucfirst($b->jenis_olahan), (float) $b->total_omzet, (float) $b->total_hpp, (float) $b->total_untung, $margin],
            default => [
                $b->nama_item, $b->tipe, (float) $b->total_qty, $b->satuan,
                (float) $b->total_omzet, (float) $b->total_hpp, (float) $b->total_untung, $margin,
            ],
        };
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A1');
        $sheet->getStyle('A4')->getFont()->setBold(true);
        $barisHeaderBreakdown = 11;
        $sheet->getStyle('A' . $barisHeaderBreakdown . ':H' . $barisHeaderBreakdown)->getFont()->setBold(true);
        $sheet->getStyle('A' . $barisHeaderBreakdown . ':H' . $barisHeaderBreakdown)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB(ltrim(app(\App\Support\Branding::class)->warna('latar'), '#')); // 4.48 — ikut Warna Latar

        $barisHeaderDetail = $barisHeaderBreakdown + 1 + $this->totalBarisBreakdown + 2;
        $sheet->getStyle('A' . $barisHeaderDetail . ':K' . $barisHeaderDetail)->getFont()->setBold(true);

        $this->autoSizeAllColumns($sheet, 'A', 'K');

        $barisFooter = $barisHeaderDetail + 1 + $this->totalBarisDetail + 2;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
