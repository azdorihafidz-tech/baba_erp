<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laba Rugi Formal — {{ $cabangNama }} — {{ $mulai->format('d-m-Y') }} s.d {{ $akhir->format('d-m-Y') }}</title>
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

        table.data { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.data td { padding: 4px 6px; font-size: 10px; border-bottom: 1px solid #f1f5f9; }
        table.data td.num { text-align: right; }
        tr.section-head td { background: #f8fafc; font-weight: bold; font-size: 10.5px; padding-top: 8px; }
        tr.subtotal td { font-weight: bold; border-top: 1px solid #cbd5e1; }
        tr.grand td { background: #dbeafe; font-weight: bold; font-size: 11px; }
        tr.grand-final td { background: #d1fae5; font-weight: bold; font-size: 12px; }
        td.indent { padding-left: 16px; }

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
            <div class="report-title">LAPORAN LABA RUGI FORMAL</div>
            <div class="report-meta">
                Periode {{ $mulai->format('d/m/Y') }} s.d {{ $akhir->format('d/m/Y') }}<br>
                {{ $cabangNama }}<br>
                Dicetak: {{ now()->format('d/m/Y H:i') }}
            </div>
        </td>
    </tr>
</table>
<div class="divider"></div>

<table class="data">
    <tr class="section-head"><td colspan="2">PENDAPATAN</td></tr>
    @foreach($labaRugi['pendapatan']['detail'] as $d)
    <tr><td class="indent">{{ $d['kode'] }} — {{ $d['nama'] }}</td><td class="num">Rp {{ number_format($d['jumlah'], 0, ',', '.') }}</td></tr>
    @endforeach
    <tr class="subtotal"><td>Total Pendapatan</td><td class="num">Rp {{ number_format($labaRugi['pendapatan']['total'], 0, ',', '.') }}</td></tr>

    <tr class="section-head"><td colspan="2">HARGA POKOK PENJUALAN (HPP)</td></tr>
    @foreach($labaRugi['hpp']['detail'] as $d)
    <tr><td class="indent">{{ $d['kode'] }} — {{ $d['nama'] }}</td><td class="num">Rp {{ number_format($d['jumlah'], 0, ',', '.') }}</td></tr>
    @endforeach
    <tr class="subtotal"><td>Total HPP</td><td class="num">(Rp {{ number_format($labaRugi['hpp']['total'], 0, ',', '.') }})</td></tr>

    <tr class="grand"><td>LABA KOTOR</td><td class="num">Rp {{ number_format($labaRugi['laba_kotor'], 0, ',', '.') }}</td></tr>

    <tr class="section-head"><td colspan="2">BEBAN OPERASIONAL</td></tr>
    @foreach($labaRugi['beban_operasional']['detail'] as $d)
    <tr><td class="indent">{{ $d['kode'] }} — {{ $d['nama'] }}</td><td class="num">Rp {{ number_format($d['jumlah'], 0, ',', '.') }}</td></tr>
    @endforeach
    <tr class="subtotal"><td>Total Beban Operasional</td><td class="num">(Rp {{ number_format($labaRugi['beban_operasional']['total'], 0, ',', '.') }})</td></tr>

    <tr class="grand"><td>LABA USAHA</td><td class="num">Rp {{ number_format($labaRugi['laba_usaha'], 0, ',', '.') }}</td></tr>

    <tr class="section-head"><td colspan="2">PENDAPATAN LAIN-LAIN</td></tr>
    @foreach($labaRugi['pendapatan_lain']['detail'] as $d)
    <tr><td class="indent">{{ $d['kode'] }} — {{ $d['nama'] }}</td><td class="num">Rp {{ number_format($d['jumlah'], 0, ',', '.') }}</td></tr>
    @endforeach
    <tr class="subtotal"><td>Total Pendapatan Lain-lain</td><td class="num">Rp {{ number_format($labaRugi['pendapatan_lain']['total'], 0, ',', '.') }}</td></tr>

    <tr class="section-head"><td colspan="2">BEBAN LAIN-LAIN</td></tr>
    @foreach($labaRugi['beban_lain']['detail'] as $d)
    <tr><td class="indent">{{ $d['kode'] }} — {{ $d['nama'] }}</td><td class="num">Rp {{ number_format($d['jumlah'], 0, ',', '.') }}</td></tr>
    @endforeach
    <tr class="subtotal"><td>Total Beban Lain-lain</td><td class="num">(Rp {{ number_format($labaRugi['beban_lain']['total'], 0, ',', '.') }})</td></tr>

    <tr class="grand"><td>LABA BERSIH SEBELUM PAJAK</td><td class="num">Rp {{ number_format($labaRugi['laba_bersih_sebelum_pajak'], 0, ',', '.') }}</td></tr>
    <tr><td class="indent">Pajak Penghasilan</td><td class="num">(Rp {{ number_format($labaRugi['pajak_penghasilan'], 0, ',', '.') }})</td></tr>
    <tr class="grand-final"><td>LABA BERSIH SETELAH PAJAK</td><td class="num">Rp {{ number_format($labaRugi['laba_bersih_setelah_pajak'], 0, ',', '.') }}</td></tr>
</table>

<div class="footer-note">
    Catatan: Laporan dikelompokkan per Chart of Accounts (COA) mengikuti struktur SAK ETAP. Belum ada fitur pencatatan pajak penghasilan usaha, sehingga nilainya Rp 0.
</div>

<table class="signature-table">
    <tr>
        <td>
            <div class="signature-line">Disiapkan oleh<br>{{ auth()->user()->name ?? '-' }}</div>
        </td>
        <td>
            <div class="signature-line">Mengetahui,<br>Owner</div>
        </td>
    </tr>
</table>

</body>
</html>
