<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * 4.46 — Excel Laporan Penjualan Produk / Kategori (menu Penjualan). Baris
 * rincian (varian per produk, atau produk per kategori) ditulis menjorok di
 * bawah induknya. Kolom Modal/Keuntungan/Margin hanya kalau berhak.
 */
class LaporanBarangKasirExport implements FromArray, WithStyles, WithTitle
{
    use HasLaporanStyles;

    private const BARIS_HEADER = 5;
    private array $header = [];
    private array $barisTebal = [];
    private array $barisAnak = [];
    private int $barisFooter = 0;

    public function __construct(
        private string $jenis,
        private string $judul,
        private array $info,
        private Collection $baris,
        private array $total,
        private float $biayaTambahan,
        private bool $lihatUntung,
        private ?string $namaUser = null,
    ) {
    }

    public function title(): string
    {
        return $this->jenis === 'produk' ? 'Penjualan Produk' : 'Penjualan Kategori';
    }

    public function array(): array
    {
        $rows = [[strtoupper($this->judul)]];
        foreach ($this->info as $k => $v) {
            $rows[] = ["{$k}: {$v}"];
        }
        while (count($rows) < self::BARIS_HEADER - 1) {
            $rows[] = [];
        }

        $this->header = $this->jenis === 'produk'
            ? ['No', 'Kode', 'Produk', 'Kategori', 'Qty', 'Satuan', 'Penjualan (sebelum diskon)', 'Diskon', 'Pendapatan']
            : ['No', 'Kategori', 'Jumlah Produk', 'Qty', 'Penjualan (sebelum diskon)', 'Diskon', 'Pendapatan'];
        if ($this->lihatUntung) {
            array_push($this->header, 'Modal (HPP)', 'Keuntungan', 'Margin %');
        }
        $rows[] = $this->header;

        $anakKey = $this->jenis === 'produk' ? 'varian' : 'produk';
        foreach ($this->baris->values() as $i => $r) {
            $rows[] = $this->baris($r, (string) ($i + 1), false);
            foreach (($r[$anakKey] ?? collect()) as $anak) {
                $rows[] = $this->baris($anak, '', true);
                $this->barisAnak[] = count($rows);
            }
        }

        $t = $this->total;
        $total = $this->jenis === 'produk'
            ? ['TOTAL', '', '', '', $t['qty'], '', $t['penjualan'], $t['diskon'], $t['pendapatan']]
            : ['TOTAL', '', '', $t['qty'], $t['penjualan'], $t['diskon'], $t['pendapatan']];
        if ($this->lihatUntung) {
            array_push($total, $this->total['hpp'], $this->total['keuntungan'],
                $this->total['pendapatan'] > 0 ? round($this->total['keuntungan'] / $this->total['pendapatan'] * 100, 1) : null);
        }
        $rows[] = $total;
        $this->barisTebal[] = count($rows);

        if ($this->biayaTambahan > 0) {
            $idx = array_search('Pendapatan', $this->header, true);
            $r1 = array_fill(0, $idx + 1, '');
            $r1[0] = 'Service charge & take away fee (bukan milik produk mana pun)';
            $r1[$idx] = $this->biayaTambahan;
            $r2 = array_fill(0, $idx + 1, '');
            $r2[0] = 'Total pendapatan (sama dengan Laporan Transaksi)';
            $r2[$idx] = $this->total['pendapatan'] + $this->biayaTambahan;
            $rows[] = $r1;
            $rows[] = $r2;
            $this->barisTebal[] = count($rows);
        }

        $rows[] = [];
        $rows[] = [$this->footerDicetakOleh($this->namaUser)];
        $this->barisFooter = count($rows);

        return $rows;
    }

    private function baris(array $r, string $no, bool $anak): array
    {
        $nama = ($anak ? '    • ' : '') . ($this->jenis === 'produk' || $anak ? $r['nama'] : $r['kategori']);
        $row = $this->jenis === 'produk'
            ? [$no, $anak ? '' : ($r['kode'] ?? ''), $nama, $anak ? '' : $r['kategori'], $r['qty'], $anak ? '' : ($r['satuan'] ?? ''),
                $r['penjualan'], $r['diskon'], $r['pendapatan']]
            : [$no, $nama, $anak ? '' : $r['jumlah_produk'], $r['qty'], $r['penjualan'], $r['diskon'], $r['pendapatan']];
        if ($this->lihatUntung) {
            array_push($row, $r['hpp'], $r['keuntungan'], $r['margin']);
        }

        return $row;
    }

    public function styles(Worksheet $sheet): array
    {
        $akhir = chr(64 + count($this->header));
        $h = self::BARIS_HEADER;
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $this->styleHeaderRow($sheet, "A{$h}:{$akhir}{$h}");

        foreach (['Penjualan (sebelum diskon)', 'Diskon', 'Pendapatan', 'Modal (HPP)', 'Keuntungan'] as $kol) {
            $idx = array_search($kol, $this->header, true);
            if ($idx !== false) {
                $this->styleRupiahColumn($sheet, chr(65 + $idx));
            }
        }
        $idxQty = array_search('Qty', $this->header, true);
        $sheet->getStyle(chr(65 + $idxQty) . ':' . chr(65 + $idxQty))->getNumberFormat()->setFormatCode('#,##0.###');

        foreach ($this->barisTebal as $b) {
            $sheet->getStyle("A{$b}:{$akhir}{$b}")->getFont()->setBold(true);
        }
        foreach ($this->barisAnak as $b) {
            $sheet->getStyle("A{$b}:{$akhir}{$b}")->getFont()->setItalic(true)->getColor()->setRGB('64748B');
        }
        $this->autoSizeAllColumns($sheet, 'B', $akhir);
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getStyle('A' . $this->barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
