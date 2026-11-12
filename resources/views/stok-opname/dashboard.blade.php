@extends('layouts.app')

@section('title', 'Dashboard Stok Opname')

@section('content')
@php
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $ang = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
    $belum = collect($d['status'])->where('status', 'belum')->count();
    $label = ['selesai' => ['Selesai', 'success'], 'diajukan' => ['Menunggu persetujuan', 'warning'], 'hitung' => ['Sedang dihitung', 'info'], 'belum' => ['Belum opname', 'danger']];
@endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard Stok Opname</h5>
    <div class="d-flex gap-2 flex-wrap">
        <x-panduan-button slug="dashboard-stok-opname" />
        <a href="{{ route('stok-opname.laporan') }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-graph-down-arrow"></i> Laporan</a>
        <a href="{{ route('stok-opname.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-clipboard-check"></i> Stok Opname</a>
    </div>
</div>
<form method="GET" class="mb-3" style="max-width:340px">
    @php $adaMitra = $terlihat->contains(fn ($l) => \App\Services\StokOpnameService::lokasiMitra($l)); $adaBaba = $terlihat->contains(fn ($l) => ! \App\Services\StokOpnameService::lokasiMitra($l)); @endphp
    <select name="lokasi" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Lokasi" data-uji="lokasi">
        @if($adaBaba)<option value="">Semua lokasi BABA</option>@endif
        @foreach($terlihat as $l)<option value="{{ $l->id }}" @selected($pilih === (string) $l->id)>{{ $l->nama_cabang }}@if(\App\Services\StokOpnameService::lokasiMitra($l)) (toko mitra)@endif</option>@endforeach
        @if($adaMitra && $adaBaba)<option value="semua" @selected($pilih === 'semua')>Semua lokasi + toko mitra</option>@endif
    </select>
</form>

@if($belum && $masaPengingat)
<div class="alert alert-danger py-2 small" data-uji="peringatan-akhir-bulan"><i class="bi bi-alarm me-1"></i>Akhir bulan: <strong>{{ $belum }} lokasi</strong> belum stok opname bulan ini.</div>
@endif

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body p-3">
            <div class="fw-semibold mb-2">Opname {{ now()->translatedFormat('F Y') }} per lokasi</div>
            <div class="d-flex flex-column gap-1" data-uji="status-lokasi">
            @forelse($d['status'] as $s)
                <div class="d-flex justify-content-between align-items-center border-bottom py-1 small gap-2">
                    <span>{{ $s['lokasi']->nama_cabang }}</span>
                    <span>@if($s['opname'])<a href="{{ route('stok-opname.show', $s['opname']) }}" class="me-1">{{ $s['opname']->nomor }}</a>@endif
                        <span class="badge bg-{{ $label[$s['status']][1] }}" data-uji="status-{{ $s['lokasi']->id }}">{{ $label[$s['status']][0] }}@if($s['status'] === 'selesai' && isset($s['opname']->ringkasan['akurasi'])) · {{ $s['opname']->ringkasan['akurasi'] }}%@endif</span></span>
                </div>
            @empty
                <div class="text-muted small">Belum ada lokasi yang memiliki stok.</div>
            @endforelse
            </div>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body p-3">
            <div class="fw-semibold mb-2">Menunggu persetujuan Anda <span class="badge bg-{{ $d['menunggu']->isEmpty() ? 'secondary' : 'danger' }}">{{ $d['menunggu']->count() }}</span></div>
            <div data-uji="menunggu">
            @forelse($d['menunggu'] as $o)
                <a href="{{ route('stok-opname.show', $o) }}" class="d-flex justify-content-between border-bottom py-1 small text-decoration-none"><span>{{ $o->nomor }} · {{ $o->cabang?->nama_cabang }}</span><span class="text-muted">{{ $o->diajukan_at?->diffForHumans() }}</span></a>
            @empty
                <div class="text-muted small">Tidak ada opname yang menunggu persetujuan Anda.</div>
            @endforelse
            </div>
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body p-3">
            <div class="fw-semibold mb-2">Nilai kerugian per penyebab</div>
            <table class="table table-sm small mb-0" data-uji="kerugian-penyebab">
                <thead><tr><th>Penyebab</th><th class="text-end">Bulan ini</th><th class="text-end">Bulan lalu</th></tr></thead>
                <tbody>
                @foreach(\App\Services\StokOpnameService::PENYEBAB_KURANG as $k)
                    @php $a = $d['ini']['per_penyebab'][$k]['nilai'] ?? 0; $b = $d['lalu']['per_penyebab'][$k]['nilai'] ?? 0; @endphp
                    <tr class="{{ $a || $b ? '' : 'text-muted' }}"><td>{{ \App\Services\StokOpnameService::PENYEBAB[$k]['label'] }}</td>
                        <td class="text-end {{ $a > $b ? 'text-danger fw-semibold' : '' }}">{{ $rp($a) }}@if($a > $b && $b > 0) <i class="bi bi-arrow-up-short"></i>@endif</td><td class="text-end">{{ $rp($b) }}</td></tr>
                @endforeach
                <tr class="fw-semibold"><td>Total kurang</td><td class="text-end text-danger" data-uji="kurang-bulan-ini">{{ $rp($d['ini']['total']['nilai_kurang']) }}</td><td class="text-end">{{ $rp($d['lalu']['total']['nilai_kurang']) }}</td></tr>
                </tbody>
            </table>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body p-3">
            <div class="fw-semibold mb-2">Akurasi stok 6 bulan (%)</div>
            @if($d['akurasi'])
            <div style="position:relative;height:220px"><canvas id="grafikAkurasi" data-uji="grafik-akurasi"></canvas></div>
            @else
            <div class="text-muted small">Belum ada opname yang disetujui dalam 6 bulan terakhir.</div>
            @endif
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body p-3">
            <div class="fw-semibold mb-2">10 barang paling sering selisih (6 bulan)</div>
            <table class="table table-sm small mb-0" data-uji="sering-selisih">
                <thead><tr><th>Barang</th><th class="text-end">Kali</th><th class="text-end">Jumlah</th><th class="text-end">Nilai</th></tr></thead>
                <tbody>
                @forelse($d['sering'] as $s)
                    <tr><td>{{ $s->nama }} <span class="text-muted">· {{ $s->lokasi }}</span></td><td class="text-end">{{ $s->kali }}</td><td class="text-end">{{ $ang($s->qty) }} {{ $s->satuan }}</td><td class="text-end text-danger">{{ $rp($s->nilai) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-muted text-center">Belum ada selisih.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="row g-3">
            <div class="col-12"><div class="card"><div class="card-body p-3">
                <div class="fw-semibold mb-1">Stok rusak tertahan</div>
                <div class="small" data-uji="ringkas-rusak">{{ $d['rusak']['jumlah'] }} barang · nilai {{ $rp($d['rusak']['nilai']) }}@if($d['rusak']['jumlah']) · tertua <span class="{{ $d['rusak']['tertua'] > 30 ? 'text-danger fw-semibold' : '' }}">{{ $d['rusak']['tertua'] }} hari</span>@endif</div>
                @can('stok_opname.stok_rusak')<a href="{{ route('stok-opname.rusak') }}" class="small">Buka Stok Rusak →</a>@endcan
            </div></div></div>
            <div class="col-12"><div class="card"><div class="card-body p-3">
                <div class="fw-semibold mb-1">Ganti rugi karyawan</div>
                <div class="small" data-uji="ringkas-ganti-rugi">Dibebankan {{ $rp($d['ganti_rugi']['dibebankan']) }} · tunai <span class="text-success">{{ $rp($d['ganti_rugi']['tunai']) }}</span> · potong gaji <span class="text-success">{{ $rp($d['ganti_rugi']['potong_gaji']) }}</span> · sisa <span class="text-danger">{{ $rp($d['ganti_rugi']['sisa']) }}</span></div>
                @can('stok_opname.ganti_rugi')<a href="{{ route('stok-opname.ganti-rugi') }}" class="small">Buka Ganti Rugi Karyawan →</a>@endcan
            </div></div></div>
        </div>
    </div>
</div>
@endsection

@if($d['akurasi'])
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(() => {
    const el = document.getElementById('grafikAkurasi'); if (!el || !window.Chart) return;
    const warna = ['#E6B422', '#141414', '#2563eb', '#16a34a', '#dc2626', '#9333ea', '#0891b2', '#ea580c'];
    new Chart(el, { type: 'line', data: { labels: @json($d['bulan']),
        datasets: @json($d['akurasi']).map((s, i) => ({ label: s.lokasi, data: s.data, borderColor: warna[i % warna.length], backgroundColor: warna[i % warna.length], spanGaps: true, tension: .2 })) },
        options: { maintainAspectRatio: false, scales: { y: { min: 0, max: 100 } }, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } } });
})();
</script>
@endpush
@endif
