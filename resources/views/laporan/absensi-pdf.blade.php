<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Absensi Wajah — {{ $dari }} s/d {{ $sampai }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            background: white;
        }
        .print-header {
            display: flex; align-items: flex-start;
            justify-content: space-between;
            border-bottom: 3px solid #3b82f6;
            padding-bottom: 12px; margin-bottom: 16px;
        }
        .company-name { font-size: 18px; font-weight: 700; color: #1e293b; }
        .company-sub  { font-size: 10px; color: #64748b; margin-top: 2px; }
        .report-title { text-align: right; }
        .report-title h1 { font-size: 14px; font-weight: 700; color: #3b82f6; }
        .report-meta  { font-size: 10px; color: #64748b; margin-top: 4px; line-height: 1.6; }
        .stats-row {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 8px; margin-bottom: 14px;
        }
        .stat-card {
            border: 1px solid #e2e8f0; border-radius: 6px;
            padding: 8px 10px; text-align: center;
        }
        .stat-val   { font-size: 18px; font-weight: 700; }
        .stat-label { font-size: 9px; color: #64748b; margin-top: 2px; }
        .c-blue   { color: #3b82f6; }
        .c-green  { color: #22c55e; }
        .c-purple { color: #8b5cf6; }
        .c-red    { color: #ef4444; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        thead th {
            background: #f1f5f9; padding: 7px 8px; text-align: left;
            font-weight: 600; border-bottom: 2px solid #e2e8f0; white-space: nowrap;
        }
        tbody td {
            padding: 6px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: middle;
        }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody tr:last-child td  { border-bottom: none; }
        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 20px;
            font-size: 9px; font-weight: 600;
        }
        .ok  { background: #dcfce7; color: #16a34a; }
        .co  { background: #dbeafe; color: #1d4ed8; }
        .wrn { background: #fef9c3; color: #b45309; }
        .err { background: #fee2e2; color: #b91c1c; }
        .print-footer {
            margin-top: 20px; padding-top: 12px; border-top: 1px solid #e2e8f0;
            display: flex; justify-content: space-between; align-items: flex-end;
            font-size: 9px; color: #64748b;
        }
        .signature-box { text-align: center; min-width: 140px; }
        .signature-line { border-top: 1px solid #94a3b8; margin-top: 40px; padding-top: 4px; }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            @page { size: A4 landscape; margin: 12mm 10mm; }
            .no-print { display: none !important; }
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; }
        }
        .print-btn {
            position: fixed; top: 16px; right: 16px;
            background: #3b82f6; color: white;
            border: none; border-radius: 8px;
            padding: 8px 18px; font-size: 12px; font-weight: 600;
            cursor: pointer; z-index: 999;
        }
        @media print { .print-btn { display: none; } }
    </style>
</head>
<body>

<button class="print-btn no-print" onclick="window.print()">🖨️ Print / Save PDF</button>

<div class="print-header">
    <div>
        <div class="company-name">{{ $brand->namaSingkat() }}</div>
        <div class="company-sub">Sistem ERP — Laporan Absensi Wajah</div>
    </div>
    <div class="report-title">
        <h1>LAPORAN ABSENSI WAJAH</h1>
        <div class="report-meta">
            Periode: <strong>{{ \Carbon\Carbon::parse($dari)->format('d/m/Y') }}</strong>
            s/d <strong>{{ \Carbon\Carbon::parse($sampai)->format('d/m/Y') }}</strong><br>
            @if($cabang)
            Cabang: <strong>{{ $cabang->nama_cabang }}</strong><br>
            @endif
            Dicetak: {{ now()->format('d/m/Y H:i:s') }}
        </div>
    </div>
</div>

@php
    $ctrl      = app(\App\Http\Controllers\LaporanAbsensiController::class);
    $total     = $records->count();
    $hariHadir = $records->whereNotNull('jam_masuk')->count();
    $hariLem   = $records->whereNotNull('jam_lembur_masuk')->count();
    $unik      = $records->pluck('karyawan_id')->unique()->filter()->count();
@endphp
<div class="stats-row">
    <div class="stat-card">
        <div class="stat-val c-blue">{{ number_format($total) }}</div>
        <div class="stat-label">Total Record</div>
    </div>
    <div class="stat-card">
        <div class="stat-val c-purple">{{ number_format($unik) }}</div>
        <div class="stat-label">Karyawan Unik</div>
    </div>
    <div class="stat-card">
        <div class="stat-val c-green">{{ number_format($hariHadir) }}</div>
        <div class="stat-label">Hari Hadir</div>
    </div>
    <div class="stat-card">
        <div class="stat-val c-red">{{ number_format($hariLem) }}</div>
        <div class="stat-label">Hari Ada Lembur</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>No</th>
            <th>Tanggal</th>
            <th>Nama Karyawan</th>
            <th>Jabatan</th>
            <th>Cabang</th>
            <th>Jam Masuk</th>
            <th>Jam Keluar</th>
            <th>Total Kerja</th>
            <th>Lembur Masuk</th>
            <th>Lembur Keluar</th>
            <th>Total Lembur</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse($records as $i => $row)
        @php
            $masukRaw    = $row->getRawOriginal('jam_masuk');
            $keluarRaw   = $row->getRawOriginal('jam_keluar');
            $lemMasukRaw = $row->getRawOriginal('jam_lembur_masuk');
            $lemKelRaw   = $row->getRawOriginal('jam_lembur_keluar');
            $statusLabel = $ctrl->labelStatus($row);
            $scCls = match(true) {
                str_contains($statusLabel,'Telat') && str_contains($statusLabel,'Lembur') => 'wrn',
                str_contains($statusLabel,'Telat')   => 'wrn',
                str_contains($statusLabel,'Lembur')  => 'co',
                $row->jam_masuk !== null              => 'ok',
                default                               => 'err',
            };
        @endphp
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $row->tanggal->format('d/m/Y') }}</td>
            <td>{{ $row->karyawan?->nama_lengkap ?? '-' }}</td>
            <td>{{ $row->karyawan?->jabatan ?? '-' }}</td>
            <td>{{ $row->cabang?->nama_cabang ?? '-' }}</td>
            <td><strong>{{ $masukRaw ? substr($masukRaw, 0, 5) : '—' }}</strong></td>
            <td>{{ $keluarRaw ? substr($keluarRaw, 0, 5) : '—' }}</td>
            <td>{{ $ctrl->hitungDurasi($masukRaw, $keluarRaw) ?? '—' }}</td>
            <td>{{ $lemMasukRaw ? substr($lemMasukRaw, 0, 5) : '—' }}</td>
            <td>{{ $lemKelRaw  ? substr($lemKelRaw, 0, 5) : '—' }}</td>
            <td>{{ $ctrl->hitungDurasi($lemMasukRaw, $lemKelRaw) ?? ($row->jam_lembur ? number_format($row->jam_lembur,1).'j' : '—') }}</td>
            <td><span class="badge {{ $scCls }}">{{ $statusLabel }}</span></td>
        </tr>
        @empty
        <tr>
            <td colspan="12" style="text-align:center;padding:20px;color:#94a3b8">
                Tidak ada data untuk periode ini.
            </td>
        </tr>
        @endforelse
    </tbody>
</table>

<div class="print-footer">
    <div>
        <div>{{ $brand->namaSingkat() }} — Laporan Absensi Wajah</div>
        <div>Dicetak otomatis oleh sistem pada {{ now()->format('d M Y, H:i') }}</div>
        <div>Total {{ $records->count() }} record | Periode {{ $dari }} s/d {{ $sampai }}</div>
    </div>
    <div class="signature-box">
        <div>Mengetahui,</div>
        <div class="signature-line">Kepala Cabang / Manager</div>
    </div>
</div>

</body>
</html>
