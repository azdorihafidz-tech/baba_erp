<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Export Excel Laporan Neraca (Sprint 3 Batch 2, 2026-09-22). */
class LaporanNeracaExport implements FromArray, WithStyles
{
    use HasLaporanStyles;

    private array $rupiahRows = [];
    private int $totalRows = 0;

    public function __construct(private array $neraca, private string $cabangNama, private ?string $namaUser = null)
    {
    }

    public function array(): array
    {
        $n = $this->neraca;
        $rows = [];
        $rows[] = ['Cabang', $this->cabangNama];
        $rows[] = ['Per Tanggal', \Carbon\Carbon::parse($n['tanggal'])->translatedFormat('d F Y')];
        $rows[] = [];
        $rows[] = ['Bagian', 'Item', 'Nilai'];

        $tambah = function ($bagian, $item, $nilai) use (&$rows) {
            $rows[] = [$bagian, $item, (float) $nilai];
            $this->rupiahRows[] = count($rows);
        };

        $tambah('ASET LANCAR', 'Kas & Setara Kas', $n['aset']['lancar']['kas_setara']);
        $tambah('ASET LANCAR', 'Piutang Usaha', $n['aset']['lancar']['piutang']);
        $tambah('ASET LANCAR', 'Persediaan Bahan Baku', $n['aset']['lancar']['persediaan_bahan_baku']);
        $tambah('ASET LANCAR', 'Persediaan Barang Jadi', $n['aset']['lancar']['persediaan_barang_jadi']);
        $tambah('ASET LANCAR', 'Persediaan Kemasan', $n['aset']['lancar']['persediaan_kemasan']);
        $tambah('ASET LANCAR', 'Total Aset Lancar', $n['aset']['lancar']['total']);
        $tambah('ASET TETAP', 'Aset Bruto', $n['aset']['tetap']['aset_bruto']);
        $tambah('ASET TETAP', 'Akumulasi Depresiasi', -$n['aset']['tetap']['akumulasi_depresiasi']);
        $tambah('ASET TETAP', 'Nilai Buku Aset Tetap', $n['aset']['tetap']['nilai_buku']);
        $tambah('TOTAL', 'TOTAL ASET', $n['aset']['total_aset']);

        $tambah('KEWAJIBAN JANGKA PENDEK', 'Hutang Usaha', $n['kewajiban']['jangka_pendek']['hutang_usaha']);
        $tambah('KEWAJIBAN JANGKA PENDEK', 'Hutang Pajak', $n['kewajiban']['jangka_pendek']['hutang_pajak']);
        $tambah('KEWAJIBAN JANGKA PENDEK', 'Total Jangka Pendek', $n['kewajiban']['jangka_pendek']['total']);
        $tambah('KEWAJIBAN JANGKA PANJANG', 'Hutang Bank', $n['kewajiban']['jangka_panjang']['hutang_bank']);
        $tambah('KEWAJIBAN JANGKA PANJANG', 'Total Jangka Panjang', $n['kewajiban']['jangka_panjang']['total']);
        $tambah('TOTAL', 'TOTAL KEWAJIBAN', $n['kewajiban']['total_kewajiban']);

        $tambah('MODAL', 'Modal Owner', $n['modal']['modal_owner']);
        $tambah('MODAL', 'Laba Ditahan', $n['modal']['laba_ditahan']);
        $tambah('TOTAL', 'TOTAL MODAL', $n['modal']['total_modal']);
        $tambah('TOTAL', 'TOTAL KEWAJIBAN + MODAL', $n['total_kewajiban_modal']);

        $rows[] = [];
        $rows[] = ['Status Balance', $n['balance_check'] ? 'BALANCE' : 'BELUM BALANCE (Selisih Rp ' . number_format($n['selisih'], 0, ',', '.') . ')'];

        $this->totalRows = count($rows);

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A4:C4');
        foreach ($this->rupiahRows as $r) {
            $this->styleRupiahColumn($sheet, 'C');
        }
        $this->autoSizeAllColumns($sheet, 'A', 'C');

        $barisFooter = $this->totalRows + 3;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
