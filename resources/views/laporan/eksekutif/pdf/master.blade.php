<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Eksekutif Keuangan — {{ $cabangNama }} — {{ $tanggal->format('d-m-Y') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; color: #1e293b; }
        .page { {{ ($isPdf ?? false) ? '' : 'border: 1px dashed #cbd5e1; margin-bottom: 24px; padding: 16px;' }} }
        .page + .page { page-break-before: always; }

        table.layout { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; padding-bottom: 10px; }
        .logo { width: 50px; height: 50px; }
        .company-name { font-size: 15px; font-weight: bold; color: #1e293b; }
        .company-sub { font-size: 8.5px; color: #64748b; }
        .report-title { text-align: right; font-size: 13px; font-weight: bold; color: #0d6efd; }
        .report-meta { text-align: right; font-size: 8.5px; color: #64748b; margin-top: 3px; }
        .divider { border-bottom: 2px solid #0d6efd; margin-bottom: 10px; }
        .page-heading { font-size: 13px; font-weight: bold; color: #0d6efd; margin-bottom: 8px; }

        table.data { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.data th { background: #f1f5f9; padding: 5px 6px; text-align: left; font-size: 9.5px; border-bottom: 1px solid #cbd5e1; }
        table.data td { padding: 4px 6px; font-size: 9.5px; border-bottom: 1px solid #f1f5f9; }
        table.data td.num { text-align: right; }
        tr.section-head td { background: #f8fafc; font-weight: bold; font-size: 10px; padding-top: 6px; }
        tr.subtotal td { font-weight: bold; border-top: 1px solid #cbd5e1; }
        tr.grand td { background: #dbeafe; font-weight: bold; }
        td.indent { padding-left: 14px; }

        .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .summary-table td { border: 1px solid #e2e8f0; padding: 7px; text-align: center; }
        .summary-table .val { font-size: 12px; font-weight: bold; }
        .summary-table .lbl { font-size: 8.5px; color: #64748b; margin-top: 2px; }

        .badge { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 8px; font-weight: bold; }
        .badge-sehat, .badge-positif, .badge-match { background: #dcfce7; color: #16a34a; }
        .badge-perhatian { background: #fef9c3; color: #b45309; }
        .badge-kritis, .badge-mismatch { background: #fee2e2; color: #b91c1c; }
        .badge-kunci { background: #dbeafe; color: #1d4ed8; }

        .footer-note { font-size: 8px; color: #94a3b8; margin-top: 6px; }
        .footer-link { font-size: 8px; color: #0d6efd; margin-top: 2px; }
        .kesimpulan-item { padding: 5px 8px; margin-bottom: 5px; border-radius: 3px; font-size: 9.5px; }
        .kesimpulan-positif { background: #ecfdf5; border-left: 3px solid #16a34a; }
        .kesimpulan-perhatian { background: #fffbeb; border-left: 3px solid #d97706; }
        .kesimpulan-kunci { background: #eff6ff; border-left: 3px solid #1d4ed8; }
    </style>
</head>
<body>

@php
    $headerData = compact('cabangNama', 'tanggal');
@endphp

<div class="page">@include('laporan.eksekutif.pdf.halaman-1-cover', $headerData)</div>
<div class="page" style="page-break-before: always;">@include('laporan.eksekutif.pdf.halaman-2-neraca', $headerData)</div>
<div class="page" style="page-break-before: always;">@include('laporan.eksekutif.pdf.halaman-3-laba-rugi', $headerData)</div>
<div class="page" style="page-break-before: always;">@include('laporan.eksekutif.pdf.halaman-4-bep', $headerData)</div>
<div class="page" style="page-break-before: always;">@include('laporan.eksekutif.pdf.halaman-5-cash-flow', $headerData)</div>
<div class="page" style="page-break-before: always;">@include('laporan.eksekutif.pdf.halaman-6-drill-down', $headerData)</div>
<div class="page" style="page-break-before: always;">@include('laporan.eksekutif.pdf.halaman-7-findings', $headerData)</div>
<div class="page" style="page-break-before: always;">@include('laporan.eksekutif.pdf.halaman-8-rangkuman', $headerData)</div>
<div class="page" style="page-break-before: always;">@include('laporan.eksekutif.pdf.halaman-9-simulasi', $headerData)</div>

</body>
</html>
