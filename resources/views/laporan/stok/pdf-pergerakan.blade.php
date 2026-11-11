@extends('laporan.pdf.layout')

@section('content')
<div class="alert-note" style="font-size:9px;color:#94a3b8;margin-bottom:6px;">
    Untuk stok saat ini per bahan, lihat Laporan Stok (Index).
</div>
<table class="data">
    <thead>
        <tr>
            <th>Tanggal</th>
            <th>Kode</th>
            <th>Bahan</th>
            <th>Cabang</th>
            <th>Tipe</th>
            <th class="num">Qty</th>
            <th>Satuan</th>
            <th>Catatan</th>
        </tr>
    </thead>
    <tbody>
        @forelse($movements as $m)
        @php
            $cabangNama = $m->tipe?->value === 'keluar'
                ? ($m->lokasiAsal?->nama_cabang ?? '-')
                : ($m->lokasiTujuan?->nama_cabang ?? $m->lokasiAsal?->nama_cabang ?? '-');
        @endphp
        <tr>
            <td>{{ $m->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
            <td>{{ $m->item?->kode_item ?? '-' }}</td>
            <td>{{ $m->item?->nama_item ?? '-' }}</td>
            <td>{{ $cabangNama }}</td>
            <td>{{ ucfirst($m->tipe?->value ?? '-') }}</td>
            <td class="num">{{ number_format($m->qty, 2, ',', '.') }}</td>
            <td>{{ $m->item?->satuan ?? '-' }}</td>
            <td>{{ $m->catatan ?? '-' }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="8" class="empty-note">Tidak ada data untuk filter yang dipilih.</td>
        </tr>
        @endforelse
    </tbody>
</table>
@endsection
