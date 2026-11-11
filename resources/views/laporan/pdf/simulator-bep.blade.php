@extends('laporan.pdf.layout')

@section('content')

<table class="data" style="margin-bottom:14px">
    <tr><th colspan="2">PARAMETER INPUT</th></tr>
    <tr><td style="width:50%">Volume Harian (kg)</td><td class="text-end">{{ number_format($volumeHarian, 2, ',', '.') }}</td></tr>
    <tr><td>Harga Jual per kg</td><td class="text-end">Rp {{ number_format($hargaJual, 0, ',', '.') }}</td></tr>
    <tr><td>Biaya Variabel per kg</td><td class="text-end">Rp {{ number_format($biayaVariabel, 0, ',', '.') }}</td></tr>
    <tr><td>Beban Tetap Bulanan</td><td class="text-end">Rp {{ number_format($bebanTetap, 0, ',', '.') }}</td></tr>
    <tr><td>Modal Awal</td><td class="text-end">Rp {{ number_format($modalAwal, 0, ',', '.') }}</td></tr>
    <tr><td>Target Profit</td><td class="text-end">{{ $targetProfit !== null ? 'Rp '.number_format($targetProfit, 0, ',', '.') : '-' }}</td></tr>
</table>

<table class="data" style="margin-bottom:14px">
    <tr><th colspan="2">HASIL PERHITUNGAN</th></tr>
    @if(!$feasible)
    <tr><td colspan="2" style="color:#dc2626;font-weight:bold">BEP TIDAK BISA DICAPAI — margin kontribusi negatif/nol.</td></tr>
    @else
    <tr><td style="width:50%">BEP dalam Unit</td><td class="text-end">{{ number_format($bepUnit, 2, ',', '.') }} kg</td></tr>
    <tr><td>BEP dalam Rupiah</td><td class="text-end">Rp {{ number_format($bepRupiah, 0, ',', '.') }}</td></tr>
    <tr><td>Margin Kontribusi per Unit</td><td class="text-end">Rp {{ number_format($margin, 2, ',', '.') }}</td></tr>
    <tr><td>Margin Kontribusi Ratio</td><td class="text-end">{{ $marginRatio !== null ? number_format($marginRatio, 1, ',', '.').'%' : '-' }}</td></tr>
    <tr><td>Volume Bulanan</td><td class="text-end">{{ number_format($volumeBulanan, 2, ',', '.') }} kg</td></tr>
    <tr><td>Omzet Bulanan</td><td class="text-end">Rp {{ number_format($omzetBulanan, 0, ',', '.') }}</td></tr>
    <tr><td>Laba/Rugi Bulanan</td><td class="text-end">Rp {{ number_format($labaBulanan, 0, ',', '.') }}</td></tr>
    <tr><td>Margin of Safety</td><td class="text-end">{{ $marginOfSafetyPersen !== null ? number_format($marginOfSafetyPersen, 1, ',', '.').'%' : '-' }}</td></tr>
    @endif
</table>

<div style="border:1px solid #cbd5e1;border-radius:4px;padding:10px 12px;margin-bottom:14px;background:#f8fafc;font-size:10.5px">
    <strong>Kesimpulan:</strong>
    <ul style="margin:6px 0 0 16px;padding:0">
        @foreach($kesimpulan as $baris)
        <li>{{ $baris }}</li>
        @endforeach
    </ul>
</div>

<table class="data">
    <thead>
        <tr>
            <th>Tabel Sensitivitas (BEP Unit, kg)</th>
            @foreach(array_keys($sensitivitas[0]['kolom']) as $hargaPct)
            <th class="text-end">Harga {{ $hargaPct >= 0 ? '+' : '' }}{{ $hargaPct }}%</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($sensitivitas as $baris)
        <tr>
            <td>Biaya {{ $baris['biaya_persen'] >= 0 ? '+' : '' }}{{ $baris['biaya_persen'] }}%</td>
            @foreach($baris['kolom'] as $hargaPct => $nilai)
            <td class="text-end {{ $baris['biaya_persen'] === 0 && $hargaPct === 0 ? 'highlight-warning' : '' }}">
                {{ $nilai !== null ? number_format($nilai, 2, ',', '.') : 'N/A' }}
            </td>
            @endforeach
        </tr>
        @endforeach
    </tbody>
</table>

@endsection
