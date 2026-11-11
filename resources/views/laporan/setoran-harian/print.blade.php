<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setoran Harian - {{ $dari->format('d/m/Y') }} s/d {{ $sampai->format('d/m/Y') }}</title>
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
    <h4 class="fw-bold mb-0">LAPORAN SETORAN HARIAN</h4>
    <div class="text-muted">{{ $dari->format('d/m/Y') }} — {{ $sampai->format('d/m/Y') }}</div>
    <div class="text-muted small">{{ $cabangTerpilih?->nama_cabang ?? 'Semua Cabang' }}</div>
</div>

<table class="table table-bordered table-sm mb-4">
    <tbody>
        <tr>
            <td class="fw-semibold" style="width:40%">Total Order</td>
            <td>{{ $ringkasan['total_order'] }}</td>
        </tr>
        <tr>
            <td class="fw-semibold">Total Pemasukan</td>
            <td>Rp {{ number_format($ringkasan['pemasukan'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="fw-semibold">Total Pengeluaran</td>
            <td>Rp {{ number_format($ringkasan['pengeluaran'], 0, ',', '.') }}</td>
        </tr>
        <tr class="table-primary">
            <td class="fw-bold">SETORAN KE PUSAT (NET)</td>
            <td class="fw-bold">Rp {{ number_format($ringkasan['net'], 0, ',', '.') }}</td>
        </tr>
    </tbody>
</table>

<h6 class="fw-bold">Breakdown Pemasukan per Metode Bayar</h6>
<table class="table table-bordered table-sm mb-3">
    <thead class="table-light"><tr><th>Metode Bayar</th><th class="text-end">Jumlah</th></tr></thead>
    <tbody>
    @forelse($breakdownPemasukan as $b)
    <tr><td>{{ $b['label'] }}</td><td class="text-end">Rp {{ number_format($b['total'], 0, ',', '.') }}</td></tr>
    @empty
    <tr><td colspan="2" class="text-center text-muted">Tidak ada data.</td></tr>
    @endforelse
    </tbody>
</table>

<h6 class="fw-bold">Breakdown Pengeluaran per Kategori</h6>
<table class="table table-bordered table-sm mb-3">
    <thead class="table-light"><tr><th>Kategori</th><th class="text-end">Jumlah</th></tr></thead>
    <tbody>
    @forelse($breakdownPengeluaran as $b)
    <tr><td>{{ $b['label'] }}</td><td class="text-end">Rp {{ number_format($b['total'], 0, ',', '.') }}</td></tr>
    @empty
    <tr><td colspan="2" class="text-center text-muted">Tidak ada data.</td></tr>
    @endforelse
    </tbody>
</table>

<h6 class="fw-bold">Detail Order</h6>
@if($detailOrder->isEmpty())
<p class="text-muted">Tidak ada order di periode ini.</p>
@else
    @foreach($detailOrder as $tanggal => $orders)
    <div class="fw-semibold small mb-1 mt-2">{{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d M Y') }}</div>
    <table class="table table-bordered table-sm mb-2">
        <thead class="table-light">
            <tr><th>Waktu</th><th>No. Order</th><th>Pelanggan</th><th>Kasir</th><th>Tipe Bayar</th><th class="text-end">Total</th></tr>
        </thead>
        <tbody>
        @foreach($orders as $o)
        <tr>
            <td>{{ $o->created_at?->format('H:i') }}</td>
            <td>{{ $o->nomor_order }}</td>
            <td>{{ $o->nama_pelanggan ?? $o->pelanggan?->nama_pelanggan ?? 'Umum' }}</td>
            <td>{{ $o->kasir?->name ?? '-' }}</td>
            <td>{{ $o->tipe_pembayaran?->label() }}</td>
            <td class="text-end">Rp {{ number_format($o->total_bayar, 0, ',', '.') }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endforeach
@endif

<h6 class="fw-bold mt-3">Detail Pengeluaran</h6>
@if($detailPengeluaran->isEmpty())
<p class="text-muted">Tidak ada pengeluaran di periode ini.</p>
@else
    @foreach($detailPengeluaran as $tanggal => $transaksis)
    <div class="fw-semibold small mb-1 mt-2">{{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d M Y') }}</div>
    <table class="table table-bordered table-sm mb-2">
        <thead class="table-light">
            <tr><th>Waktu</th><th>Kategori</th><th>Keterangan</th><th class="text-end">Jumlah</th></tr>
        </thead>
        <tbody>
        @foreach($transaksis as $t)
        <tr>
            <td>{{ $t->created_at?->format('H:i') }}</td>
            <td>{{ $t->label_kategori_pengeluaran }}</td>
            <td>{{ $t->keterangan }}</td>
            <td class="text-end">Rp {{ number_format($t->jumlah, 0, ',', '.') }}</td>
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
