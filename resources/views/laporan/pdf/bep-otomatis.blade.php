<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>BEP Otomatis — {{ $cabangNama }} — {{ $mulai->format('d-m-Y') }} s.d {{ $akhir->format('d-m-Y') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; color: #1e293b; }
        table.layout { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; padding-bottom: 10px; }
        .logo { width: 55px; height: 55px; }
        .company-name { font-size: 16px; font-weight: bold; color: #1e293b; }
        .company-sub { font-size: 9px; color: #64748b; }
        .report-title { text-align: right; font-size: 14px; font-weight: bold; color: {{ $brand->warna('utama') }}; }
        .report-meta { text-align: right; font-size: 9px; color: #64748b; margin-top: 3px; }
        .divider { border-bottom: 2px solid {{ $brand->warna('utama') }}; margin-bottom: 12px; }

        .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .summary-table td { border: 1px solid #e2e8f0; padding: 8px; text-align: center; width: 25%; }
        .summary-table .val { font-size: 13px; font-weight: bold; }
        .summary-table .lbl { font-size: 9px; color: #64748b; margin-top: 2px; }

        table.data { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.data th { background: #f1f5f9; padding: 5px 6px; text-align: left; font-size: 10px; border-bottom: 1px solid #cbd5e1; }
        table.data td { padding: 4px 6px; font-size: 10px; border-bottom: 1px solid #f1f5f9; }
        table.data td.num { text-align: right; }
        tr.grand td { background: #dbeafe; font-weight: bold; }

        .footer-note { font-size: 8.5px; color: #94a3b8; margin-top: 4px; }
        .signature-table { width: 100%; margin-top: 40px; }
        .signature-table td { text-align: center; font-size: 10px; padding-top: 50px; }
        .signature-line { border-top: 1px solid #1e293b; display: inline-block; width: 160px; padding-top: 4px; }
    </style>
</head>
<body>

<table class="layout header-table">
    <tr>
        <td style="width: 60px;">
            @if($brand->logoFile())
            <img src="{{ $brand->logoFile() }}" class="logo">
            @endif
        </td>
        <td>
            <div class="company-name">{{ $brand->namaSingkat() }}</div>
            <div class="company-sub">{{ $brand->subjudul() }}</div>
        </td>
        <td style="text-align:right">
            <div class="report-title">LAPORAN BEP OTOMATIS</div>
            <div class="report-meta">
                Periode {{ $mulai->format('d/m/Y') }} s.d {{ $akhir->format('d/m/Y') }}<br>
                {{ $cabangNama }}<br>
                Dicetak: {{ now()->format('d/m/Y H:i') }}
            </div>
        </td>
    </tr>
</table>
<div class="divider"></div>

<table class="summary-table">
    <tr>
        <td><div class="val">{{ number_format($bep['bep_unit'] ?? 0, 2, ',', '.') }} kg</div><div class="lbl">BEP Unit</div></td>
        <td><div class="val">Rp {{ number_format($bep['bep_rupiah'] ?? 0, 0, ',', '.') }}</div><div class="lbl">BEP Rupiah</div></td>
        <td><div class="val">{{ number_format($bep['volume_aktual'], 2, ',', '.') }} kg</div><div class="lbl">Volume Aktual</div></td>
        <td><div class="val">{{ $bep['persentase_tercapai'] !== null ? number_format($bep['persentase_tercapai'], 1, ',', '.') . '%' : '-' }}</div><div class="lbl">% Tercapai</div></td>
    </tr>
</table>

<table class="data">
    <tr><th colspan="2">KOMPONEN PERHITUNGAN</th></tr>
    <tr><td>Biaya Tetap (periode ini)</td><td class="num">Rp {{ number_format($bep['biaya_tetap'], 0, ',', '.') }}</td></tr>
    <tr><td>Biaya Variabel / kg (HPP jasa giling)</td><td class="num">Rp {{ number_format($bep['biaya_variabel_per_unit'], 2, ',', '.') }}</td></tr>
    <tr><td>Harga Jual rata-rata / kg</td><td class="num">Rp {{ number_format($bep['harga_jual_per_unit'], 2, ',', '.') }}</td></tr>
    <tr class="grand"><td>Margin Kontribusi / kg</td><td class="num">Rp {{ number_format($bep['margin_kontribusi_per_unit'], 2, ',', '.') }}</td></tr>
    <tr><td>Total HPP Jasa Giling</td><td class="num">Rp {{ number_format($bep['total_hpp_jasa_giling'], 0, ',', '.') }}</td></tr>
    <tr><td>Total Omzet Jasa Giling</td><td class="num">Rp {{ number_format($bep['total_omzet_jasa_giling'], 0, ',', '.') }}</td></tr>
    <tr><td>Margin of Safety</td><td class="num">{{ $bep['margin_of_safety_persen'] !== null ? number_format($bep['margin_of_safety_persen'], 1, ',', '.') . '%' : '-' }}</td></tr>
    <tr class="grand"><td>Estimasi Laba pada Volume Aktual</td><td class="num">Rp {{ number_format($bep['estimasi_laba'], 0, ',', '.') }}</td></tr>
</table>

<table class="data">
    <tr><th colspan="2">BREAKDOWN BIAYA TETAP PER KATEGORI</th></tr>
    @forelse($bep['biaya_tetap_detail'] as $d)
    <tr><td>{{ $d['nama_kategori'] }}</td><td class="num">Rp {{ number_format($d['jumlah'], 0, ',', '.') }}</td></tr>
    @empty
    <tr><td colspan="2">Tidak ada transaksi biaya tetap di periode ini.</td></tr>
    @endforelse
    <tr class="grand"><td>Total</td><td class="num">Rp {{ number_format($bep['biaya_tetap'], 0, ',', '.') }}</td></tr>
</table>

@if(!$bep['bisa_bep'])
<div class="footer-note">Catatan: BEP tidak bisa dihitung karena margin kontribusi negatif/nol (Harga Jual &le; Biaya Variabel per kg).</div>
@endif

<table class="signature-table">
    <tr>
        <td><div class="signature-line">Disiapkan oleh<br>{{ auth()->user()->name ?? '-' }}</div></td>
        <td><div class="signature-line">Mengetahui,<br>Owner</div></td>
    </tr>
</table>

</body>
</html>
