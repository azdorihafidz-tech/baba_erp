<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Export Excel Laporan Buku Besar per akun COA (Sprint 3 Batch 2, 2026-09-22). */
class LaporanBukuBesarExport implements FromArray, WithStyles
{
    use HasLaporanStyles;

    private int $headerRow = 1;
    private int $totalRows = 0;

    public function __construct(private array $ledger, private string $cabangNama, private ?string $namaUser = null)
    {
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = ['Akun', $this->ledger['akun']->kode . ' — ' . $this->ledger['akun']->nama];
        $rows[] = ['Saldo Normal', ucfirst($this->ledger['akun']->saldo_normal)];
        $rows[] = ['Cabang', $this->cabangNama];
        $rows[] = [];

        $this->headerRow = count($rows) + 1;
        $rows[] = ['Tanggal', 'Keterangan', 'Debit', 'Kredit', 'Saldo'];

        foreach ($this->ledger['baris'] as $b) {
            $rows[] = [
                \Carbon\Carbon::parse($b['tanggal'])->format('d/m/Y'),
                $b['keterangan'] ?? '-',
                (float) $b['debit'],
                (float) $b['kredit'],
                (float) $b['saldo'],
            ];
        }

        $rows[] = ['Total', '', (float) $this->ledger['total_debit'], (float) $this->ledger['total_kredit'], (float) $this->ledger['saldo_akhir']];

        $this->totalRows = count($rows);

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A' . $this->headerRow . ':E' . $this->headerRow);
        $this->styleRupiahColumn($sheet, 'C');
        $this->styleRupiahColumn($sheet, 'D');
        $this->styleRupiahColumn($sheet, 'E');
        $this->autoSizeAllColumns($sheet, 'A', 'E');

        $barisTotal = $this->totalRows;
        $sheet->getStyle('A' . $barisTotal . ':E' . $barisTotal)->getFont()->setBold(true);

        $barisFooter = $this->totalRows + 3;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
