<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel Laporan Laba Rugi (menu Laporan → Keuangan).
 *
 * Bug fix 2026-09-21: export lama pakai fputcsv() manual (bukan xlsx
 * sungguhan) DAN crash — `$pemasukan`/`$pengeluaran` didapat dari
 * `TransaksiKeuangan::selectRaw('kategori, SUM(jumlah) as total')`, tapi
 * Eloquent TETAP menerapkan cast model (`kategori` => App\Enums\
 * KategoriTransaksi) ke kolom raw itu -- fputcsv() coba string-cast objek
 * enum, PHP fatal error "Object ... could not be converted to string".
 * Selain itu file .xls yang dihasilkan sebenarnya CSV teks dgn delimiter
 * ";" -- kalau locale Excel-nya pakai koma sbg list separator, SEMUA
 * kolom kebaca jadi 1 kolom (persis keluhan Owner). Fix: xlsx sungguhan
 * via maatwebsite/excel (pola sama `TransaksiKeuanganExport`), kategori
 * dipanggil `->label()` (bukan objek mentah).
 */
class LaporanLabaRugiExport implements FromArray, WithStyles
{
    private int $totalBarisPemasukan;
    private int $totalBarisPengeluaran;

    public function __construct(
        private Collection $pemasukan,
        private Collection $pengeluaran,
        private float $totalPemasukan,
        private float $totalPengeluaran,
        private float $labaRugi,
        private string $periodeLabel,
    ) {
        $this->totalBarisPemasukan = $pemasukan->count();
        $this->totalBarisPengeluaran = $pengeluaran->count();
    }

    public function array(): array
    {
        $rows = [
            ['LAPORAN LABA RUGI'],
            [$this->periodeLabel],
            [],
            ['PEMASUKAN'],
            ['Kategori', 'Total (Rp)'],
        ];

        foreach ($this->pemasukan as $p) {
            $rows[] = [$p->kategori?->label() ?? 'Lainnya', (float) $p->total];
        }
        $rows[] = ['Total Pemasukan', $this->totalPemasukan];
        $rows[] = [];
        $rows[] = ['PENGELUARAN'];
        $rows[] = ['Kategori', 'Total (Rp)'];

        foreach ($this->pengeluaran as $p) {
            $rows[] = [$p->kategori?->label() ?? 'Lainnya', (float) $p->total];
        }
        $rows[] = ['Total Pengeluaran', $this->totalPengeluaran];
        $rows[] = [];
        $rows[] = [$this->labaRugi >= 0 ? 'LABA BERSIH' : 'RUGI BERSIH', abs($this->labaRugi)];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A4')->getFont()->setBold(true);
        $sheet->getStyle('A5:B5')->getFont()->setBold(true);
        $sheet->getStyle('A5:B5')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D1FAE5');

        $baris_header_pengeluaran = 6 + $this->totalBarisPemasukan + 1 + 1;
        $sheet->getStyle('A' . ($baris_header_pengeluaran - 1))->getFont()->setBold(true);
        $sheet->getStyle('A' . $baris_header_pengeluaran . ':B' . $baris_header_pengeluaran)->getFont()->setBold(true);
        $sheet->getStyle('A' . $baris_header_pengeluaran . ':B' . $baris_header_pengeluaran)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FEE2E2');

        $barisTerakhir = 6 + $this->totalBarisPemasukan + 1 + 3 + $this->totalBarisPengeluaran + 1 + 1;
        $sheet->getStyle('A' . $barisTerakhir . ':B' . $barisTerakhir)->getFont()->setBold(true)->setSize(12);

        $sheet->getStyle('B:B')->getNumberFormat()->setFormatCode('#,##0');
        foreach (['A', 'B'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}
