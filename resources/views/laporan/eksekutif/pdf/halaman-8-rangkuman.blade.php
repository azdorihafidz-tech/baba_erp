@include('laporan.eksekutif.pdf._page-header', ['judulHalaman' => 'Halaman 8 — Rangkuman Final'])

@php
    $r = $rangkumanFinal;
    $labelBadge = ['Sehat' => 'badge-positif', 'Perlu Perhatian' => 'badge-perhatian', 'Kritis' => 'badge-kritis'];
    $namaDimensi = [
        'profitabilitas' => 'Profitabilitas',
        'likuiditas' => 'Likuiditas',
        'pencapaian_bep' => 'Pencapaian BEP',
        'efisiensi_beban' => 'Efisiensi Beban',
        'solvabilitas' => 'Solvabilitas',
    ];
    $severityClass = ['kritis' => 'kesimpulan-perhatian', 'perhatian' => 'kesimpulan-perhatian', 'positif' => 'kesimpulan-positif'];
@endphp

<table class="summary-table">
    <tr>
        <td colspan="1"><div class="val">{{ number_format($r['health_score']['skor_rata_rata'], 1, ',', '.') }}/100</div><div class="lbl">Skor Kesehatan Finansial</div></td>
        <td colspan="3"><span class="badge {{ $labelBadge[$r['health_score']['label']] ?? '' }}" style="font-size:11px; padding:4px 10px;">{{ strtoupper($r['health_score']['label']) }}</span></td>
    </tr>
</table>

<table class="data">
    <tr><th>Dimensi</th><th class="num">Skor (0-100)</th></tr>
    @foreach($r['health_score']['dimensi'] as $key => $skor)
    <tr><td>{{ $namaDimensi[$key] ?? $key }}</td><td class="num">{{ number_format($skor, 1, ',', '.') }}</td></tr>
    @endforeach
</table>
<div class="footer-note">Skor heuristik rule-based berbasis threshold internal — BUKAN standar penilaian akuntansi/audit baku. Wajar rendah untuk periode awal bulan (biaya tetap tercatat penuh, pendapatan baru terkumpul sedikit).</div>

<div class="page-heading" style="font-size:10.5px; margin-top:10px">3 Poin Utama untuk Direksi</div>
@foreach($r['poin_utama'] as $p)
<div class="kesimpulan-item {{ $severityClass[$p['severity']] ?? '' }}">{{ $p['teks'] }}</div>
@endforeach

<div class="page-heading" style="font-size:10.5px; margin-top:10px">Pertanyaan untuk Direksi</div>
<table class="data">
    @foreach($r['pertanyaan_direksi'] as $i => $q)
    <tr><td>{{ $i + 1 }}. {{ $q }}</td></tr>
    @endforeach
</table>

<div class="page-heading" style="font-size:10.5px; margin-top:10px">Highlight Halaman 1-7</div>
<table class="data">
    <tr><td>Laba Bersih Bulan Ini</td><td class="num">Rp {{ number_format($r['highlight']['laba_bersih_bulan_ini'], 0, ',', '.') }}</td></tr>
    <tr><td>BEP Tercapai</td><td class="num">{{ $r['highlight']['bep_persentase_tercapai'] !== null ? number_format($r['highlight']['bep_persentase_tercapai'], 1, ',', '.') . '%' : '-' }}</td></tr>
    <tr><td>Neraca Balance</td><td class="num">{{ $r['highlight']['balance_check'] ? 'Ya' : 'Tidak' }}</td></tr>
    <tr><td>Posisi Kas</td><td class="num">Rp {{ number_format($r['highlight']['posisi_kas'], 0, ',', '.') }}</td></tr>
    <tr><td>Estimasi Cash Runway</td><td class="num">{{ $r['highlight']['cash_runway_bulan'] !== null ? number_format($r['highlight']['cash_runway_bulan'], 1, ',', '.') . ' bulan' : '-' }}</td></tr>
    <tr><td>Verifikasi Konsistensi Data</td><td class="num">{{ $r['highlight']['semua_cross_check_match'] ? 'Semua MATCH' : 'ADA MISMATCH' }}</td></tr>
</table>
