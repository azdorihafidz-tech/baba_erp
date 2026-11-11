<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Neraca — {{ $cabangNama }} — {{ \Carbon\Carbon::parse($neraca['tanggal'])->format('d-m-Y') }}</title>
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

        .balance-box { border: 1px solid; border-radius: 4px; padding: 6px 10px; margin-bottom: 10px; font-size: 10px; }
        .balance-ok { border-color: #198754; background: #e8f6ee; color: #14532d; }
        .balance-warn { border-color: #d97706; background: #fef7e6; color: #78350f; }

        table.data { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.data th { background: #f1f5f9; padding: 5px 6px; text-align: left; font-size: 10px; border-bottom: 1px solid #cbd5e1; }
        table.data td { padding: 4px 6px; font-size: 10px; border-bottom: 1px solid #f1f5f9; }
        table.data td.num { text-align: right; }
        tr.section-head td { background: #f8fafc; font-weight: bold; font-size: 10px; padding-top: 8px; }
        tr.subtotal td { font-weight: bold; border-top: 1px solid #cbd5e1; }
        tr.grand td { background: #dbeafe; font-weight: bold; font-size: 11px; }
        td.indent { padding-left: 16px; }

        .col-wrap td { vertical-align: top; width: 50%; padding: 0 6px; }

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
            <div class="report-title">LAPORAN NERACA</div>
            <div class="report-meta">
                Per {{ \Carbon\Carbon::parse($neraca['tanggal'])->translatedFormat('d F Y') }}<br>
                {{ $cabangNama }}<br>
                Dicetak: {{ now()->format('d/m/Y H:i') }}
            </div>
        </td>
    </tr>
</table>
<div class="divider"></div>

@if($neraca['balance_check'])
<div class="balance-box balance-ok">Neraca BALANCE — Total Aset Rp {{ number_format($neraca['aset']['total_aset'], 0, ',', '.') }} = Total Kewajiban + Modal Rp {{ number_format($neraca['total_kewajiban_modal'], 0, ',', '.') }}</div>
@else
<div class="balance-box balance-warn">
    Neraca BELUM BALANCE — Total Aset Rp {{ number_format($neraca['aset']['total_aset'], 0, ',', '.') }} vs Total Kewajiban+Modal Rp {{ number_format($neraca['total_kewajiban_modal'], 0, ',', '.') }} (Selisih Rp {{ number_format($neraca['selisih'], 0, ',', '.') }}). Wajar untuk data historis pra-double-entry — Modal Owner adalah figure yang bisa di-adjust manual.
</div>
@endif

<table class="layout col-wrap">
<tr>
<td>
    <table class="data">
        <tr><th colspan="2">ASET</th></tr>
        <tr class="section-head"><td colspan="2">Aset Lancar</td></tr>
        <tr><td class="indent">Kas &amp; Setara Kas</td><td class="num">Rp {{ number_format($neraca['aset']['lancar']['kas_setara'], 0, ',', '.') }}</td></tr>
        <tr><td class="indent">Piutang Usaha</td><td class="num">Rp {{ number_format($neraca['aset']['lancar']['piutang'], 0, ',', '.') }}</td></tr>
        <tr><td class="indent">Persediaan Bahan Baku</td><td class="num">Rp {{ number_format($neraca['aset']['lancar']['persediaan_bahan_baku'], 0, ',', '.') }}</td></tr>
        <tr><td class="indent">Persediaan Barang Jadi</td><td class="num">Rp {{ number_format($neraca['aset']['lancar']['persediaan_barang_jadi'], 0, ',', '.') }}</td></tr>
        <tr><td class="indent">Persediaan Kemasan</td><td class="num">Rp {{ number_format($neraca['aset']['lancar']['persediaan_kemasan'], 0, ',', '.') }}</td></tr>
        <tr class="subtotal"><td>Total Aset Lancar</td><td class="num">Rp {{ number_format($neraca['aset']['lancar']['total'], 0, ',', '.') }}</td></tr>

        <tr class="section-head"><td colspan="2">Aset Tetap</td></tr>
        <tr><td class="indent">Aset Bruto (Harga Perolehan)</td><td class="num">Rp {{ number_format($neraca['aset']['tetap']['aset_bruto'], 0, ',', '.') }}</td></tr>
        <tr><td class="indent">Akumulasi Depresiasi</td><td class="num">(Rp {{ number_format($neraca['aset']['tetap']['akumulasi_depresiasi'], 0, ',', '.') }})</td></tr>
        <tr class="subtotal"><td>Nilai Buku Aset Tetap</td><td class="num">Rp {{ number_format($neraca['aset']['tetap']['nilai_buku'], 0, ',', '.') }}</td></tr>

        <tr class="grand"><td>TOTAL ASET</td><td class="num">Rp {{ number_format($neraca['aset']['total_aset'], 0, ',', '.') }}</td></tr>
    </table>
</td>
<td>
    <table class="data">
        <tr><th colspan="2">KEWAJIBAN</th></tr>
        <tr class="section-head"><td colspan="2">Kewajiban Jangka Pendek</td></tr>
        <tr><td class="indent">Hutang Usaha</td><td class="num">Rp {{ number_format($neraca['kewajiban']['jangka_pendek']['hutang_usaha'], 0, ',', '.') }}</td></tr>
        <tr><td class="indent">Hutang Pajak</td><td class="num">Rp {{ number_format($neraca['kewajiban']['jangka_pendek']['hutang_pajak'], 0, ',', '.') }}</td></tr>
        <tr class="subtotal"><td>Total Jangka Pendek</td><td class="num">Rp {{ number_format($neraca['kewajiban']['jangka_pendek']['total'], 0, ',', '.') }}</td></tr>

        <tr class="section-head"><td colspan="2">Kewajiban Jangka Panjang</td></tr>
        <tr><td class="indent">Hutang Bank</td><td class="num">Rp {{ number_format($neraca['kewajiban']['jangka_panjang']['hutang_bank'], 0, ',', '.') }}</td></tr>
        <tr class="subtotal"><td>Total Jangka Panjang</td><td class="num">Rp {{ number_format($neraca['kewajiban']['jangka_panjang']['total'], 0, ',', '.') }}</td></tr>

        <tr class="grand"><td>TOTAL KEWAJIBAN</td><td class="num">Rp {{ number_format($neraca['kewajiban']['total_kewajiban'], 0, ',', '.') }}</td></tr>
    </table>

    <table class="data">
        <tr><th colspan="2">MODAL</th></tr>
        <tr><td class="indent">Modal Owner</td><td class="num">Rp {{ number_format($neraca['modal']['modal_owner'], 0, ',', '.') }}</td></tr>
        <tr><td class="indent">Laba Ditahan (s/d cutoff)</td><td class="num">Rp {{ number_format($neraca['modal']['laba_ditahan'], 0, ',', '.') }}</td></tr>
        <tr class="grand"><td>TOTAL MODAL</td><td class="num">Rp {{ number_format($neraca['modal']['total_modal'], 0, ',', '.') }}</td></tr>
    </table>

    <table class="data">
        <tr class="grand"><td>TOTAL KEWAJIBAN + MODAL</td><td class="num">Rp {{ number_format($neraca['total_kewajiban_modal'], 0, ',', '.') }}</td></tr>
    </table>
</td>
</tr>
</table>

<div class="footer-note">
    Catatan: Kas, Persediaan, dan Nilai Buku Aset mencerminkan kondisi terkini sistem (bukan snapshot historis per tanggal) — hanya Laba Ditahan yang menghormati cutoff tanggal di atas.
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
