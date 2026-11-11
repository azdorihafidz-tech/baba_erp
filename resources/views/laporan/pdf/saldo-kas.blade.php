@extends('laporan.pdf.layout')

@section('content')
@forelse($kasReports as $r)
<div class="section-head" style="font-weight:bold;margin-bottom:4px;">
    KAS: {{ $r['kas']->nama_kas }} ({{ $r['kas']->cabang?->nama_cabang ?? '-' }})
</div>
<table class="data" style="margin-bottom:14px;">
    <thead>
        <tr><th>Tanggal</th><th>No. Transaksi</th><th>Keterangan</th><th class="num">Debit</th><th class="num">Kredit</th><th class="num">Saldo</th></tr>
    </thead>
    <tbody>
        <tr class="subtotal"><td colspan="5">Saldo Awal Periode</td><td class="num">Rp {{ number_format($r['saldo_awal'], 0, ',', '.') }}</td></tr>
        @forelse($r['mutasi'] as $m)
        <tr>
            <td>{{ \Carbon\Carbon::parse($m['tanggal'])->format('d/m/Y') }}</td>
            <td>{{ $m['nomor'] }}</td>
            <td>{{ $m['keterangan'] }}</td>
            <td class="num">{{ $m['debit'] ? 'Rp '.number_format($m['debit'], 0, ',', '.') : '-' }}</td>
            <td class="num">{{ $m['kredit'] ? 'Rp '.number_format($m['kredit'], 0, ',', '.') : '-' }}</td>
            <td class="num">Rp {{ number_format($m['saldo_running'], 0, ',', '.') }}</td>
        </tr>
        @empty
        <tr><td colspan="6" class="empty-note">Tidak ada mutasi pada periode ini.</td></tr>
        @endforelse
        <tr class="grand"><td colspan="5">Saldo Akhir Periode</td><td class="num">Rp {{ number_format($r['saldo_akhir'], 0, ',', '.') }}</td></tr>
    </tbody>
</table>
@empty
<p class="empty-note">Tidak ada data untuk filter yang dipilih.</p>
@endforelse
@endsection
