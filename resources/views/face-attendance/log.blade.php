@extends('layouts.app')

@section('title', 'Log Absensi Wajah')

@push('styles')
<style>
.foto-bukti {
    width: 50px; height: 50px; border-radius: 8px;
    object-fit: cover; cursor: pointer;
    border: 1px solid #e2e8f0; transition: transform .15s;
}
.foto-bukti:hover { transform: scale(1.05); }
.status-valid     { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.status-invalid   { background: #fef9c3; color: #713f12; border: 1px solid #fde68a; }
.status-unknown   { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.status-liveness  { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.status-duplikat     { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
.badge-lembur-keluar { background: #f97316; color: #fff; }
.quick-filter-btn {
    padding: 3px 10px; border-radius: 20px; font-size: .75rem; font-weight: 500;
    border: 1px solid #e2e8f0; background: white; color: #475569; cursor: pointer;
    white-space: nowrap; transition: all .15s;
}
.quick-filter-btn:hover, .quick-filter-btn.active {
    background: #3b82f6; border-color: #3b82f6; color: white;
}
</style>
@endpush

@section('content')
<div class="container-fluid px-3 px-md-4">

    {{-- Header --}}
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-2">
        <div>
            <h4 class="mb-1 fw-semibold">Log Absensi Wajah</h4>
            <p class="text-muted mb-0 small">Riwayat percobaan absensi face recognition</p>
        </div>
        <a href="{{ route('face-attendance.today') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke Rekap
        </a>
    </div>

    {{-- Filter --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('face-attendance.log') }}" id="logFilterForm">
                {{-- Quick date --}}
                <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
                    <span class="text-muted small">Quick:</span>
                    @php
                        $quick = [
                            'Hari Ini'   => [today()->toDateString(), today()->toDateString()],
                            'Kemarin'    => [today()->subDay()->toDateString(), today()->subDay()->toDateString()],
                            'Minggu Ini' => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
                            'Bulan Ini'  => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
                        ];
                    @endphp
                    @foreach($quick as $lbl => [$d, $s])
                    <button type="button" class="quick-filter-btn {{ $dari === $d && $sampai === $s ? 'active' : '' }}"
                            onclick="setLogFilter('{{ $d }}','{{ $s }}')">{{ $lbl }}</button>
                    @endforeach
                </div>

                <div class="row g-2 align-items-end">
                    <div class="col-6 col-sm-3 col-md-2">
                        <label class="form-label small mb-1">Dari</label>
                        <input type="date" name="dari" id="logDari" class="form-control form-control-sm" value="{{ $dari }}">
                    </div>
                    <div class="col-6 col-sm-3 col-md-2">
                        <label class="form-label small mb-1">Sampai</label>
                        <input type="date" name="sampai" id="logSampai" class="form-control form-control-sm" value="{{ $sampai }}">
                    </div>
                    @if($canAllBranches)
                    <div class="col-6 col-sm-3 col-md-2">
                        <label class="form-label small mb-1">Cabang</label>
                        <select name="cabang_id" class="form-select form-select-sm">
                            <option value="">Pilih Cabang</option>
                            @foreach($cabangs as $c)
                            <option value="{{ $c->id }}" {{ $cabangId == $c->id ? 'selected' : '' }}>{{ $c->nama_cabang }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="col-6 col-sm-3 col-md-2">
                        <label class="form-label small mb-1">Tipe</label>
                        <select name="tipe" class="form-select form-select-sm">
                            <option value="all"           {{ $tipeFil === 'all'           ? 'selected' : '' }}>Semua Tipe</option>
                            <option value="masuk"         {{ $tipeFil === 'masuk'         ? 'selected' : '' }}>Masuk</option>
                            <option value="keluar"        {{ $tipeFil === 'keluar'        ? 'selected' : '' }}>Keluar</option>
                            <option value="lembur_masuk"  {{ $tipeFil === 'lembur_masuk'  ? 'selected' : '' }}>Lembur Masuk</option>
                            <option value="lembur_keluar" {{ $tipeFil === 'lembur_keluar' ? 'selected' : '' }}>Lembur Keluar</option>
                        </select>
                    </div>
                    <div class="col-6 col-sm-3 col-md-2">
                        <label class="form-label small mb-1">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="all"            {{ $statusFil === 'all'            ? 'selected' : '' }}>Semua Status</option>
                            <option value="valid"          {{ $statusFil === 'valid'          ? 'selected' : '' }}>Valid</option>
                            <option value="duplikat"       {{ $statusFil === 'duplikat'       ? 'selected' : '' }}>Duplikat</option>
                            <option value="invalid_lokasi" {{ $statusFil === 'invalid_lokasi' ? 'selected' : '' }}>Lokasi Invalid</option>
                            <option value="tidak_dikenali" {{ $statusFil === 'tidak_dikenali' ? 'selected' : '' }}>Tidak Dikenali</option>
                            <option value="liveness_gagal" {{ $statusFil === 'liveness_gagal' ? 'selected' : '' }}>Liveness Gagal</option>
                        </select>
                    </div>
                    <div class="col-6 col-sm-3 col-md-2">
                        <label class="form-label small mb-1">Karyawan</label>
                        <select name="karyawan_id" class="form-select form-select-sm">
                            <option value="">Semua Karyawan</option>
                            @foreach($karyawanList as $k)
                            <option value="{{ $k->id }}" {{ $karyawanFil == $k->id ? 'selected' : '' }}>{{ $k->nama_lengkap }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-funnel me-1"></i>Filter
                        </button>
                        <a href="{{ route('face-attendance.log') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-x me-1"></i>Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabel Log --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold">
                Log {{ \Carbon\Carbon::parse($dari)->format('d/m/Y') }}
                @if($dari !== $sampai) — {{ \Carbon\Carbon::parse($sampai)->format('d/m/Y') }} @endif
            </h6>
            <span class="badge bg-secondary-subtle text-secondary border">{{ $logs->total() }} record</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light text-muted" style="font-size:.78rem">
                        <tr>
                            <th>Waktu</th>
                            <th>Karyawan</th>
                            <th>Tipe</th>
                            <th>Status</th>
                            <th class="d-none d-sm-table-cell">Confidence</th>
                            <th class="d-none d-lg-table-cell">GPS / Jarak</th>
                            <th class="d-none d-md-table-cell">Device</th>
                            <th>Foto</th>
                            @if(auth()->user()->canAccessAllBranches())
                            <th class="text-center" style="width:50px"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        <tr>
                            <td class="fw-semibold" style="font-variant-numeric:tabular-nums;white-space:nowrap">
                                <div>{{ $log->waktu->format('H:i:s') }}</div>
                                <div class="text-muted" style="font-size:.7rem">{{ $log->waktu->format('d/m/Y') }}</div>
                            </td>
                            <td>
                                @if($log->karyawan)
                                <div class="fw-semibold">{{ $log->karyawan->nama_lengkap }}</div>
                                <div class="text-muted" style="font-size:.72rem">{{ $log->karyawan->nik }}</div>
                                @else
                                <span class="text-muted fst-italic small">Tidak dikenali</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    [$tipeCls, $tipeLbl] = match($log->tipe) {
                                        'clock_in'      => ['text-bg-success',      '↑ Masuk'],
                                        'clock_out'     => ['text-bg-danger',       '↓ Keluar'],
                                        'lembur_masuk'  => ['text-bg-warning',      '⏰ Lembur Masuk'],
                                        'lembur_keluar' => ['badge-lembur-keluar',  '✅ Lembur Keluar'],
                                        default         => ['text-bg-secondary',    $log->tipe],
                                    };
                                @endphp
                                <span class="badge {{ $tipeCls }}" style="font-size:.72rem">{{ $tipeLbl }}</span>
                            </td>
                            <td>
                                @php
                                    [$stCls, $stLbl] = match($log->status) {
                                        'valid'          => ['status-valid',    'Valid'],
                                        'duplikat'       => ['status-duplikat', 'Duplikat'],
                                        'invalid_lokasi' => ['status-invalid',  'Lok. Invalid'],
                                        'tidak_dikenali' => ['status-unknown',  'Tdk Dikenali'],
                                        'liveness_gagal' => ['status-liveness', 'Liveness Gagal'],
                                        default          => ['status-unknown',  $log->status],
                                    };
                                @endphp
                                <span class="badge {{ $stCls }}" style="font-size:.72rem">{{ $stLbl }}</span>
                            </td>
                            <td class="d-none d-sm-table-cell">
                                @if($log->confidence_score !== null)
                                <div class="progress" style="width:70px;height:6px;display:inline-block;vertical-align:middle">
                                    <div class="progress-bar {{ $log->confidence_score >= 70 ? 'bg-success' : ($log->confidence_score >= 50 ? 'bg-warning' : 'bg-danger') }}"
                                         style="width:{{ min(100, $log->confidence_score) }}%"></div>
                                </div>
                                <span class="ms-1 text-muted">{{ number_format($log->confidence_score, 1) }}%</span>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="d-none d-lg-table-cell" style="font-size:.75rem">
                                @if($log->latitude !== null && $log->longitude !== null)
                                @php $isOutside = $log->status === 'invalid_lokasi'; @endphp
                                <span class="{{ $isOutside ? 'text-warning fw-semibold' : 'text-muted' }}">
                                    <i class="bi bi-geo-alt{{ $isOutside ? '-fill' : '' }} me-1"></i>
                                    {{ $log->jarak_dari_cabang !== null ? $log->jarak_dari_cabang.'m' : '?' }}
                                    @if($isOutside)<i class="bi bi-exclamation-triangle-fill ms-1 text-warning"></i>@endif
                                </span>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="d-none d-md-table-cell text-muted" style="font-size:.72rem">
                                {{ $log->device?->device_name ?? '—' }}
                            </td>
                            <td>
                                @if($log->foto_absen)
                                <img src="{{ url('/img/'.$log->foto_absen) }}"
                                     class="foto-bukti"
                                     alt="Foto absen"
                                     data-bs-toggle="modal"
                                     data-bs-target="#fotoModal"
                                     data-src="{{ url('/img/'.$log->foto_absen) }}"
                                     data-nama="{{ $log->karyawan?->nama_lengkap ?? 'Tidak dikenali' }}"
                                     data-waktu="{{ $log->waktu->format('d/m/Y H:i:s') }}"
                                     onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=!&background=fee2e2&color=991b1b&size=50';this.title='Foto tidak ditemukan'">
                                @else
                                <span class="text-muted small">—</span>
                                @endif
                            </td>
                            @if(auth()->user()->canAccessAllBranches())
                            <td class="text-center">
                                <form method="POST" action="{{ route('face-attendance.log.destroy', $log) }}"
                                      onsubmit="return confirm('Hapus log ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" title="Hapus log" style="min-height:28px">
                                        <i class="bi bi-trash" style="font-size:.7rem"></i>
                                    </button>
                                </form>
                            </td>
                            @endif
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ auth()->user()->canAccessAllBranches() ? 9 : 8 }}" class="text-center py-5 text-muted">
                                <i class="bi bi-clock-history display-5 d-block mb-2 opacity-25"></i>
                                Tidak ada log untuk filter yang dipilih
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($logs->hasPages())
        <div class="card-footer bg-white">
            {{ $logs->appends(request()->query())->links() }}
        </div>
        @endif
    </div>

</div>

{{-- Modal Foto Bukti --}}
<div class="modal fade" id="fotoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header border-0">
                <div>
                    <h6 class="modal-title fw-semibold" id="fotoModalNama"></h6>
                    <div class="small text-muted" id="fotoModalWaktu"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-2">
                <img id="fotoModalImg" src="" alt="Foto absen" class="img-fluid rounded" style="max-height:60vh">
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.foto-bukti').forEach(img => {
    img.addEventListener('click', function () {
        document.getElementById('fotoModalImg').src            = this.dataset.src;
        document.getElementById('fotoModalNama').textContent  = this.dataset.nama;
        document.getElementById('fotoModalWaktu').textContent = this.dataset.waktu;
    });
});
function setLogFilter(dari, sampai) {
    document.getElementById('logDari').value   = dari;
    document.getElementById('logSampai').value = sampai;
    document.getElementById('logFilterForm').submit();
}
</script>
@endpush
