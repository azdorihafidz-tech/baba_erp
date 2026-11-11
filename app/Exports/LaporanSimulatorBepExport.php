<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** Export Excel Snapshot Simulator BEP (Sprint 3 lanjutan, 2026-09-22) — 3 sheet: Parameter, Hasil, Sensitivitas. */
class LaporanSimulatorBepExport implements WithMultipleSheets
{
    public function __construct(private array $snapshot, private ?string $namaUser = null)
    {
    }

    public function sheets(): array
    {
        return [
            'Parameter Input' => new SimulatorBepParameterSheet($this->snapshot, $this->namaUser),
            'Hasil Perhitungan' => new SimulatorBepHasilSheet($this->snapshot, $this->namaUser),
            'Sensitivitas' => new SimulatorBepSensitivitasSheet($this->snapshot, $this->namaUser),
        ];
    }
}

/** @internal Sheet 1 — Parameter Input. */
class SimulatorBepParameterSheet implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithStyles
{
    use HasLaporanStyles;

    public function __construct(private array $s, private ?string $namaUser)
    {
    }

    public function array(): array
    {
        return [
            ['Item', 'Nilai'],
            ['Nama Simulasi', $this->s['namaSimulasi']],
            ['Tanggal Simulasi', now()->translatedFormat('d F Y')],
            ['Cabang', $this->s['cabangNama']],
            ['Volume Harian (kg)', $this->s['volumeHarian']],
            ['Harga Jual per kg (Rp)', $this->s['hargaJual']],
            ['Biaya Variabel per kg (Rp)', $this->s['biayaVariabel']],
            ['Beban Tetap Bulanan (Rp)', $this->s['bebanTetap']],
            ['Modal Awal (Rp)', $this->s['modalAwal']],
            ['Target Profit (Rp)', $this->s['targetProfit'] ?? '-'],
        ];
    }

    public function styles($sheet): array
    {
        $this->styleHeaderRow($sheet, 'A1:B1');
        $this->autoSizeAllColumns($sheet, 'A', 'B');

        $barisFooter = count($this->array()) + 2;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}

/** @internal Sheet 2 — Hasil Perhitungan + Kesimpulan. */
class SimulatorBepHasilSheet implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithStyles
{
    use HasLaporanStyles;

    public function __construct(private array $s, private ?string $namaUser)
    {
    }

    public function array(): array
    {
        $rows = [['Metric', 'Nilai']];

        if (!$this->s['feasible']) {
            $rows[] = ['Status', 'BEP TIDAK BISA DICAPAI (margin kontribusi negatif/nol)'];
        } else {
            $rows[] = ['BEP dalam Unit (kg)', round($this->s['bepUnit'], 2)];
            $rows[] = ['BEP dalam Rupiah', round($this->s['bepRupiah'], 0)];
            $rows[] = ['Margin Kontribusi per Unit (Rp)', round($this->s['margin'], 2)];
            $rows[] = ['Margin Kontribusi Ratio (%)', $this->s['marginRatio'] !== null ? round($this->s['marginRatio'], 1) : '-'];
            $rows[] = ['Volume Bulanan (kg)', round($this->s['volumeBulanan'], 2)];
            $rows[] = ['Omzet Bulanan (Rp)', round($this->s['omzetBulanan'], 0)];
            $rows[] = ['Laba/Rugi Bulanan (Rp)', round($this->s['labaBulanan'], 0)];
            $rows[] = ['Margin of Safety (%)', $this->s['marginOfSafetyPersen'] !== null ? round($this->s['marginOfSafetyPersen'], 1) : '-'];
        }

        $rows[] = [];
        $rows[] = ['Kesimpulan', ''];
        foreach ($this->s['kesimpulan'] as $baris) {
            $rows[] = ['', $baris];
        }

        return $rows;
    }

    public function styles($sheet): array
    {
        $this->styleHeaderRow($sheet, 'A1:B1');
        $this->autoSizeAllColumns($sheet, 'A', 'B');

        $barisFooter = count($this->array()) + 2;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}

/** @internal Sheet 3 — Matriks Sensitivitas BEP Unit (baris = %biaya variabel, kolom = %harga jual). */
class SimulatorBepSensitivitasSheet implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithStyles
{
    use HasLaporanStyles;

    public function __construct(private array $s, private ?string $namaUser)
    {
    }

    public function array(): array
    {
        $rows = [];
        $header = ['Biaya Variabel \\ Harga Jual'];
        foreach (array_keys($this->s['sensitivitas'][0]['kolom']) as $hargaPct) {
            $header[] = ($hargaPct >= 0 ? '+' : '') . $hargaPct . '%';
        }
        $rows[] = $header;

        foreach ($this->s['sensitivitas'] as $baris) {
            $row = [($baris['biaya_persen'] >= 0 ? '+' : '') . $baris['biaya_persen'] . '%'];
            foreach ($baris['kolom'] as $nilai) {
                $row[] = $nilai !== null ? round($nilai, 2) : 'N/A';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public function styles($sheet): array
    {
        $jumlahKolom = count($this->s['sensitivitas'][0]['kolom']) + 1;
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($jumlahKolom);

        $this->styleHeaderRow($sheet, 'A1:' . $lastCol . '1');
        $this->autoSizeAllColumns($sheet, 'A', $lastCol);

        // Highlight sel baseline (biaya 0%, harga 0%)
        $baselineRowIdx = null;
        foreach ($this->s['sensitivitas'] as $idx => $baris) {
            if ($baris['biaya_persen'] === 0) {
                $baselineRowIdx = $idx + 2;
                break;
            }
        }
        $baselineColIdx = array_search(0, array_keys($this->s['sensitivitas'][0]['kolom']), true);
        if ($baselineRowIdx !== null && $baselineColIdx !== false) {
            $baselineCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($baselineColIdx + 2);
            $sheet->getStyle($baselineCol . $baselineRowIdx)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB(ltrim(app(\App\Support\Branding::class)->warna('latar'), '#')); // 4.48 — ikut Warna Latar
            $sheet->getStyle($baselineCol . $baselineRowIdx)->getFont()->setBold(true);
        }

        $totalBaris = count($this->s['sensitivitas']) + 1;
        $barisFooter = $totalBaris + 3;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
