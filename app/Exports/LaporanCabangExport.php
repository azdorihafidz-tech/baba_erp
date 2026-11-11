<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Export Excel Laporan Cabang vs Cabang (Sprint 3 Batch 2, 2026-09-21). */
class LaporanCabangExport implements FromArray, WithStyles
{
    use HasLaporanStyles;

    public function __construct(
        private Collection $ranking,
        private float $totalPemasukan,
        private float $totalPengeluaran,
        private float $totalNet,
        private ?string $namaUser = null,
    ) {
    }

    public function array(): array
    {
        $rows = [['Ranking', 'Cabang', 'Pemasukan', 'Pengeluaran', 'Setoran Keluar', 'Setoran Masuk', 'Net']];

        foreach ($this->ranking as $i => $r) {
            $rows[] = [$i + 1, $r['nama_cabang'], (float) $r['pemasukan'], (float) $r['pengeluaran'], (float) $r['setoran_keluar'], (float) $r['setoran_masuk'], (float) $r['net']];
        }
        $rows[] = ['', 'TOTAL', $this->totalPemasukan, $this->totalPengeluaran, '', '', $this->totalNet];
        $rows[] = [];
        $rows[] = [$this->footerDicetakOleh($this->namaUser)];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A1:G1');
        foreach (['C', 'D', 'E', 'F', 'G'] as $col) {
            $this->styleRupiahColumn($sheet, $col);
        }
        $this->autoSizeAllColumns($sheet, 'A', 'G');

        $barisTotal = $this->ranking->count() + 2;
        $sheet->getStyle('A' . $barisTotal . ':G' . $barisTotal)->getFont()->setBold(true);

        return [];
    }
}
