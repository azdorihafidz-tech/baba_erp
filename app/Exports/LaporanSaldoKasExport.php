<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Export Excel Laporan Saldo Kas (Sprint 3 Batch 2, 2026-09-21) -- multi-section per kas. */
class LaporanSaldoKasExport implements FromArray, WithStyles
{
    use HasLaporanStyles;

    private array $headerRowIndexes = [];

    public function __construct(private Collection $kasReports, private ?string $namaUser = null)
    {
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->kasReports as $r) {
            $rows[] = ['KAS: ' . $r['kas']->nama_kas . ' (' . ($r['kas']->cabang?->nama_cabang ?? '-') . ')'];
            $rows[] = ['Saldo Awal Periode', '', '', '', (float) $r['saldo_awal']];
            $this->headerRowIndexes[] = count($rows) + 1;
            $rows[] = ['Tanggal', 'No. Transaksi', 'Keterangan', 'Debit (Keluar)', 'Kredit (Masuk)', 'Saldo'];
            foreach ($r['mutasi'] as $m) {
                $rows[] = [
                    \Carbon\Carbon::parse($m['tanggal'])->format('d/m/Y'),
                    $m['nomor'],
                    $m['keterangan'],
                    $m['debit'] ? (float) $m['debit'] : null,
                    $m['kredit'] ? (float) $m['kredit'] : null,
                    (float) $m['saldo_running'],
                ];
            }
            $rows[] = ['Saldo Akhir Periode', '', '', '', (float) $r['saldo_akhir']];
            $rows[] = [];
        }

        $rows[] = [$this->footerDicetakOleh($this->namaUser)];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        foreach ($this->headerRowIndexes as $idx) {
            $this->styleHeaderRow($sheet, 'A' . $idx . ':F' . $idx);
        }
        $this->styleRupiahColumn($sheet, 'D');
        $this->styleRupiahColumn($sheet, 'E');
        $this->styleRupiahColumn($sheet, 'F');
        $this->autoSizeAllColumns($sheet, 'A', 'F');

        return [];
    }
}
