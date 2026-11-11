<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * 4.46 — Excel Laporan Transaksi (menu Penjualan), isi = halaman yang sedang
 * dibuka (rekap per tahun/bulan/hari, atau daftar transaksi 1 hari). Kolom
 * HPP & Keuntungan hanya ada kalau user berhak melihat keuntungan.
 */
class LaporanTransaksiKasirExport implements FromArray, WithStyles, WithTitle
{
    use HasLaporanStyles;

    private int $barisHeader = 4;
    private int $jumlahKolom = 0;
    private array $kolomRupiah = [];
    private int $jumlahData = 0;

    public function __construct(
        private string $level,
        private Collection $baris,
        private array $total,
        private string $judul,
        private string $cabangNama,
        private bool $lihatUntung,
        private bool $semuaCabang,
        private ?string $namaUser = null,
    ) {
    }

    public function title(): string
    {
        return 'Laporan Transaksi';
    }

    public function array(): array
    {
        $rows = [
            ['LAPORAN TRANSAKSI'],
            ['Periode: ' . $this->judul],
            ['Cabang: ' . $this->cabangNama],
        ];

        if ($this->level === 'transaksi') {
            $header = ['Jam', 'No. Order'];
            if ($this->semuaCabang) {
                $header[] = 'Cabang';
            }
            array_push($header, 'Pelanggan', 'Tipe', 'Metode Bayar', 'Pendapatan');
        } else {
            $header = [match ($this->level) { 'tahun' => 'Tahun', 'bulan' => 'Bulan', default => 'Tanggal' }, 'Jumlah Transaksi', 'Pendapatan'];
        }
        if ($this->lihatUntung) {
            array_push($header, 'Modal (HPP)', 'Keuntungan');
        }
        $rows[] = $header;
        $this->jumlahKolom = count($header);

        foreach ($this->baris as $r) {
            if ($this->level === 'transaksi') {
                $row = [$r['jam'], $r['nomor']];
                if ($this->semuaCabang) {
                    $row[] = $r['cabang'];
                }
                array_push($row, $r['pelanggan'], $r['tipe'], $r['metode'], $r['pendapatan']);
            } else {
                $row = [$r['label'], $r['transaksi'], $r['pendapatan']];
            }
            if ($this->lihatUntung) {
                array_push($row, $r['hpp'], $r['keuntungan']);
            }
            $rows[] = $row;
        }
        $this->jumlahData = $this->baris->count();

        $jmlKolomLabel = $this->level === 'transaksi' ? count($header) - ($this->lihatUntung ? 3 : 1) : 1;
        $totalRow = array_fill(0, $jmlKolomLabel, '');
        $totalRow[0] = 'TOTAL';
        if ($this->level !== 'transaksi') {
            $totalRow[] = $this->total['transaksi'];
        } else {
            $totalRow[0] = 'TOTAL (' . $this->total['transaksi'] . ' transaksi)';
        }
        $totalRow[] = $this->total['pendapatan'];
        if ($this->lihatUntung) {
            array_push($totalRow, $this->total['hpp'], $this->total['keuntungan']);
        }
        $rows[] = $totalRow;

        $rows[] = [];
        $rows[] = [$this->footerDicetakOleh($this->namaUser)];

        // Kolom rupiah = kolom "Pendapatan" sampai akhir
        $idxPendapatan = array_search('Pendapatan', $header, true);
        for ($i = $idxPendapatan; $i < count($header); $i++) {
            $this->kolomRupiah[] = chr(65 + $i);
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $akhir = chr(64 + $this->jumlahKolom);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $this->styleHeaderRow($sheet, "A{$this->barisHeader}:{$akhir}{$this->barisHeader}");

        $barisTotal = $this->barisHeader + $this->jumlahData + 1;
        $sheet->getStyle("A{$barisTotal}:{$akhir}{$barisTotal}")->getFont()->setBold(true);
        foreach ($this->kolomRupiah as $kol) {
            $this->styleRupiahColumn($sheet, $kol);
        }
        $this->autoSizeAllColumns($sheet, 'A', $akhir);
        $sheet->getStyle('A' . ($barisTotal + 2))->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
