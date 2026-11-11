<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel Laporan Pergerakan Stok. Sprint 3 Batch 1b/1c, 2026-09-21.
 * Kolom "Sisa Stok" & "Referensi" SENGAJA TIDAK ADA -- data belum ditrack
 * sistem (butuh running-balance + kolom referensi_type/id baru di
 * stock_movements, dicatat sbg TODO Sprint terpisah, lihat CLAUDE.md
 * [[12.14]]). Cabang di sini bisa lokasi asal ATAU tujuan tergantung tipe.
 */
class LaporanStokPergerakanExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    use HasLaporanStyles;

    public function __construct(private Collection $movements, private ?string $namaUser = null)
    {
    }

    public function collection(): Collection
    {
        return $this->movements;
    }

    public function headings(): array
    {
        return ['Tanggal', 'Kode', 'Bahan', 'Cabang', 'Tipe', 'Qty', 'Satuan', 'Catatan'];
    }

    public function map($m): array
    {
        /** @var StockMovement $m */
        $cabangNama = $m->tipe?->value === 'keluar'
            ? ($m->lokasiAsal?->nama_cabang ?? '-')
            : ($m->lokasiTujuan?->nama_cabang ?? $m->lokasiAsal?->nama_cabang ?? '-');

        return [
            $m->created_at?->format('d/m/Y H:i') ?? '-',
            $m->item?->kode_item ?? '-',
            $m->item?->nama_item ?? '-',
            $cabangNama,
            ucfirst($m->tipe?->value ?? '-'),
            (float) $m->qty,
            $m->item?->satuan ?? '-',
            $m->catatan ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A1:H1');
        $this->autoSizeAllColumns($sheet, 'A', 'H');

        $barisFooter = $this->movements->count() + 3;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
