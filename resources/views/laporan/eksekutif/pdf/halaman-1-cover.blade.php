@include('laporan.eksekutif.pdf._page-header', ['judulHalaman' => 'Halaman 1 — Cover Overview'])

@php
    $o = $overview;
    $topAset = collect($o['aset_tetap_breakdown']['detail'])->sortByDesc('nilai_buku')->take(10);
    $sisaAset = count($o['aset_tetap_breakdown']['detail']) - $topAset->count();
    $kesimpulanClass = ['positif' => 'kesimpulan-positif', 'perhatian' => 'kesimpulan-perhatian', 'kunci' => 'kesimpulan-kunci'];
@endphp

<table class="summary-table">
    <tr>
        <td><div class="val">Rp {{ number_format($o['modal_awal'], 0, ',', '.') }}</div><div class="lbl">Modal Awal (Owner)</div></td>
        <td><div class="val">Rp {{ number_format($o['aset_tetap_breakdown']['nilai_buku'], 0, ',', '.') }}</div><div class="lbl">Nilai Buku Aset Tetap</div></td>
        <td><div class="val">Rp {{ number_format($o['kas_summary']['posisi_kas'], 0, ',', '.') }}</div><div class="lbl">Posisi Kas Saat Ini</div></td>
        <td><div class="val">{{ $o['bep_vs_realisasi']['persentase_tercapai'] !== null ? number_format($o['bep_vs_realisasi']['persentase_tercapai'], 1, ',', '.') . '%' : '-' }}</div><div class="lbl">BEP Tercapai (Bulan Ini)</div></td>
    </tr>
</table>

<div class="page-heading" style="font-size:10.5px">Poin Kunci</div>
@foreach($o['kesimpulan'] as $poin)
<div class="kesimpulan-item {{ $kesimpulanClass[$poin['kategori']] ?? '' }}">{{ $poin['teks'] }}</div>
@endforeach

<table class="data" style="margin-top:8px">
    <tr><th colspan="4">ASET TETAP — TOP {{ $topAset->count() }} BERDASARKAN NILAI BUKU</th></tr>
    <tr><th>Nama Aset</th><th class="num">Harga Perolehan</th><th class="num">Akum. Depresiasi</th><th class="num">Nilai Buku</th></tr>
    @foreach($topAset as $a)
    <tr>
        <td>{{ $a['nama'] }}</td>
        <td class="num">Rp {{ number_format($a['harga_perolehan'], 0, ',', '.') }}</td>
        <td class="num">Rp {{ number_format($a['akum_depresiasi'], 0, ',', '.') }}</td>
        <td class="num">Rp {{ number_format($a['nilai_buku'], 0, ',', '.') }}</td>
    </tr>
    @endforeach
    <tr class="grand"><td>Total ({{ count($o['aset_tetap_breakdown']['detail']) }} aset)</td>
        <td class="num">Rp {{ number_format($o['aset_tetap_breakdown']['aset_bruto'], 0, ',', '.') }}</td>
        <td class="num">Rp {{ number_format($o['aset_tetap_breakdown']['akumulasi_depresiasi'], 0, ',', '.') }}</td>
        <td class="num">Rp {{ number_format($o['aset_tetap_breakdown']['nilai_buku'], 0, ',', '.') }}</td>
    </tr>
</table>
@if($sisaAset > 0)
<div class="footer-note">+{{ $sisaAset }} aset lainnya — lihat Halaman 2 (Neraca) untuk breakdown lengkap.</div>
@endif

<table class="data" style="margin-top:8px">
    <tr><th colspan="2">PENYUSUTAN</th><th colspan="2">TRANSAKSI &amp; PRODUKSI (BULAN BERJALAN)</th></tr>
    <tr>
        <td>Penyusutan Bulan Ini</td><td class="num">Rp {{ number_format($o['penyusutan_summary']['bulanan'], 0, ',', '.') }}</td>
        <td>Total Order</td><td class="num">{{ number_format($o['transaksi_summary']['total_order'], 0, ',', '.') }}</td>
    </tr>
    <tr>
        <td>Akumulasi Penyusutan</td><td class="num">Rp {{ number_format($o['penyusutan_summary']['akumulasi'], 0, ',', '.') }}</td>
        <td>Total Nominal</td><td class="num">Rp {{ number_format($o['transaksi_summary']['total_nominal'], 0, ',', '.') }}</td>
    </tr>
    <tr>
        <td>Sisa Umur Ekonomis Rata-rata</td><td class="num">{{ $o['penyusutan_summary']['sisa_umur_rata2_bulan'] !== null ? number_format($o['penyusutan_summary']['sisa_umur_rata2_bulan'], 1, ',', '.') . ' bulan' : '-' }}</td>
        <td>Rata-rata / Order</td><td class="num">Rp {{ number_format($o['transaksi_summary']['rata_rata_per_order'], 0, ',', '.') }}</td>
    </tr>
    <tr>
        <td>Produksi Jasa Giling (kg)</td><td class="num">{{ number_format($o['produksi_summary']['total_kg'], 2, ',', '.') }} kg</td>
        <td>Rata-rata Harian</td><td class="num">{{ number_format($o['produksi_summary']['rata_rata_harian_kg'], 2, ',', '.') }} kg</td>
    </tr>
    @if($o['produksi_summary']['peak_day'])
    <tr>
        <td>Hari Produksi Tertinggi</td>
        <td class="num">{{ \Carbon\Carbon::parse($o['produksi_summary']['peak_day']['tanggal'])->format('d/m/Y') }} ({{ number_format($o['produksi_summary']['peak_day']['kg'], 2, ',', '.') }} kg)</td>
        <td></td><td></td>
    </tr>
    @endif
</table>

<div class="footer-note">Detail lengkap: <a href="{{ route('laporan.neraca.index') }}">Menu Neraca</a> · <a href="{{ route('laporan.bep-otomatis.index') }}">Menu BEP Otomatis</a> · <a href="{{ route('dashboard.pusat') }}">Dashboard</a></div>
