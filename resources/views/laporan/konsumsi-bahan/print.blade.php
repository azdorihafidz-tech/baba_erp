<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konsumsi Bahan Baku - {{ $dari->format('d/m/Y') }} s/d {{ $sampai->format('d/m/Y') }}</title>
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
    <h4 class="fw-bold mb-0">LAPORAN KONSUMSI BAHAN BAKU</h4>
    <div class="text-muted">{{ $dari->format('d/m/Y') }} — {{ $sampai->format('d/m/Y') }}</div>
    <div class="text-muted small">{{ $cabangTerpilih?->nama_cabang ?? 'Semua Cabang' }} @if($tipe) — {{ ucwords(str_replace('_', ' ', $tipe)) }} @endif</div>
</div>

<table class="table table-bordered table-sm mb-4">
    <tbody>
        <tr>
            <td class="fw-semibold" style="width:40%">Total Item Unik</td>
            <td>{{ $ringkasan['total_item_unik'] }}</td>
        </tr>
        <tr>
            <td class="fw-semibold">Total Omzet</td>
            <td>Rp {{ number_format($ringkasan['total_omzet'], 0, ',', '.') }}</td>
        </tr>
        <tr class="table-primary">
            <td class="fw-bold">TOTAL NILAI HPP</td>
            <td class="fw-bold">Rp {{ number_format($ringkasan['total_hpp'], 0, ',', '.') }}</td>
        </tr>
        <tr class="{{ $ringkasan['total_untung'] >= 0 ? 'table-success' : 'table-danger' }}">
            <td class="fw-bold">TOTAL UNTUNG {{ $ringkasan['margin'] !== null ? '('.$ringkasan['margin'].'%)' : '' }}</td>
            <td class="fw-bold">Rp {{ number_format($ringkasan['total_untung'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="fw-semibold">Item Terbanyak Terpakai</td>
            <td>{{ $ringkasan['item_terbanyak_qty']?->nama_item ?? '-' }}</td>
        </tr>
        <tr>
            <td class="fw-semibold">Item HPP Termahal</td>
            <td>{{ $ringkasan['item_termahal_hpp']?->nama_item ?? '-' }}</td>
        </tr>
    </tbody>
</table>

<h6 class="fw-bold">Breakdown per Item</h6>
<table class="table table-bordered table-sm mb-3">
    <thead class="table-light">
        <tr><th>Item</th><th>Tipe</th><th class="text-end">Qty Terpakai</th><th class="text-end">Omzet</th><th class="text-end">Nilai HPP</th><th class="text-end">Untung</th><th class="text-end">Margin</th><th class="text-end">% HPP dari Total</th></tr>
    </thead>
    <tbody>
    @forelse($breakdownPenuh as $b)
    <tr>
        <td>{{ $b->nama_item }}</td>
        <td>{{ ucwords(str_replace('_', ' ', $b->tipe)) }}</td>
        <td class="text-end">{{ rtrim(rtrim(number_format($b->total_qty, 3, ',', '.'), '0'), ',') }} {{ $b->satuan }}</td>
        <td class="text-end">Rp {{ number_format($b->total_omzet, 0, ',', '.') }}</td>
        <td class="text-end">Rp {{ number_format($b->total_hpp, 0, ',', '.') }}</td>
        <td class="text-end">Rp {{ number_format($b->total_untung, 0, ',', '.') }}</td>
        <td class="text-end">{{ $b->margin !== null ? $b->margin . '%' : '-' }}</td>
        <td class="text-end">{{ $b->persentase }}%</td>
    </tr>
    @empty
    <tr><td colspan="8" class="text-center text-muted">Tidak ada data.</td></tr>
    @endforelse
    </tbody>
    <tfoot>
        <tr class="table-light fw-bold">
            <td colspan="3" class="text-end">Total:</td>
            <td class="text-end">Rp {{ number_format($breakdownPenuh->sum('total_omzet'), 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($breakdownPenuh->sum('total_hpp'), 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($breakdownPenuh->sum('total_untung'), 0, ',', '.') }}</td>
            <td colspan="2"></td>
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
            <tr><th>Waktu</th><th>No. Order</th><th>Item</th><th class="text-end">Qty</th><th class="text-end">HPP</th><th>Kasir</th></tr>
        </thead>
        <tbody>
        @foreach($rows as $r)
        <tr>
            <td>{{ \Carbon\Carbon::parse($r->order_created_at)->format('H:i') }}</td>
            <td>{{ $r->nomor_order }}</td>
            <td>{{ $r->nama_item }}</td>
            <td class="text-end">{{ rtrim(rtrim(number_format($r->qty, 3, ',', '.'), '0'), ',') }} {{ $r->satuan }}</td>
            <td class="text-end">Rp {{ number_format($r->hpp, 0, ',', '.') }}</td>
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
