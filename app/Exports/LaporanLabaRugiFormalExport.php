<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Export Excel Laporan Laba Rugi Formal (Sprint 3 Batch 2, 2026-09-22). */
class LaporanLabaRugiFormalExport implements FromArray, WithStyles
{
    use HasLaporanStyles;

    private int $totalRows = 0;

    public function __construct(private array $labaRugi, private string $cabangNama, private ?string $namaUser = null)
    {
    }

    public function array(): array
    {
        $lr = $this->labaRugi;
        $rows = [];
        $rows[] = ['Cabang', $this->cabangNama];
        $rows[] = [];
        $rows[] = ['Bagian', 'Kode', 'Nama', 'Jumlah'];

        $seksi = function ($label, $data) use (&$rows) {
            $rows[] = [$label, '', '', ''];
            foreach ($data['detail'] as $d) {
                $rows[] = ['', $d['kode'], $d['nama'], (float) $d['jumlah']];
            }
            $rows[] = ['Total ' . $label, '', '', (float) $data['total']];
        };

        $seksi('PENDAPATAN', $lr['pendapatan']);
        $seksi('HARGA POKOK PENJUALAN (HPP)', $lr['hpp']);
        $rows[] = ['LABA KOTOR', '', '', (float) $lr['laba_kotor']];
        $seksi('BEBAN OPERASIONAL', $lr['beban_operasional']);
        $rows[] = ['LABA USAHA', '', '', (float) $lr['laba_usaha']];
        $seksi('PENDAPATAN LAIN-LAIN', $lr['pendapatan_lain']);
        $seksi('BEBAN LAIN-LAIN', $lr['beban_lain']);
        $rows[] = ['LABA BERSIH SEBELUM PAJAK', '', '', (float) $lr['laba_bersih_sebelum_pajak']];
        $rows[] = ['Pajak Penghasilan', '', '', (float) $lr['pajak_penghasilan']];
        $rows[] = ['LABA BERSIH SETELAH PAJAK', '', '', (float) $lr['laba_bersih_setelah_pajak']];

        $this->totalRows = count($rows);

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A3:D3');
        $this->styleRupiahColumn($sheet, 'D');
        $this->autoSizeAllColumns($sheet, 'A', 'D');

        $barisFooter = $this->totalRows + 3;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
