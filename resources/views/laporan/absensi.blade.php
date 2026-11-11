@extends('layouts.app')

@section('title', 'Laporan Absensi Wajah')

@push('styles')
<style>
    .badge-hadir      { background:#dcfce7;color:#16a34a;border:1px solid #bbf7d0; }
    .badge-lembur     { background:#dbeafe;color:#1d4ed8;border:1px solid #bfdbfe; }
    .badge-telat      { background:#fef9c3;color:#b45309;border:1px solid #fde68a; }
    .badge-telat-lem  { background:#ffedd5;color:#c2410c;border:1px solid #fed7aa; }
    .badge-alpha      { background:#fee2e2;color:#b91c1c;border:1px solid #fecaca; }
    .jam-cell { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .jam-val  { font-weight: 600; color: #1e293b; }
    .jam-dur  { font-size: .7rem; color: #64748b; }
    .icon-sm  { font-size: .72rem; opacity: .65; }
    .icon-sm:hover { opacity: 1; }
    .quick-filter-btn {
        padding: 4px 10px; border-radius: 20px; font-size: .78rem; font-weight: 500;
        border: 1px solid #e2e8f0; background: white; color: #475569; cursor: pointer;
        white-space: nowrap; transition: all .15s;
    }
    .quick-filter-btn:hover, .quick-filter-btn.active {
        background: #3b82f6; border-color: #3b82f6; color: white;
    }
    @media print { .no-print { display: none !important; } }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Laporan Absensi Wajah</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Laporan Absensi Wajah</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2 flex-wrap no-print">
        <a href="{{ route('laporan.absensi.excel', request()->query()) }}"
           class="btn btn-sm btn-outline-success">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Excel
        </a>
        <a href="{{ route('laporan.absensi.pdf', request()->query()) }}"
           target="_blank" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
        </a>
        <x-panduan-button slug="laporan-absensi" />
    </div>
</div>

{{-- ===== FILTER ===== --}}
<div class="card mb-3 no-print">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('laporan.absensi') }}" id="filterForm">
            <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
                <span class="text-muted" style="font-size:.8rem">Quick:</span>
                @php
                    $quickFilters = [
                        'Hari Ini'   => [today()->toDateString(), today()->toDateString()],
                        'Minggu Ini' => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
                        'Bulan Ini'  => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
                        'Bulan Lalu' => [now()->subMonth()->startOfMonth()->toDateString(), now()->subMonth()->endOfMonth()->toDateString()],
                        '30 Hari'    => [now()->subDays(29)->toDateString(), today()->toDateString()],
                    ];
                @endphp
                @foreach($quickFilters as $label => [$d, $s])
                <button type="button" class="quick-filter-btn {{ $dari === $d && $sampai === $s ? 'active' : '' }}"
                        onclick="setQuickFilter('{{ $d }}','{{ $s }}')">{{ $label }}</button>
                @endforeach
            </div>
            <div class="row g-2 align-items-end">
                <x-search-box placeholder="Nama / NIK / jabatan..." col="col-12 col-sm-6 col-md-3" label="Cari" />
                <div class="col-6 col-sm-3 col-md-2">
                    <label class="form-label small mb-1">Dari Tanggal</label>
                    <input type="date" name="dari" id="inputDari" class="form-control form-control-sm" value="{{ $dari }}">
                </div>
                <div class="col-6 col-sm-3 col-md-2">
                    <label class="form-label small mb-1">Sampai Tanggal</label>
                    <input type="date" name="sampai" id="inputSampai" class="form-control form-control-sm" value="{{ $sampai }}">
                </div>
                @if($canAllBranches)
                <div class="col-6 col-sm-3 col-md-2">
                    <label class="form-label small mb-1">Cabang</label>
                    <select name="cabang_id" class="form-select form-select-sm">
                        <option value="">Semua Cabang</option>
                        @foreach($cabangs as $c)
                        <option value="{{ $c->id }}" {{ $cabangId == $c->id ? 'selected' : '' }}>{{ $c->nama_cabang }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-6 col-sm-3 col-md-2">
                    <label class="form-label small mb-1">Karyawan</label>
                    <select name="karyawan_id" class="form-select form-select-sm">
                        <option value="">Semua Karyawan</option>
                        @foreach($karyawanList as $k)
                        <option value="{{ $k->id }}" {{ $karyawanFil == $k->id ? 'selected' : '' }}>{{ $k->nama_lengkap }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-sm-3 col-md-2">
                    <label class="form-label small mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="all"        {{ $statusFil === 'all'        ? 'selected' : '' }}>Semua</option>
                        <option value="hadir"      {{ $statusFil === 'hadir'      ? 'selected' : '' }}>Hadir</option>
                        <option value="ada_lembur" {{ $statusFil === 'ada_lembur' ? 'selected' : '' }}>Ada Lembur</option>
                        <option value="telat"      {{ $statusFil === 'telat'      ? 'selected' : '' }}>Telat</option>
                    </select>
                </div>
                <div class="col-auto d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <a href="{{ route('laporan.absensi') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x me-1"></i>Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ===== STATISTIK ===== --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card text-center">
            <div class="card-body py-2 px-3">
                <div class="fw-bold" style="font-size:1.5rem;color:#22c55e">{{ number_format($stats['hari_hadir']) }}</div>
                <div class="text-muted" style="font-size:.78rem">Hari Hadir</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center">
            <div class="card-body py-2 px-3">
                <div class="fw-bold" style="font-size:1.5rem;color:#8b5cf6">{{ number_format($stats['unik_karyawan']) }}</div>
                <div class="text-muted" style="font-size:.78rem">Karyawan Unik</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center">
            <div class="card-body py-2 px-3">
                <div class="fw-bold" style="font-size:1.5rem;color:#3b82f6">{{ number_format($stats['hari_lembur']) }}</div>
                <div class="text-muted" style="font-size:.78rem">Hari Ada Lembur</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center">
            <div class="card-body py-2 px-3">
                <div class="fw-bold" style="font-size:1.5rem;color:#f59e0b">{{ number_format($stats['hari_telat']) }}</div>
                <div class="text-muted" style="font-size:.78rem">Hari Telat</div>
            </div>
        </div>
    </div>
</div>

{{-- ===== TABEL ===== --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between py-2 px-3">
        <span class="fw-semibold" style="font-size:.9rem">
            <i class="bi bi-table me-1"></i>Data Absensi
            <span class="text-muted fw-normal" style="font-size:.8rem">{{ $dari }} s/d {{ $sampai }}</span>
        </span>
        <span class="badge bg-primary-subtle text-primary">{{ $records->total() }} record</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle" style="font-size:.82rem">
            <thead class="table-light">
                <tr>
                    <th class="px-3" style="width:36px">No</th>
                    <th style="min-width:90px">Tanggal</th>
                    <th style="min-width:150px">Karyawan</th>
                    @if($canAllBranches)
                    <th class="d-none d-md-table-cell" style="min-width:110px">Cabang</th>
                    @endif
                    <th style="min-width:80px">Masuk</th>
                    <th class="d-none d-sm-table-cell" style="min-width:80px">Keluar</th>
                    <th class="d-none d-lg-table-cell" style="min-width:80px">Lembur Masuk</th>
                    <th class="d-none d-lg-table-cell" style="min-width:80px">Lembur Keluar</th>
                    <th class="d-none d-md-table-cell" style="min-width:70px">Total</th>
                    <th class="d-none d-xl-table-cell" style="min-width:70px">Total Lembur</th>
                    <th style="min-width:100px">Status</th>
                    <th class="text-center no-print" style="width:{{ $canAllBranches ? '80px' : '44px' }}">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($records as $i => $row)
                @php
                    $masukRaw  = $row->getRawOriginal('jam_masuk');
                    $keluarRaw = $row->getRawOriginal('jam_keluar');
                    $lemMasukR = $row->getRawOriginal('jam_lembur_masuk');
                    $lemKelR   = $row->getRawOriginal('jam_lembur_keluar');

                    // hitungDurasi inline
                    $durHitung = function(?string $a, ?string $b): ?string {
                        if (!$a || !$b) return null;
                        try {
                            $m = \Carbon\Carbon::createFromFormat('H:i:s', substr($a,0,8));
                            $k = \Carbon\Carbon::createFromFormat('H:i:s', substr($b,0,8));
                            if ($k->lte($m)) return null;
                            $min = $m->diffInMinutes($k);
                            return sprintf('%dj %02dm', intdiv($min,60), $min%60);
                        } catch (\Exception $e) { return null; }
                    };
                    $totalKerja  = $durHitung($masukRaw, $keluarRaw);
                    $totalLembur = $durHitung($lemMasukR, $lemKelR);

                    // labelStatus inline
                    $terlambat   = str_contains((string)$row->keterangan, 'Terlambat');
                    $adaLembur   = !empty($row->jam_lembur_masuk);
                    $statusLabel = !$row->jam_masuk ? 'Tidak Hadir'
                        : ($terlambat && $adaLembur ? 'Telat + Lembur'
                        : ($terlambat ? 'Telat'
                        : ($adaLembur ? 'Hadir + Lembur' : 'Hadir')));

                    $statusBadge = match(true) {
                        str_contains($statusLabel,'Lembur') && str_contains($statusLabel,'Telat') => 'badge-telat-lem',
                        str_contains($statusLabel,'Telat')  => 'badge-telat',
                        str_contains($statusLabel,'Lembur') => 'badge-lembur',
                        $row->jam_masuk !== null             => 'badge-hadir',
                        default                              => 'badge-alpha',
                    };

                    // Foto & GPS with fallback to face_attendance FK
                    $fm  = $row->faceAttendanceMasuk;
                    $fk  = $row->faceAttendanceKeluar;
                    $flm = $row->faceAttendanceLemburMasuk;
                    $flk = $row->faceAttendanceLemburKeluar;

                    $fotoM  = $row->foto_masuk         ?? $fm?->foto_absen;
                    $fotoK  = $row->foto_keluar        ?? $fk?->foto_absen;
                    $fotoLM = $row->foto_lembur_masuk  ?? $flm?->foto_absen;
                    $fotoLK = $row->foto_lembur_keluar ?? $flk?->foto_absen;

                    $latM  = $row->lat_masuk         ?? $fm?->latitude;
                    $lngM  = $row->lng_masuk         ?? $fm?->longitude;
                    $latK  = $row->lat_keluar        ?? $fk?->latitude;
                    $lngK  = $row->lng_keluar        ?? $fk?->longitude;
                    $latLM = $row->lat_lembur_masuk  ?? $flm?->latitude;
                    $lngLM = $row->lng_lembur_masuk  ?? $flm?->longitude;
                    $latLK = $row->lat_lembur_keluar ?? $flk?->latitude;
                    $lngLK = $row->lng_lembur_keluar ?? $flk?->longitude;

                    // Build detail JSON for modal
                    // manual per-section: true kalau TIDAK ada face_attendance FK DAN tidak ada foto di kolom absensis
                    $isMasukManual       = is_null($fm)  && is_null($row->foto_masuk);
                    $isKeluarManual      = is_null($fk)  && is_null($row->foto_keluar);
                    $isLemburMasukManual = is_null($flm) && is_null($row->foto_lembur_masuk);
                    $isLemburKeluarManual= is_null($flk) && is_null($row->foto_lembur_keluar);

                    $buildSec = function($jam, $foto, $lat, $lng, $fa, $manual) {
                        if (!$jam) return null;
                        return [
                            'jam'        => $jam,
                            'foto'       => $foto ? url('/img/'.$foto) : null,
                            'lat'        => ($lat !== null && $lat !== '') ? (float)$lat : null,
                            'lng'        => ($lng !== null && $lng !== '') ? (float)$lng : null,
                            'jarak'      => $fa?->jarak_dari_cabang,
                            'device'     => $fa?->device?->device_name,
                            'confidence' => $fa?->confidence_score !== null
                                            ? number_format((float)$fa->confidence_score, 1)
                                            : null,
                            'liveness'   => isset($fa->is_liveness_passed) ? (bool)$fa->is_liveness_passed : null,
                            'manual'     => $manual,
                        ];
                    };
                    $detailJson = json_encode([
                        'nama'        => $row->karyawan?->nama_lengkap ?? '-',
                        'tanggal'     => $row->tanggal->format('d F Y'),
                        'tanggal_raw' => $row->tanggal->format('Y-m-d'),
                        'karyawan_id' => $row->karyawan_id,
                        'masuk'       => $buildSec($masukRaw  ? substr($masukRaw, 0, 5)  : null, $fotoM,  $latM,  $lngM,  $fm,  $isMasukManual),
                        'keluar'      => $buildSec($keluarRaw ? substr($keluarRaw, 0, 5) : null, $fotoK,  $latK,  $lngK,  $fk,  $isKeluarManual),
                        'lembur_masuk'  => $buildSec($lemMasukR ? substr($lemMasukR, 0, 5) : null, $fotoLM, $latLM, $lngLM, $flm, $isLemburMasukManual),
                        'lembur_keluar' => $buildSec($lemKelR   ? substr($lemKelR, 0, 5)   : null, $fotoLK, $latLK, $lngLK, $flk, $isLemburKeluarManual),
                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                @endphp
                <tr>
                    <td class="px-3 text-muted">{{ $records->firstItem() + $i }}</td>
                    <td>
                        <div class="fw-medium">{{ $row->tanggal->format('d/m/Y') }}</div>
                        <div class="text-muted" style="font-size:.7rem">{{ $row->tanggal->translatedFormat('D') }}</div>
                    </td>
                    <td>
                        @if($row->karyawan)
                        <div class="fw-medium">{{ $row->karyawan->nama_lengkap }}</div>
                        <div class="text-muted" style="font-size:.72rem">{{ $row->karyawan->jabatan }}</div>
                        @else
                        <span class="text-muted fst-italic">—</span>
                        @endif
                        @if($row->dicatat_oleh)
                        <span class="badge bg-secondary-subtle text-secondary border" style="font-size:.65rem">Manual</span>
                        @endif
                    </td>
                    @if($canAllBranches)
                    <td class="d-none d-md-table-cell text-muted" style="font-size:.78rem">{{ $row->cabang?->nama_cabang ?? '-' }}</td>
                    @endif

                    {{-- Masuk --}}
                    <td class="jam-cell">
                        @if($masukRaw)
                        <span class="jam-val">{{ substr($masukRaw, 0, 5) }}</span>
                        @if($row->is_telat)
                        <span class="badge badge-telat ms-1" style="font-size:.65rem">Telat {{ $row->menit_telat }}m</span>
                        @endif
                        @if($fotoM)
                        <a href="javascript:void(0)"
                           onclick="showPhotoModal('{{ url('/img/'.$fotoM) }}','{{ $row->karyawan?->nama_lengkap }}','Masuk {{ substr($masukRaw,0,5) }}')"
                           class="ms-1 icon-sm text-muted" title="Foto masuk">
                            <i class="bi bi-camera"></i>
                        </a>
                        @endif
                        @if($latM && $lngM)
                        <a href="https://maps.google.com/?q={{ $latM }},{{ $lngM }}"
                           target="_blank" class="ms-1 icon-sm text-primary" title="GPS masuk">
                            <i class="bi bi-geo-alt"></i>
                        </a>
                        @endif
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>

                    {{-- Keluar --}}
                    <td class="jam-cell d-none d-sm-table-cell">
                        @if($keluarRaw)
                        <span class="jam-val">{{ substr($keluarRaw, 0, 5) }}</span>
                        @if($fotoK)
                        <a href="javascript:void(0)"
                           onclick="showPhotoModal('{{ url('/img/'.$fotoK) }}','{{ $row->karyawan?->nama_lengkap }}','Keluar {{ substr($keluarRaw,0,5) }}')"
                           class="ms-1 icon-sm text-muted" title="Foto keluar">
                            <i class="bi bi-camera"></i>
                        </a>
                        @endif
                        @if($latK && $lngK)
                        <a href="https://maps.google.com/?q={{ $latK }},{{ $lngK }}"
                           target="_blank" class="ms-1 icon-sm text-primary" title="GPS keluar">
                            <i class="bi bi-geo-alt"></i>
                        </a>
                        @endif
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>

                    {{-- Lembur Masuk --}}
                    <td class="jam-cell d-none d-lg-table-cell">
                        @if($lemMasukR)
                        <span class="jam-val text-warning">{{ substr($lemMasukR, 0, 5) }}</span>
                        @if($fotoLM)
                        <a href="javascript:void(0)"
                           onclick="showPhotoModal('{{ url('/img/'.$fotoLM) }}','{{ $row->karyawan?->nama_lengkap }}','Lembur Masuk {{ substr($lemMasukR,0,5) }}')"
                           class="ms-1 icon-sm text-muted" title="Foto lembur masuk">
                            <i class="bi bi-camera"></i>
                        </a>
                        @endif
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>

                    {{-- Lembur Keluar --}}
                    <td class="jam-cell d-none d-lg-table-cell">
                        @if($lemKelR)
                        <span class="jam-val" style="color:#f97316">{{ substr($lemKelR, 0, 5) }}</span>
                        @if($fotoLK)
                        <a href="javascript:void(0)"
                           onclick="showPhotoModal('{{ url('/img/'.$fotoLK) }}','{{ $row->karyawan?->nama_lengkap }}','Lembur Keluar {{ substr($lemKelR,0,5) }}')"
                           class="ms-1 icon-sm text-muted" title="Foto lembur keluar">
                            <i class="bi bi-camera"></i>
                        </a>
                        @endif
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>

                    {{-- Total Kerja --}}
                    <td class="jam-cell d-none d-md-table-cell">
                        @if($totalKerja)
                        <span class="jam-dur">{{ $totalKerja }}</span>
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>

                    {{-- Total Lembur --}}
                    <td class="jam-cell d-none d-xl-table-cell">
                        @if($totalLembur)
                        <span class="jam-dur text-warning">{{ $totalLembur }}</span>
                        @elseif($row->jam_lembur)
                        <span class="jam-dur text-warning">{{ number_format($row->jam_lembur, 1) }}j</span>
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>

                    {{-- Status --}}
                    <td>
                        <span class="badge {{ $statusBadge }}" style="font-size:.72rem">{{ $statusLabel }}</span>
                    </td>

                    {{-- Aksi --}}
                    <td class="text-center no-print">
                        <div class="d-flex gap-1 justify-content-center">
                            <button type="button"
                                    class="btn btn-sm btn-outline-info py-0 px-1"
                                    title="Detail absensi"
                                    data-bs-toggle="modal"
                                    data-bs-target="#detailModal"
                                    data-detail="{{ $detailJson }}">
                                <i class="bi bi-eye" style="font-size:.8rem"></i>
                            </button>
                            @can('hapus_log_absensi')
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger py-0 px-1"
                                    title="Hapus absensi"
                                    onclick="hapusAbsensiLaporan('{{ route('absensi.destroy', $row->id) }}','{{ addslashes($row->karyawan?->nama_lengkap ?? '') }}','{{ $row->tanggal->format('d/m/Y') }}')">
                                <i class="bi bi-trash" style="font-size:.8rem"></i>
                            </button>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $canAllBranches ? 12 : 11 }}" class="text-center py-4 text-muted">
                        <i class="bi bi-inbox me-2"></i>Tidak ada data absensi untuk filter yang dipilih.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($records->hasPages())
    <div class="card-footer py-2 px-3">
        {{ $records->links() }}
    </div>
    @endif
</div>

@can('hapus_log_absensi')
{{-- Form hapus absensi dari halaman laporan --}}
<form id="form-hapus-absensi-laporan" method="POST" action="" style="display:none">
    @csrf
    @method('DELETE')
</form>
@endcan

{{-- ===== DETAIL MODAL ===== --}}
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-fullscreen-sm-down modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2 px-3">
                <div>
                    <h6 class="modal-title fw-bold mb-0" id="detailModalTitle">Detail Absensi</h6>
                    <div class="text-muted small" id="detailModalSub"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="accordion accordion-flush" id="detailAccordion"></div>
            </div>
            <div class="modal-footer justify-content-start py-2 px-3">
                <a href="#" id="detailLogLink" class="btn btn-sm btn-outline-secondary" target="_blank">
                    <i class="bi bi-list-ul me-1"></i>Lihat Log Hari Ini
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Photo Lightbox Modal --}}
<div class="modal fade" id="photoModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content bg-dark border-0">
            <div class="modal-header border-0 bg-dark py-2 px-3">
                <div>
                    <div class="text-white fw-semibold small" id="photoModalNama"></div>
                    <div class="text-white-50" style="font-size:.75rem" id="photoModalWaktu"></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2 text-center">
                <img id="photoModalImg" src="" alt="Foto absen"
                     class="img-fluid rounded"
                     style="max-height:80vh;object-fit:contain"
                     onerror="this.alt='Foto tidak dapat dimuat'">
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function showPhotoModal(src, nama, waktu) {
    document.getElementById('photoModalImg').src = src;
    document.getElementById('photoModalNama').textContent = nama || '';
    document.getElementById('photoModalWaktu').textContent = waktu || '';
    const photoModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('photoModal'));
    photoModal.show();
}

function setQuickFilter(dari, sampai) {
    document.getElementById('inputDari').value   = dari;
    document.getElementById('inputSampai').value = sampai;
    document.getElementById('filterForm').submit();
}

// Populate detail modal when shown
document.getElementById('detailModal').addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    if (!btn || !btn.hasAttribute('data-detail')) return;

    let data;
    try { data = JSON.parse(btn.getAttribute('data-detail')); }
    catch (_) { return; }

    document.getElementById('detailModalTitle').textContent = 'Detail Absensi — ' + data.nama;
    document.getElementById('detailModalSub').textContent   = data.tanggal;

    const SECTIONS = [
        { key: 'masuk',         title: 'Absen Masuk',    icon: 'bi-box-arrow-in-right', colorClass: 'text-success' },
        { key: 'keluar',        title: 'Absen Keluar',   icon: 'bi-box-arrow-right',    colorClass: 'text-danger'  },
        { key: 'lembur_masuk',  title: 'Lembur Masuk',   icon: 'bi-clock',              colorClass: 'text-warning' },
        { key: 'lembur_keluar', title: 'Lembur Keluar',  icon: 'bi-clock-history',      colorClass: 'text-secondary' },
    ];

    // First section with data → expanded by default
    const firstWithData = SECTIONS.findIndex(s => data[s.key]);

    let html = '';
    SECTIONS.forEach((s, idx) => {
        const d    = data[s.key];
        const show = (idx === firstWithData);

        html += `<div class="accordion-item border-start-0 border-end-0">
            <h2 class="accordion-header">
                <button class="accordion-button py-2 ${!show ? 'collapsed' : ''}" type="button"
                        data-bs-toggle="collapse" data-bs-target="#acc-${s.key}" style="font-size:.85rem">
                    <i class="bi ${s.icon} ${d ? s.colorClass : 'text-muted'} me-2"></i>
                    <span class="${d ? 'fw-semibold' : 'text-muted'}">${s.title}</span>
                    ${d ? `<span class="badge text-bg-secondary ms-2 fw-normal" style="font-size:.65rem">${d.jam}</span>` : '<span class="badge bg-secondary-subtle text-secondary ms-2" style="font-size:.65rem">Tidak ada data</span>'}
                    ${d?.manual ? '<span class="badge text-bg-info ms-1" style="font-size:.6rem">Manual</span>' : ''}
                </button>
            </h2>
            <div id="acc-${s.key}" class="accordion-collapse collapse ${show ? 'show' : ''}">
                <div class="accordion-body py-2 px-3" style="font-size:.82rem">`;

        if (!d) {
            html += '<p class="text-muted mb-0">Tidak ada data absensi untuk bagian ini.</p>';
        } else if (d.manual) {
            // ── ABSEN MANUAL ──────────────────────────────────────────────
            html += `<div class="alert alert-info d-flex align-items-start gap-2 py-2 mb-0 rounded-0">
                <i class="bi bi-info-circle-fill mt-1 flex-shrink-0"></i>
                <div>
                    <div class="fw-semibold" style="font-size:.82rem">Absen Manual</div>
                    <div class="text-muted" style="font-size:.78rem">
                        Diinput manual oleh admin — tidak ada foto &amp; data GPS.
                    </div>
                </div>
            </div>`;
        } else {
            // ── FACE RECOGNITION ──────────────────────────────────────────
            const hasFaceData = d.foto || (d.lat && d.lng)
                || d.confidence !== null || d.device || d.jarak !== null;

            if (!hasFaceData) {
                html += `<div class="d-flex align-items-center gap-2 text-muted py-1" style="font-size:.8rem">
                    <i class="bi bi-question-circle"></i>
                    <span>Data face recognition tidak tersedia (kemungkinan catatan lama sebelum sistem aktif).</span>
                </div>`;
            } else {
                html += '<div class="row g-3">';

                // Foto column
                if (d.foto) {
                    html += `<div class="col-12 col-md-5">
                        <img src="${escHtml(d.foto)}"
                             class="img-fluid rounded shadow-sm"
                             style="max-height:220px;cursor:pointer;object-fit:cover;width:100%"
                             onclick="showPhotoModal('${escHtml(d.foto)}','${escHtml(data.nama)}','${escHtml(s.title + ' ' + d.jam)}')"
                             onerror="this.onerror=null;this.parentNode.style.display='none'">
                    </div>
                    <div class="col-12 col-md-7">`;
                } else {
                    html += '<div class="col-12">';
                }

                // Info grid
                html += '<div class="row g-2">';

                if (d.confidence !== null && d.confidence !== undefined) {
                    const conf = parseFloat(d.confidence);
                    const barColor = conf >= 70 ? 'bg-success' : (conf >= 50 ? 'bg-warning' : 'bg-danger');
                    html += `<div class="col-6">
                        <div class="text-muted">Confidence</div>
                        <div class="fw-semibold">${escHtml(String(d.confidence))}%</div>
                        <div class="progress mt-1" style="height:4px">
                            <div class="progress-bar ${barColor}" style="width:${Math.min(100, conf)}%"></div>
                        </div>
                    </div>`;
                }
                if (d.liveness !== null && d.liveness !== undefined) {
                    html += `<div class="col-6">
                        <div class="text-muted">Liveness</div>
                        <div>${d.liveness ? '✅ Passed' : '❌ Failed'}</div>
                    </div>`;
                }
                if (d.device) {
                    html += `<div class="col-6">
                        <div class="text-muted">Device</div>
                        <div class="fw-semibold">${escHtml(d.device)}</div>
                    </div>`;
                }
                if (d.jarak !== null && d.jarak !== undefined) {
                    html += `<div class="col-6">
                        <div class="text-muted">Jarak Cabang</div>
                        <div class="fw-semibold">${escHtml(String(d.jarak))} m</div>
                    </div>`;
                }
                if (d.lat && d.lng) {
                    const mUrl = `https://maps.google.com/?q=${d.lat},${d.lng}`;
                    html += `<div class="col-12">
                        <div class="text-muted">📍 Lokasi GPS</div>
                        <div>
                            <a href="${escHtml(mUrl)}" target="_blank" class="text-primary text-decoration-none">
                                <i class="bi bi-geo-alt-fill me-1"></i>${d.lat}, ${d.lng}
                            </a>
                        </div>
                    </div>`;
                }

                html += '</div>'; // row g-2
                html += '</div>'; // col (col-md-7 or col-12)
                html += '</div>'; // row g-3
            }
        }

        html += `</div></div></div>`; // accordion-body, collapse, item
    });

    document.getElementById('detailAccordion').innerHTML = html;

    // Lihat Log link
    const logBase = '{{ route('face-attendance.log') }}';
    document.getElementById('detailLogLink').href =
        logBase + '?karyawan_id=' + data.karyawan_id + '&dari=' + data.tanggal_raw + '&sampai=' + data.tanggal_raw;
});

function escHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function hapusAbsensiLaporan(url, nama, tgl) {
    if (!confirm('Hapus absensi ' + nama + ' tanggal ' + tgl + '?\nData jam masuk/keluar/lembur hari itu akan terhapus seluruhnya.')) return;
    const f = document.getElementById('form-hapus-absensi-laporan');
    if (!f) return;
    f.action = url;
    f.submit();
}
</script>
@endpush
