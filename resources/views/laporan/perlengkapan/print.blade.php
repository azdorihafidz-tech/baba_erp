<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemakaian Perlengkapan - {{ $mulai->format('d/m/Y') }} s/d {{ $akhir->format('d/m/Y') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { padding: 0 !important; }
            @page { size: A4 portrait; margin: 12mm; }
        }
        body { padding: 16px; font-size: 13px; }
        .table th, .table td { padding: .35rem .5rem; }
        .btn-close-page { position: fixed; top: 12px; right: 12px; z-index: 999; }
    </style>
</head>
<body onload="window.print()">

<button type="button" class="btn btn-outline-secondary btn-sm no-print btn-close-page" onclick="window.close()">
    <i class="bi bi-x-lg"></i> Tutup
</button>

<div class="text-center mb-3">
    <h4 class="fw-bold mb-0">LAPORAN PEMAKAIAN PERLENGKAPAN</h4>
    <div class="text-muted">{{ $mulai->format('d/m/Y') }} — {{ $akhir->format('d/m/Y') }}</div>
</div>

<table class="table table-bordered table-sm mb-4">
    <tbody>
        <tr>
            <td class="fw-semibold" style="width:40%">Total Nilai</td>
            <td>Rp {{ number_format($ringkasan['total_nilai'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="fw-semibold">Jumlah Kejadian</td>
            <td>{{ $ringkasan['jumlah_kejadian'] }}</td>
        </tr>
        <tr>
            <td class="fw-semibold">Item Berbeda Terpakai</td>
            <td>{{ $ringkasan['jumlah_item'] }}</td>
        </tr>
    </tbody>
</table>

<h6 class="fw-bold">Detail Pemakaian</h6>
<table class="table table-bordered table-sm">
    <thead>
        <tr>
            <th>Tanggal</th>
            <th>Item</th>
            <th>Cabang</th>
            <th class="text-end">Qty</th>
            <th class="text-end">Nilai</th>
            <th>Keterangan</th>
        </tr>
    </thead>
    <tbody>
        @forelse($detail as $d)
        <tr>
            <td>{{ \Carbon\Carbon::parse($d->tanggal_pemakaian)->format('d/m/Y') }}</td>
            <td>{{ $d->nama_item }}</td>
            <td>{{ $d->nama_cabang }}</td>
            <td class="text-end">{{ number_format($d->qty, 3) }} {{ $d->satuan }}</td>
            <td class="text-end">Rp {{ number_format($d->nilai, 0, ',', '.') }}</td>
            <td>{{ $d->keterangan ?: '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="6" class="text-center text-muted">Belum ada data untuk periode ini</td></tr>
        @endforelse
    </tbody>
</table>

</body>
</html>
