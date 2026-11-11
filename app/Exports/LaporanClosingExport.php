<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Export Excel Closing / Tutup Buku — preview maupun arsip. Sheet:
 * Ringkasan (penjualan + laba rugi), Arus Kas, Saldo Kas (+ uang fisik),
 * Mutasi Internal, Per Cabang (hanya keseluruhan).
 *
 * @param array $data      hasil ClosingService::hitung() / snapshot JSON
 * @param array $info      ['Judul' => ..., 'Periode' => ..., 'Cabang' => ..., 'Status' => ...]
 * @param array $kasFisik  [kas_id => ['saldo_fisik' => x, 'selisih' => y]]
 */
class LaporanClosingExport implements WithMultipleSheets
{
    public function __construct(private array $data, private array $info, private array $kasFisik = [], private ?string $namaUser = null)
    {
    }

    public function sheets(): array
    {
        $d = $this->data;
        $sheets = [
            $this->ringkasan($d),
            $this->arusKas($d),
            $this->saldoKas($d),
            $this->mutasi($d),
        ];
        if (! empty($d['per_cabang'])) {
            $sheets[] = $this->perCabang($d);
        }

        return $sheets;
    }

    private function kepala(): array
    {
        $rows = [];
        foreach ($this->info as $label => $nilai) {
            $rows[] = [$label, $nilai];
        }
        $rows[] = [''];

        return $rows;
    }

    private function ringkasan(array $d): ClosingSheet
    {
        $pj = $d['penjualan'];
        $lr = $d['laba_rugi'];
        $rows = $this->kepala();
        $head = [];
        $rows[] = ['PENJUALAN', 'Nilai']; $head[] = count($rows);
        $rows[] = ['Jumlah transaksi', $pj['jumlah_order']];
        $rows[] = ['Omzet kotor', $pj['omzet_kotor']];
        $rows[] = ['Diskon', -$pj['diskon']];
        $rows[] = ['Service charge', $pj['service_charge']];
        $rows[] = ['Take away fee', $pj['take_away_fee']];
        $rows[] = ['Omzet bersih', $pj['omzet_bersih']];
        foreach ($pj['per_metode'] as $m) {
            $rows[] = ['  Metode: ' . $m['label'], $m['total']];
        }
        foreach ($pj['per_tipe'] as $t) {
            $rows[] = ['  Tipe: ' . $t['label'] . ' (' . $t['jumlah_order'] . ' trx)', $t['total']];
        }
        $rows[] = [''];
        $rows[] = ['LABA RUGI', 'Nilai']; $head[] = count($rows);
        $rows[] = ['Omzet bersih', $lr['omzet']];
        $rows[] = ['HPP', -$lr['hpp']];
        if ((float) ($lr['eliminasi_omzet'] ?? 0) > 0) {
            $rows[] = ['  (kiriman Gudang → cabang tidak dihitung: omzet ' . number_format((float) $lr['eliminasi_omzet'], 0, ',', '.') . ', HPP ' . number_format((float) $lr['eliminasi_hpp'], 0, ',', '.') . ')'];
        }
        $rows[] = ['Laba kotor', $lr['laba_kotor']];
        foreach ($lr['pendapatan_lain'] as $r) {
            $rows[] = ['  + ' . $r['label'], $r['total']];
        }
        foreach ($lr['beban'] as $r) {
            $rows[] = ['  − ' . $r['label'], -$r['total']];
        }
        $rows[] = ['Laba bersih', $lr['laba_bersih']];
        $rows[] = [''];
        $rows[] = ['NILAI PERSEDIAAN STOK', 'Nilai']; $head[] = count($rows);
        $rows[] = ['Bahan baku & tambahan', $d['stok']['bahan_baku']];
        $rows[] = ['Kemasan', $d['stok']['kemasan']];
        $rows[] = ['Barang jadi', $d['stok']['barang_jadi']];
        $rows[] = ['Total', $d['stok']['total']];

        return new ClosingSheet('Ringkasan', $rows, $head, ['B'], 'B', $this->namaUser, [count($this->info) + 3]);
    }

    private function arusKas(array $d): ClosingSheet
    {
        $ak = $d['arus_kas'];
        $rows = $this->kepala();
        $head = [];
        $rows[] = ['PEMASUKAN', 'Jumlah']; $head[] = count($rows);
        foreach ($ak['pemasukan'] as $r) {
            $rows[] = [$r['label'], $r['total']];
        }
        $rows[] = ['Total pemasukan', $ak['total_pemasukan']];
        $rows[] = [''];
        $rows[] = ['PENGELUARAN', 'Jumlah']; $head[] = count($rows);
        foreach ($ak['pengeluaran'] as $r) {
            $rows[] = [$r['label'], $r['total']];
        }
        $rows[] = ['Total pengeluaran', $ak['total_pengeluaran']];
        $rows[] = [''];
        $rows[] = ['Arus kas bersih', $ak['net']];

        return new ClosingSheet('Arus Kas', $rows, $head, ['B'], 'B', $this->namaUser);
    }

    private function saldoKas(array $d): ClosingSheet
    {
        $rows = $this->kepala();
        $rows[] = ['Kas', 'Cabang', 'Grup', 'Saldo Awal', 'Masuk', 'Keluar', 'Mutasi Masuk', 'Mutasi Keluar', 'Saldo Akhir', 'Uang Fisik', 'Selisih'];
        $head = [count($rows)];
        foreach ($d['saldo_kas']['rows'] as $r) {
            $f = $this->kasFisik[$r['kas_id']] ?? null;
            $rows[] = [
                $r['nama_kas'], $r['cabang'], $r['grup'] === 'tunai' ? 'Tunai' : 'Bank',
                $r['saldo_awal'], $r['masuk'], $r['keluar'], $r['mutasi_masuk'], $r['mutasi_keluar'], $r['saldo_akhir'],
                $f['saldo_fisik'] ?? null, $f['selisih'] ?? null,
            ];
        }
        $t = $d['saldo_kas']['total'];
        $rows[] = ['TOTAL', '', '', $t['saldo_awal'], $t['masuk'], $t['keluar'], $t['mutasi_masuk'], $t['mutasi_keluar'], $t['saldo_akhir']];

        return new ClosingSheet('Saldo Kas', $rows, $head, ['D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'], 'K', $this->namaUser);
    }

    private function mutasi(array $d): ClosingSheet
    {
        $mt = $d['mutasi'];
        $rows = $this->kepala();
        $rows[] = ['Jenis Mutasi', 'Keluar', 'Masuk', 'Belum Diterima'];
        $head = [count($rows)];
        foreach ($mt['ringkasan'] as $g) {
            $rows[] = [$g['label'], $g['keluar'], $g['masuk'], $g['dalam_perjalanan']];
        }
        $rows[] = [''];
        $rows[] = ['Tanggal', 'Jenis', 'Dari', 'Ke', 'Jumlah', 'Status'];
        $head[] = count($rows);
        foreach ($mt['detail'] as $x) {
            $rows[] = [$x['tanggal'], $x['jenis'], $x['dari'], $x['ke'], $x['jumlah'], $x['status']];
        }

        return new ClosingSheet('Mutasi Internal', $rows, $head, ['B', 'C', 'D', 'E'], 'F', $this->namaUser);
    }

    private function perCabang(array $d): ClosingSheet
    {
        $rows = $this->kepala();
        $rows[] = ['Cabang', 'Transaksi', 'Omzet', 'HPP', 'Laba Kotor', 'Pendapatan Lain', 'Beban', 'Laba Bersih'];
        $head = [count($rows)];
        foreach ($d['per_cabang'] as $c) {
            $rows[] = [$c['nama_cabang'], $c['jumlah_order'], $c['omzet'], $c['hpp'], $c['laba_kotor'], $c['pendapatan_lain'], $c['beban'], $c['laba_bersih']];
        }

        return new ClosingSheet('Per Cabang', $rows, $head, ['C', 'D', 'E', 'F', 'G', 'H'], 'H', $this->namaUser);
    }
}

/** @internal 1 sheet generik laporan closing. */
class ClosingSheet implements FromArray, WithStyles, WithTitle
{
    use HasLaporanStyles;

    /**
     * @param int[] $headerRows  nomor baris (1-based) yang di-style sebagai header
     * @param string[] $rupiahCols kolom berformat Rupiah
     * @param int[] $plainRows   baris yang dikecualikan dari format Rupiah (mis. jumlah transaksi)
     */
    public function __construct(
        private string $judul,
        private array $rows,
        private array $headerRows,
        private array $rupiahCols,
        private string $kolomAkhir,
        private ?string $namaUser,
        private array $plainRows = [],
    ) {
    }

    public function title(): string
    {
        return $this->judul;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function styles($sheet): array
    {
        foreach ($this->headerRows as $r) {
            $this->styleHeaderRow($sheet, "A{$r}:{$this->kolomAkhir}{$r}");
        }
        foreach ($this->rupiahCols as $col) {
            $this->styleRupiahColumn($sheet, $col);
        }
        foreach ($this->plainRows as $r) {
            $sheet->getStyle("B{$r}")->getNumberFormat()->setFormatCode('#,##0');
        }
        $this->autoSizeAllColumns($sheet, 'A', $this->kolomAkhir);

        $barisFooter = count($this->rows) + 2;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
