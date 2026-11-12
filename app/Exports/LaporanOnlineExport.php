<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** ERP BABA Tahap 4 — Excel Laporan Belanja Online (satu tabel sesuai jenis laporan yang dibuka). */
class LaporanOnlineExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    use HasLaporanStyles;

    /** @param array<int, string> $judulKolom  @param array<int, array> $baris */
    public function __construct(private string $judul, private array $judulKolom, private array $baris, private ?string $namaUser = null)
    {
    }

    public function array(): array
    {
        return $this->baris;
    }

    public function headings(): array
    {
        return $this->judulKolom;
    }

    public function title(): string
    {
        return mb_substr(preg_replace('/[^\pL\pN ]+/u', ' ', $this->judul), 0, 31);
    }

    public function styles(Worksheet $sheet): array
    {
        $akhir = Coordinate::stringFromColumnIndex(max(1, count($this->judulKolom)));
        $this->styleHeaderRow($sheet, "A1:{$akhir}1");
        $this->autoSizeAllColumns($sheet, 'A', $akhir);
        $sheet->setCellValue('A' . (count($this->baris) + 3), $this->footerDicetakOleh($this->namaUser));

        return [];
    }
}
