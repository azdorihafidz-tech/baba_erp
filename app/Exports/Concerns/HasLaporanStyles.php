<?php

namespace App\Exports\Concerns;

use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sprint 3 Batch 1a D'mentai (2026-09-21) — style konsisten dipakai SEMUA
 * Export class laporan (bukan cuma yang lama Kas/Aset/Keuangan) supaya
 * tampilan Excel seragam lintas menu: header bold + fill krim brand
 * (#FFF8E7), auto-width kolom, format Rupiah/tanggal Indonesia, baris
 * footer "Dicetak oleh".
 */
trait HasLaporanStyles
{
    /**
     * Bold + fill krim brand utk 1 baris header (mis. 'A1:G1').
     */
    protected function styleHeaderRow(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB(ltrim(app(\App\Support\Branding::class)->warna('latar'), '#')); // 4.48 — ikut Warna Latar
    }

    /**
     * Auto-size semua kolom dari huruf $dari sampai $sampai (default A-Z
     * kalau tidak disebut eksplisit -- PhpSpreadsheet aman dipanggil ke
     * kolom yang genuinely tidak dipakai, cuma no-op).
     */
    protected function autoSizeAllColumns(Worksheet $sheet, string $dari = 'A', string $sampai = 'Z'): void
    {
        foreach (range($dari, $sampai) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Format 1 kolom (mis. 'F') sbg Rupiah -- angka TETAP numerik asli
     * (bukan text "Rp ..."), cuma DITAMPILKAN dgn prefix "Rp" + pemisah
     * ribuan via number format mask Excel. Sengaja BUKAN string formatting
     * manual spy kolom tetap bisa di-SUM/sort/filter normal di Excel.
     */
    protected function styleRupiahColumn(Worksheet $sheet, string $kolom): void
    {
        $sheet->getStyle($kolom . ':' . $kolom)->getNumberFormat()->setFormatCode('"Rp" #,##0');
    }

    /**
     * Format tanggal gaya Indonesia lengkap, mis. "21 September 2026".
     * Dipakai di judul/footer export (BUKAN kolom tabel tanggal per-baris,
     * yang tetap pakai d/m/Y singkat spy kolom tidak melebar).
     */
    protected function formatTanggalIndonesia(\DateTimeInterface $tanggal): string
    {
        return \Carbon\Carbon::instance($tanggal)->translatedFormat('d F Y');
    }

    /**
     * Baris footer "Dicetak oleh: {user} pada {tanggal}" -- dipanggil dari
     * array() masing-masing Export class (FromArray) sbg baris terakhir,
     * ATAU dari sheets()/collection() based export lewat AfterSheet event
     * kalau strukturnya WithMapping (lihat implementasi per-class).
     */
    protected function footerDicetakOleh(?string $namaUser): string
    {
        $nama = $namaUser ?: 'Sistem';

        return 'Dicetak oleh: ' . $nama . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB';
    }
}
