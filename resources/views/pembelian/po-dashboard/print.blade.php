<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard PO - {{ now()->format('d/m/Y') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { padding: 0 !important; }
            @page { size: A4 portrait; margin: 12mm; }
        }
        body { padding: 16px; font-size: 13px; }
        h4, h5, h6 { margin-bottom: .25rem; }
        .table th, .table td { padding: .35rem .5rem; }
        .btn-close-page {
            position: fixed; top: 12px; right: 12px; z-index: 999;
        }
    </style>
</head>
<body>

<button type="button" class="btn btn-outline-secondary btn-sm no-print btn-close-page" onclick="window.close()">
    <i class="bi bi-x-lg"></i> Tutup
</button>

<div class="text-center mb-3">
    <h4 class="fw-bold mb-0">DASHBOARD PO</h4>
    <div class="text-muted">Dicetak {{ now()->format('d/m/Y H:i') }}</div>
    <div class="text-muted small">{{ $cabangTerpilih?->nama_cabang ?? 'Semua Cabang' }}</div>
</div>

<h6 class="fw-bold">Ringkasan per Status</h6>
<table class="table table-bordered table-sm mb-4">
    <thead class="table-light">
        <tr><th>Status</th><th class="text-end">Jumlah PO</th><th class="text-end">Total Nilai</th><th class="text-end">Umur Maks</th></tr>
    </thead>
    <tbody>
        @php
            $labelBucket = [
                'menunggu_approval' => 'Menunggu Approval',
                'perlu_dikirim'     => 'Perlu Dikirim',
                'dalam_perjalanan'  => 'Dalam Perjalanan',
                'belum_diterima'    => 'Belum Diterima (total)',
                'belum_dibayar'     => 'Belum Dibayar',
            ];
        @endphp
        @foreach($labelBucket as $key => $label)
        @php $r = $ringkasan[$key]; @endphp
        <tr>
            <td>{{ $label }}</td>
            <td class="text-end">{{ $r['count'] }}</td>
            <td class="text-end">Rp {{ number_format($r['total_nilai'], 0, ',', '.') }}</td>
            <td class="text-end">{{ $r['umur_maks_hari'] }} hari</td>
        </tr>
        @endforeach
    </tbody>
</table>

<h6 class="fw-bold">Daftar PO Aktif</h6>
<table class="table table-bordered table-sm mb-3">
    <thead class="table-light">
        <tr><th>Nomor PO</th><th>Supplier</th><th>Cabang</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Umur</th><th>Sudah Dibayar</th></tr>
    </thead>
    <tbody>
    @forelse($breakdownPenuh as $b)
    <tr>
        <td>{{ $b->nomor_po }}</td>
        <td>{{ $b->nama_supplier ?? '-' }}</td>
        <td>{{ $b->nama_cabang ?? '-' }}</td>
        <td>{{ $b->status_label }}</td>
        <td class="text-end">Rp {{ number_format($b->total_harga, 0, ',', '.') }}</td>
        <td class="text-end">{{ $b->umur_hari }} hari</td>
        <td>{{ $b->sudah_dibayar === null ? '-' : ($b->sudah_dibayar ? 'Ya' : 'Belum') }}</td>
    </tr>
    @empty
    <tr><td colspan="7" class="text-center text-muted">Tidak ada data.</td></tr>
    @endforelse
    </tbody>
</table>

<div class="text-muted small text-center mt-4 no-print">
    Dicetak: {{ now()->format('d/m/Y H:i') }}
</div>

<script>
window.addEventListener('load', function () {
    window.print();
});
</script>
</body>
</html>
