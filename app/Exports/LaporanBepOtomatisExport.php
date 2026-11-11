<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Export Excel Laporan BEP Otomatis (Sprint 3 Batch 2, 2026-09-22). Ringkasan key-value + breakdown biaya tetap. */
class LaporanBepOtomatisExport implements FromArray, WithStyles
{
    use HasLaporanStyles;

    private int $headerRowUtama = 1;
    private int $headerRowBiaya = 1;
    private int $totalRows = 0;

    public function __construct(private array $bep, private string $cabangNama, private ?string $namaUser = null)
    {
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = ['Cabang', $this->cabangNama];
        $rows[] = [];

        $this->headerRowUtama = count($rows) + 1;
        $rows[] = ['Komponen Perhitungan', 'Nilai'];
        $rows[] = ['BEP Unit (kg)', (float) ($this->bep['bep_unit'] ?? 0)];
        $rows[] = ['BEP Rupiah', (float) ($this->bep['bep_rupiah'] ?? 0)];
        $rows[] = ['Volume Aktual (kg)', (float) ($this->bep['volume_aktual'] ?? 0)];
        $rows[] = ['% Tercapai', $this->bep['persentase_tercapai'] !== null ? round((float) $this->bep['persentase_tercapai'], 1) : '-'];
        $rows[] = ['Biaya Tetap', (float) ($this->bep['biaya_tetap'] ?? 0)];
        $rows[] = ['Biaya Variabel / kg', (float) ($this->bep['biaya_variabel_per_unit'] ?? 0)];
        $rows[] = ['Harga Jual rata-rata / kg', (float) ($this->bep['harga_jual_per_unit'] ?? 0)];
        $rows[] = ['Margin Kontribusi / kg', (float) ($this->bep['margin_kontribusi_per_unit'] ?? 0)];
        $rows[] = ['Total HPP', (float) ($this->bep['total_hpp_jasa_giling'] ?? 0)];
        $rows[] = ['Total Omzet', (float) ($this->bep['total_omzet_jasa_giling'] ?? 0)];
        $rows[] = ['Margin of Safety (%)', $this->bep['margin_of_safety_persen'] !== null ? round((float) $this->bep['margin_of_safety_persen'], 1) : '-'];
        $rows[] = ['Estimasi Laba', (float) ($this->bep['estimasi_laba'] ?? 0)];
        $rows[] = [];

        $this->headerRowBiaya = count($rows) + 1;
        $rows[] = ['Kategori Biaya Tetap', 'Jumlah'];
        foreach (($this->bep['biaya_tetap_detail'] ?? []) as $d) {
            $rows[] = [$d['nama_kategori'], (float) $d['jumlah']];
        }
        if (empty($this->bep['biaya_tetap_detail'])) {
            $rows[] = ['Tidak ada transaksi biaya tetap di periode ini.', ''];
        }

        $this->totalRows = count($rows);

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A' . $this->headerRowUtama . ':B' . $this->headerRowUtama);
        $this->styleHeaderRow($sheet, 'A' . $this->headerRowBiaya . ':B' . $this->headerRowBiaya);
        $this->autoSizeAllColumns($sheet, 'A', 'B');

        $barisFooter = $this->totalRows + 2;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
