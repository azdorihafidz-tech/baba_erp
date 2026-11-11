<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laba Rugi - {{ $dari->format('d/m/Y') }} s/d {{ $sampai->format('d/m/Y') }}</title>
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
    <h4 class="fw-bold mb-0">LAPORAN LABA RUGI</h4>
    <div class="text-muted">{{ $dari->format('d/m/Y') }} — {{ $sampai->format('d/m/Y') }}</div>
    <div class="text-muted small">{{ $cabangTerpilih?->nama_cabang ?? 'Semua Cabang' }} — Breakdown: {{ ['item'=>'Per Item','kategori'=>'Per Kategori','jenis_olahan'=>'Per Jenis Menu','order'=>'Per Order'][$level] ?? 'Per Item' }}</div>
</div>

<table class="table table-bordered table-sm mb-4">
    <tbody>
        <tr>
            <td class="fw-semibold" style="width:40%">Total Order</td>
            <td>{{ $ringkasan['total_order'] }}</td>
        </tr>
        <tr>
            <td class="fw-semibold">Total Omzet</td>
            <td>Rp {{ number_format($ringkasan['total_omzet'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="fw-semibold">Total HPP</td>
            <td>Rp {{ number_format($ringkasan['total_hpp'], 0, ',', '.') }}</td>
        </tr>
        <tr class="{{ $ringkasan['total_untung'] >= 0 ? 'table-success' : 'table-danger' }}">
            <td class="fw-bold">TOTAL UNTUNG</td>
            <td class="fw-bold">Rp {{ number_format($ringkasan['total_untung'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="fw-semibold">Margin</td>
            <td>{{ $ringkasan['margin'] !== null ? $ringkasan['margin'] . '%' : '-' }}</td>
        </tr>
    </tbody>
</table>

<h6 class="fw-bold">Breakdown {{ ['item'=>'Per Item','kategori'=>'Per Kategori','jenis_olahan'=>'Per Jenis Menu','order'=>'Per Order'][$level] ?? 'Per Item' }}</h6>
<table class="table table-bordered table-sm mb-3">
    <thead class="table-light">
        <tr>
            @if($level === 'item')
                <th>Item</th><th>Tipe</th><th class="text-end">Qty</th>
            @elseif($level === 'kategori')
                <th>Kategori</th>
            @elseif($level === 'jenis_olahan')
                <th>Jenis Menu</th>
            @else
                <th>No Order</th><th>Tanggal</th><th>Pelanggan</th><th>Kasir</th>
            @endif
            <th class="text-end">Omzet</th><th class="text-end">HPP</th><th class="text-end">Untung</th><th class="text-end">Margin</th>
        </tr>
    </thead>
    <tbody>
    @forelse($breakdownPenuh as $b)
    <tr>
        @if($level === 'item')
            <td>{{ $b->nama_item }}</td><td>{{ ucwords(str_replace('_', ' ', $b->tipe)) }}</td>
            <td class="text-end">{{ rtrim(rtrim(number_format($b->total_qty, 3, ',', '.'), '0'), ',') }} {{ $b->satuan }}</td>
        @elseif($level === 'kategori')
            <td>{{ ucwords(str_replace('_', ' ', $b->kategori)) }}</td>
        @elseif($level === 'jenis_olahan')
            <td>{{ $b->jenis_olahan === '-' ? '-' : ucfirst($b->jenis_olahan) }}</td>
        @else
            <td>{{ $b->nomor_order }}</td><td>{{ \Carbon\Carbon::parse($b->tanggal_order)->format('d/m/Y') }}</td>
            <td>{{ $b->nama_pelanggan ?? 'Umum' }}</td><td>{{ $b->kasir_nama ?? '-' }}</td>
        @endif
        <td class="text-end">Rp {{ number_format($b->total_omzet, 0, ',', '.') }}</td>
        <td class="text-end">Rp {{ number_format($b->total_hpp, 0, ',', '.') }}</td>
        <td class="text-end">Rp {{ number_format($b->total_untung, 0, ',', '.') }}</td>
        <td class="text-end">{{ $b->margin !== null ? $b->margin . '%' : '-' }}</td>
    </tr>
    @empty
    <tr><td colspan="8" class="text-center text-muted">Tidak ada data.</td></tr>
    @endforelse
    </tbody>
    <tfoot>
        <tr class="table-light fw-bold">
            <td colspan="{{ $level === 'item' ? 3 : ($level === 'order' ? 4 : 1) }}" class="text-end">Total:</td>
            <td class="text-end">Rp {{ number_format($breakdownPenuh->sum('total_omzet'), 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($breakdownPenuh->sum('total_hpp'), 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($breakdownPenuh->sum('total_untung'), 0, ',', '.') }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>

<h6 class="fw-bold mt-3">Detail Transaksi</h6>
@if($detail->isEmpty())
<p class="text-muted">Tidak ada transaksi di periode ini.</p>
@else
    @foreach($detail as $tanggal => $rows)
    <div class="fw-semibold small mb-1 mt-2">{{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d M Y') }}</div>
    <table class="table table-bordered table-sm mb-2">
        <thead class="table-light">
            <tr><th>Waktu</th><th>No. Order</th><th>Kategori</th><th>Item</th><th class="text-end">Qty</th><th class="text-end">Omzet</th><th class="text-end">HPP</th><th class="text-end">Untung</th><th>Kasir</th></tr>
        </thead>
        <tbody>
        @foreach($rows as $r)
        <tr>
            <td>{{ \Carbon\Carbon::parse($r->order_created_at)->format('H:i') }}</td>
            <td>{{ $r->nomor_order }}</td>
            <td>{{ ucwords(str_replace('_', ' ', $r->kategori)) }}</td>
            <td>{{ $r->nama_item }}</td>
            <td class="text-end">{{ rtrim(rtrim(number_format($r->qty, 3, ',', '.'), '0'), ',') }} {{ $r->satuan }}</td>
            <td class="text-end">Rp {{ number_format($r->omzet, 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($r->hpp, 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($r->untung, 0, ',', '.') }}</td>
            <td>{{ $r->kasir_nama ?? '-' }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endforeach
@endif

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
