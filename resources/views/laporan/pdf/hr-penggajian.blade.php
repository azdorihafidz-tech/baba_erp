@extends('laporan.pdf.layout')

@section('content')
<table class="data">
    <thead>
        <tr><th>Karyawan</th><th>Cabang</th><th class="num">Gaji Pokok</th><th class="num">Tunjangan</th><th class="num">Lembur</th><th class="num">Bonus</th><th class="num">Potongan</th><th class="num">Total Gaji</th><th class="center">Status</th></tr>
    </thead>
    <tbody>
        @forelse($penggajians as $p)
        <tr>
            <td>{{ $p->karyawan?->nama_lengkap ?? '-' }}</td>
            <td>{{ $p->cabang?->nama_cabang ?? '-' }}</td>
            <td class="num">Rp {{ number_format($p->gaji_pokok, 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format($p->tunjangan, 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format($p->uang_lembur, 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format($p->bonus, 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format($p->potongan_absensi + $p->potongan_lain, 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format($p->total_gaji, 0, ',', '.') }}</td>
            <td class="center">{{ ucfirst(str_replace('_',' ',$p->status)) }}</td>
        </tr>
        @empty
        <tr><td colspan="9" class="empty-note">Tidak ada data untuk filter yang dipilih.</td></tr>
        @endforelse
    </tbody>
    @if($penggajians->isNotEmpty())
    <tfoot>
        <tr class="grand"><td colspan="7">TOTAL GAJI</td><td class="num">Rp {{ number_format($totalGaji, 0, ',', '.') }}</td><td></td></tr>
    </tfoot>
    @endif
</table>
@endsection
