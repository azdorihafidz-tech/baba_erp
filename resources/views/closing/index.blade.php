@extends('layouts.app')
@section('title', 'Closing / Tutup Buku')
@section('content')

@php
    $jenisLabel = ['harian' => 'Harian', 'mingguan' => 'Mingguan', 'bulanan' => 'Bulanan'][$periode['jenis']];
@endphp

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Closing / Tutup Buku</h4>
        <small class="text-muted">
            {{ $jenisLabel }} · {{ $periode['label'] }} ·
            <i class="bi bi-geo-alt"></i> {{ $data['meta']['cabang_nama'] }}
        </small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('closing.riwayat') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-clock-history me-1"></i>Riwayat Closing
        </a>
        @can('closing.export')
        <a href="{{ route('closing.export', request()->query() + ['format' => 'excel']) }}" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i><span class="d-none d-sm-inline">Excel</span>
        </a>
        <a href="{{ route('closing.export', request()->query() + ['format' => 'pdf']) }}" class="btn btn-danger btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i><span class="d-none d-sm-inline">PDF</span>
        </a>
        @endcan
        <x-panduan-button slug="closing" />
    </div>
</div>

@if($errors->any())
<div class="alert alert-danger small">
    @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
</div>
@endif

{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('closing.index') }}" class="row g-2 align-items-end" id="formFilterClosing">
            <div class="col-6 col-md-2">
                <label class="form-label form-label-sm mb-1">Jenis <x-tooltip key="closing.jenis" /></label>
                <select name="jenis" id="closingJenis" class="form-select form-select-sm">
                    <option value="harian" @selected($periode['jenis'] === 'harian')>Harian</option>
                    <option value="mingguan" @selected($periode['jenis'] === 'mingguan')>Mingguan</option>
                    <option value="bulanan" @selected($periode['jenis'] === 'bulanan')>Bulanan</option>
                </select>
            </div>
            <div class="col-6 col-md-3" id="wrapTanggal">
                <label class="form-label form-label-sm mb-1">Tanggal</label>
                <input type="date" name="tanggal" class="form-control form-control-sm" value="{{ $periode['tanggal'] }}">
            </div>
            <div class="col-6 col-md-3" id="wrapBulan">
                <label class="form-label form-label-sm mb-1">
                    Bulan Periode <x-tooltip key="closing.bulan_periode" />
                </label>
                <input type="month" name="bulan" class="form-control form-control-sm" value="{{ $periode['bulan'] }}">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label form-label-sm mb-1">Cabang <x-tooltip key="closing.cabang" /></label>
                <select name="cabang_id" class="form-select form-select-sm">
                    @if($bolehKonsolidasi)
                    <option value="semua" @selected($cabangId === null)>Semua Cabang (Keseluruhan)</option>
                    @endif
                    @foreach($cabangs as $c)
                    <option value="{{ $c->id }}" @selected($cabangId === (int) $c->id)>{{ $c->nama_cabang }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-1 d-grid">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Tampilkan</button>
            </div>
        </form>
        <div class="small text-muted mt-2">
            Periode: <strong>{{ \Carbon\Carbon::parse($data['meta']['start'])->format('d/m/Y') }}</strong>
            s/d <strong>{{ \Carbon\Carbon::parse($data['meta']['end'])->format('d/m/Y') }}</strong>.
            Periode bulanan dimulai tanggal <strong>{{ $tglMulaiPeriode }}</strong>
            @can('pengaturan.umum_edit')
            (<a href="{{ route('pengaturan.umum') }}">ubah di Pengaturan Umum</a>)
            @endcan
        </div>
    </div>
</div>

{{-- Status & aksi closing --}}
@if($closingAktif)
<div class="alert alert-success d-flex gap-2 align-items-start flex-wrap">
    <i class="bi bi-lock-fill fs-5"></i>
    <div class="small flex-grow-1">
        <strong>Sudah di-closing:</strong> {{ $closingAktif->jenis_label }} {{ $closingAktif->label_periode }}
        (versi {{ $closingAktif->versi }}, {{ $closingAktif->ditutup_pada->format('d/m/Y H:i') }}).
        Periode ini <strong>terkunci</strong> — angka resmi adalah angka di arsip closing, bukan angka preview di bawah.
    </div>
    <a href="{{ route('closing.show', $closingAktif) }}" class="btn btn-success btn-sm">
        <i class="bi bi-archive me-1"></i>Lihat Arsip Closing
    </a>
</div>
@else
<div class="alert alert-warning d-flex gap-2 align-items-start flex-wrap">
    <i class="bi bi-eye fs-5"></i>
    <div class="small flex-grow-1">
        <strong>PREVIEW — angka real-time, belum disimpan/dikunci.</strong>
        Angka di halaman ini dihitung ulang setiap dibuka, jadi bisa berubah kalau ada transaksi periode ini yang
        ditambah, diedit, atau dihapus. Simpan closing untuk membekukan angka dan mengunci periodenya.
    </div>
    @if($periode['jenis'] === 'harian' && $cabangId)
        @can('closing.harian')
            @if($periodeBerakhir)
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalClosingHarian">
                <i class="bi bi-lock me-1"></i>Closing Harian
            </button>
            @else
            <span class="small text-muted">Tanggal yang akan datang belum bisa di-closing.</span>
            @endif
        @endcan
    @elseif($periode['jenis'] === 'bulanan')
        @if($cabangId)
            @can('closing.bulanan')
                @if($periodeBerakhir)
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalClosingBulanan">
                    <i class="bi bi-lock me-1"></i>Tutup Buku Bulanan
                </button>
                @else
                <span class="small text-muted">Periode belum berakhir ({{ $periode['end']->format('d/m/Y') }}).</span>
                @endif
            @endcan
        @elseif($bolehKonsolidasi)
            @can('closing.bulanan')
                @if($cabangBelumTutupBuku->isNotEmpty())
                <span class="small text-muted">Belum tutup buku: {{ $cabangBelumTutupBuku->pluck('nama_cabang')->implode(', ') }}</span>
                @elseif(!$periodeBerakhir)
                <span class="small text-muted">Periode belum berakhir ({{ $periode['end']->format('d/m/Y') }}).</span>
                @else
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalClosingBulanan">
                    <i class="bi bi-lock me-1"></i>Tutup Buku Keseluruhan
                </button>
                @endif
            @endcan
        @endif
    @elseif($periode['jenis'] === 'mingguan')
        <span class="small text-muted">Mingguan adalah rekap dari closing harian (tanpa kunci terpisah).</span>
    @endif
</div>
@endif

{{-- Rekap mingguan --}}
@if(!empty($rekapMingguan))
<div class="card mb-3">
    <div class="card-header fw-semibold"><i class="bi bi-calendar-week me-1"></i> Status Closing Harian Minggu Ini</div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-sm mb-0 align-middle text-center small">
            <thead class="table-light">
                <tr>
                    <th class="text-start">Cabang</th>
                    @foreach($rekapMingguan[0]['hari'] as $h)
                    <th>{{ \Carbon\Carbon::parse($h['tanggal'])->locale('id')->translatedFormat('D d/m') }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($rekapMingguan as $row)
                <tr>
                    <td class="text-start">{{ $row['cabang'] }}</td>
                    @foreach($row['hari'] as $h)
                    <td>
                        @if($h['closing_id'])
                            <a href="{{ route('closing.show', $h['closing_id']) }}" class="text-success" title="{{ $h['jenis'] === 'harian' ? 'Closing harian' : 'Terkunci tutup buku bulanan' }}">
                                <i class="bi bi-check-circle-fill"></i>
                            </a>
                            @if($h['selisih'] !== null && (float) $h['selisih'] != 0)
                            <div class="{{ (float) $h['selisih'] < 0 ? 'text-danger' : 'text-success' }}" style="font-size:.7rem">{{ number_format((float) $h['selisih'], 0, ',', '.') }}</div>
                            @endif
                        @elseif($h['akan_datang'])
                            <span class="text-muted">·</span>
                        @else
                            <span class="text-warning" title="Belum closing"><i class="bi bi-exclamation-circle"></i></span>
                        @endif
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="small text-muted px-2 py-2">
            <i class="bi bi-check-circle-fill text-success"></i> sudah closing ·
            <i class="bi bi-exclamation-circle text-warning"></i> belum closing · angka kecil = selisih uang fisik.
        </div>
    </div>
</div>
@endif


@include('closing._laporan', ['data' => $data, 'cabangId' => $cabangId])

{{-- Modal Closing Harian --}}
@if(!$closingAktif && $periode['jenis'] === 'harian' && $cabangId)
@can('closing.harian')
<div class="modal fade" id="modalClosingHarian" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
        <form method="POST" action="{{ route('closing.harian.store') }}" class="modal-content" id="formClosingHarian">
            @csrf
            <input type="hidden" name="cabang_id" value="{{ $cabangId }}">
            <input type="hidden" name="tanggal" value="{{ $periode['start']->toDateString() }}">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-lock me-1"></i> Closing Harian</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="small mb-2">
                    {{ $periode['label'] }} · {{ $data['meta']['cabang_nama'] }}.<br>
                    Hitung uang di laci untuk setiap kas tunai, lalu isi jumlahnya. Selisih dengan saldo sistem akan dicatat.
                </p>
                @forelse($kasTunai as $kas)
                <div class="mb-2">
                    <label class="form-label form-label-sm mb-1">
                        {{ $kas->nama_kas }} <x-tooltip key="closing.uang_fisik" />
                    </label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">Rp</span>
                        <input type="number" step="1" min="0" class="form-control" name="fisik[{{ $kas->id }}]" required
                            value="{{ old('fisik.' . $kas->id) }}" placeholder="Uang fisik di laci">
                    </div>
                    <div class="form-text">Saldo sistem: Rp {{ number_format((float) ($saldoSistem[$kas->id] ?? 0), 0, ',', '.') }}</div>
                </div>
                @empty
                <div class="alert alert-info small mb-2">Cabang ini tidak punya kas tunai aktif — tidak perlu hitung uang fisik.</div>
                @endforelse
                <div class="mb-2">
                    <label class="form-label form-label-sm mb-1">Catatan (opsional)</label>
                    <textarea name="catatan" class="form-control form-control-sm" rows="2" maxlength="1000">{{ old('catatan') }}</textarea>
                </div>
                <div class="alert alert-warning small mb-0">
                    Setelah disimpan, transaksi & order tanggal ini <strong>tidak bisa ditambah, diubah, atau dihapus</strong>
                    kecuali Owner membuka kunci.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-lock me-1"></i>Simpan & Kunci</button>
            </div>
        </form>
    </div>
</div>
@endcan
@endif

{{-- Modal Tutup Buku Bulanan / Keseluruhan --}}
@if(!$closingAktif && $periode['jenis'] === 'bulanan' && $periodeBerakhir && ($cabangId || ($bolehKonsolidasi && $cabangBelumTutupBuku->isEmpty())))
@can('closing.bulanan')
<div class="modal fade" id="modalClosingBulanan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-sm-down">
        <form method="POST" action="{{ route('closing.bulanan.store') }}" class="modal-content" id="formClosingBulanan">
            @csrf
            <input type="hidden" name="cabang_id" value="{{ $cabangId ?? 'semua' }}">
            <input type="hidden" name="bulan" value="{{ $periode['bulan'] }}">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-lock me-1"></i> {{ $cabangId ? 'Tutup Buku Bulanan' : 'Tutup Buku Keseluruhan' }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="small">
                    <strong>{{ $periode['label'] }}</strong> · {{ $data['meta']['cabang_nama'] }}<br>
                    Omzet {{ 'Rp ' . number_format($data['penjualan']['omzet_bersih'], 0, ',', '.') }} ·
                    Laba bersih {{ 'Rp ' . number_format($data['laba_rugi']['laba_bersih'], 0, ',', '.') }}
                </p>
                <div class="mb-2">
                    <label class="form-label form-label-sm mb-1">Catatan (opsional)</label>
                    <textarea name="catatan" class="form-control form-control-sm" rows="2" maxlength="1000">{{ old('catatan') }}</textarea>
                </div>
                <div class="alert alert-warning small mb-0">
                    @if($cabangId)
                    Angka periode ini akan dibekukan dan seluruh transaksi & order di dalam periode
                    <strong>dikunci</strong>. Pastikan semua transaksi bulan ini sudah diinput dengan benar.
                    @else
                    Angka gabungan semua cabang akan dibekukan. Kunci tetap berasal dari tutup buku masing-masing cabang.
                    @endif
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-lock me-1"></i>Simpan & Kunci</button>
            </div>
        </form>
    </div>
</div>
@endcan
@endif

@push('scripts')
<script>
(function () {
    const jenis = document.getElementById('closingJenis');
    const wrapTanggal = document.getElementById('wrapTanggal');
    const wrapBulan = document.getElementById('wrapBulan');
    function toggle() {
        const bulanan = jenis.value === 'bulanan';
        wrapTanggal.classList.toggle('d-none', bulanan);
        wrapBulan.classList.toggle('d-none', !bulanan);
        wrapTanggal.querySelector('input').disabled = bulanan;
        wrapBulan.querySelector('input').disabled = !bulanan;
    }
    jenis.addEventListener('change', toggle);
    toggle();
})();
</script>
@endpush
@endsection
