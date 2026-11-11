<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Buku Besar — {{ $ledger['akun']->kode }} — {{ $mulai->format('d-m-Y') }} s.d {{ $akhir->format('d-m-Y') }}</title>
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

        .akun-box { border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px 10px; margin-bottom: 10px; font-size: 11px; background: #f8fafc; }

        table.data { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.data th { background: #f1f5f9; padding: 5px 6px; text-align: left; font-size: 10px; border-bottom: 1px solid #cbd5e1; }
        table.data td { padding: 4px 6px; font-size: 9.5px; border-bottom: 1px solid #f1f5f9; }
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
            <div class="report-title">BUKU BESAR</div>
            <div class="report-meta">
                Periode {{ $mulai->format('d/m/Y') }} s.d {{ $akhir->format('d/m/Y') }}<br>
                {{ $cabangNama }}<br>
                Dicetak: {{ now()->format('d/m/Y H:i') }}
            </div>
        </td>
    </tr>
</table>
<div class="divider"></div>

<div class="akun-box">
    <strong>{{ $ledger['akun']->kode }} — {{ $ledger['akun']->nama }}</strong> (Saldo Normal: {{ ucfirst($ledger['akun']->saldo_normal) }})
</div>

<table class="data">
    <thead>
        <tr><th>Tanggal</th><th>Keterangan</th><th class="num">Debit</th><th class="num">Kredit</th><th class="num">Saldo</th></tr>
    </thead>
    <tbody>
    @forelse($ledger['baris'] as $b)
        <tr>
            <td>{{ \Carbon\Carbon::parse($b['tanggal'])->format('d/m/Y') }}</td>
            <td>{{ $b['keterangan'] ?? '-' }}</td>
            <td class="num">{{ $b['debit'] > 0 ? number_format($b['debit'], 0, ',', '.') : '-' }}</td>
            <td class="num">{{ $b['kredit'] > 0 ? number_format($b['kredit'], 0, ',', '.') : '-' }}</td>
            <td class="num">{{ number_format($b['saldo'], 0, ',', '.') }}</td>
        </tr>
    @empty
        <tr><td colspan="5">Tidak ada transaksi untuk akun ini di periode yang dipilih.</td></tr>
    @endforelse
    </tbody>
    <tfoot>
        <tr class="grand">
            <td colspan="2">Total</td>
            <td class="num">Rp {{ number_format($ledger['total_debit'], 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format($ledger['total_kredit'], 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format($ledger['saldo_akhir'], 0, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>

<div class="footer-note">
    Catatan: Saldo baris pertama periode dianggap Rp 0 (bukan saldo kumulatif riil sejak akun ini ada). Buku Besar ini hanya mencakup akun Pendapatan/HPP/Beban.
</div>

<table class="signature-table">
    <tr>
        <td><div class="signature-line">Disiapkan oleh<br>{{ auth()->user()->name ?? '-' }}</div></td>
        <td><div class="signature-line">Mengetahui,<br>Owner</div></td>
    </tr>
</table>

</body>
</html>
