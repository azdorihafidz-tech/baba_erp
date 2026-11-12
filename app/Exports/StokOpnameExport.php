<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use App\Services\StokOpnameService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** ERP BABA Tahap 7b — Berita Acara Stok Opname (Excel): kepala, rincian per barang, ringkasan, per penyebab, tanda tangan. */
class StokOpnameExport implements FromArray, WithStyles, WithTitle
{
    use HasLaporanStyles;

    private int $barisJudul = 0;

    public function __construct(private array $ba, private ?string $namaUser = null)
    {
    }

    public function title(): string
    {
        return $this->ba['o']->nomor;
    }

    public function array(): array
    {
        $o = $this->ba['o'];
        $rg = $this->ba['ringkasan'];
        $out = [
            ['BERITA ACARA STOK OPNAME' . ($this->ba['final'] ? '' : ' — SEMENTARA (belum disetujui)')],
            ['Nomor', $o->nomor], ['Lokasi', $o->cabang?->nama_cabang], ['Cakupan', (\App\Models\StokOpname::CAKUPAN[$o->cakupan] ?? $o->cakupan) . ($o->cakupan_ket ? ' — ' . $o->cakupan_ket : '')],
            ['Dimulai', $o->created_at?->format('d/m/Y H:i') . ' oleh ' . ($o->pembuat?->name ?? '-')],
            ['Penghitung', implode(', ', $this->ba['penghitung'])], ['Diajukan', $o->diajukan_at ? $o->diajukan_at->format('d/m/Y H:i') . ' oleh ' . $o->pengaju?->name : '-'],
            ['Disetujui', $o->disetujui_at ? $o->disetujui_at->format('d/m/Y H:i') . ' oleh ' . $o->penyetuju?->name : '-'], ['Status', $o->status_label], [],
        ];
        $this->barisJudul = count($out) + 1;
        $out[] = ['Kode', 'Barang', 'Kategori', 'Satuan', 'Stok sistem', 'Fisik', 'Selisih', 'Harga pokok', 'Nilai selisih', 'Penyebab', 'Dihitung', 'Penghitung'];
        foreach ($this->ba['baris'] as $b) {
            $out[] = [$b['kode'], $b['nama'], $b['kategori'], $b['satuan'], $b['sistem'], $b['fisik'], $b['selisih'], round((float) $b['harga'], 2), $b['nilai'], $b['penyebab'], $b['hitung_ke'] . 'x', $b['penghitung']];
        }
        $out[] = [];
        $out[] = ['RINGKASAN'];
        foreach ([['Barang dihitung', $rg['dihitung']], ['Belum dihitung', $rg['belum']], ['Cocok', $rg['cocok']], ['Kurang', $rg['kurang']], ['Lebih', $rg['lebih']],
            ['Nilai kurang', $rg['nilai_kurang']], ['Nilai lebih', $rg['nilai_lebih']], ['Selisih bersih', $rg['nilai_bersih']], ['Akurasi stok (%)', $rg['akurasi']]] as $r) {
            $out[] = $r;
        }
        $out[] = [];
        $out[] = ['PER PENYEBAB', 'Pos keuangan', 'Nilai'];
        foreach ($this->ba['per_penyebab'] as $k => $p) {
            $out[] = [StokOpnameService::PENYEBAB[$k]['label'] ?? $k, (StokOpnameService::PENYEBAB[$k]['coa'] ?? '') . ' ' . (StokOpnameService::PENYEBAB[$k]['nama'] ?? ''), round((float) $p['nilai'], 2)];
        }
        $out[] = [];
        $out[] = ['Penghitung', '', 'Pemeriksa', '', 'Disetujui'];
        $out[] = [];
        $out[] = [];
        $out[] = [implode(', ', $this->ba['penghitung']) ?: '(.................)', '', $o->pengaju?->name ?? '(.................)', '', $o->penyetuju?->name ?? '(.................)'];

        return $out;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $this->styleHeaderRow($sheet, "A{$this->barisJudul}:L{$this->barisJudul}");
        $this->autoSizeAllColumns($sheet, 'A', 'L');
        $sheet->setCellValue('A' . ($sheet->getHighestRow() + 2), $this->footerDicetakOleh($this->namaUser));

        return [];
    }
}
