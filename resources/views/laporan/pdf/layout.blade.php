<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $judulLaporan ?? 'Laporan' }} — {{ $brand->namaSingkat() }}</title>
    <style>
        {{-- Sprint 3 Batch 1a D'mentai (2026-09-21) -- layout PDF laporan
             dipakai SEMUA menu Laporan (bukan cuma yang lama Neraca/Laba
             Rugi Formal/dst), supaya branding+struktur konsisten lintas
             menu. Ikuti pola visual yang sudah proven di
             laporan/pdf/neraca.blade.php (logo+header table, warna brand
             #FF6B00), ditambah footer nomor halaman (position:fixed +
             CSS counter(page)/counter(pages) -- teknik standar dompdf,
             TIDAK butuh enable_php krn config project sengaja
             enable_php=false demi keamanan). --}}
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

        table.data { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.data th { background: {{ $brand->warna('latar') }}; padding: 5px 6px; text-align: left; font-size: 10px; border-bottom: 1px solid #cbd5e1; font-weight: bold; }
        table.data td { padding: 4px 6px; font-size: 10px; border-bottom: 1px solid #f1f5f9; }
        table.data td.num { text-align: right; }
        table.data td.center { text-align: center; }
        table.data tbody tr:nth-child(even) { background: #fafafa; }
        tr.section-head td { background: #f8fafc; font-weight: bold; font-size: 10px; padding-top: 8px; }
        tr.subtotal td { font-weight: bold; border-top: 1px solid #cbd5e1; }
        tr.grand td { background: #dbeafe; font-weight: bold; font-size: 11px; }
        tr.highlight-warning td { background: #fef3c7; }
        tr.highlight-danger td { background: #fee2e2; }

        .empty-note { text-align: center; color: #94a3b8; padding: 16px 0; font-size: 10px; }

        #footer-dicetak {
            position: fixed;
            bottom: -30px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }
        .page-counter:before {
            content: "Halaman " counter(page) " dari " counter(pages);
        }
        @page {
            margin: 25px 20px 45px 20px;
        }
    </style>
    @stack('pdf-styles')
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
            <div class="report-title">{{ strtoupper($judulLaporan ?? 'LAPORAN') }}</div>
            <div class="report-meta">
                @foreach(($filterInfo ?? []) as $label => $value)
                {{ $label }}: {{ $value }}<br>
                @endforeach
            </div>
        </td>
    </tr>
</table>
<div class="divider"></div>

@yield('content')

<div id="footer-dicetak">
    {{ $footerDicetak ?? ('Dicetak: ' . now()->translatedFormat('d F Y, H:i') . ' WIB') }}
    &bull; <span class="page-counter"></span>
</div>

</body>
</html>
