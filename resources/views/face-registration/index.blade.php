@extends('layouts.app')

@section('title', 'Registrasi Wajah Karyawan')

@push('styles')
<style>
.face-status-badge { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.face-status-badge.registered { background: #22c55e; }
.face-status-badge.unregistered { background: #e2e8f0; }
.karyawan-card {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 1rem;
    transition: box-shadow .2s;
    background: white;
}
.karyawan-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.10); }
.karyawan-avatar {
    width: 50px; height: 50px; border-radius: 50%;
    background: #f1f5f9; object-fit: cover; flex-shrink: 0;
}
</style>
@endpush

@section('content')
<div class="container-fluid px-3 px-md-4">

    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-2">
        <div>
            <h4 class="mb-1 fw-semibold">Registrasi Wajah Karyawan</h4>
            <p class="text-muted mb-0 small">Daftarkan wajah karyawan untuk absensi face recognition</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <a href="{{ route('face-attendance.today') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-clock-history me-1"></i> Absensi Hari Ini
            </a>
            <x-panduan-button slug="registrasi-wajah" />
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Statistik --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="h3 fw-bold text-dark mb-0">{{ $karyawans->count() }}</div>
                <div class="small text-muted">Total Karyawan</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="h3 fw-bold text-success mb-0">{{ $terdaftar }}</div>
                <div class="small text-muted">Sudah Daftar</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="h3 fw-bold text-warning mb-0">{{ $belumDaftar }}</div>
                <div class="small text-muted">Belum Daftar</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="h3 fw-bold text-primary mb-0">
                    {{ $karyawans->count() > 0 ? round($terdaftar / $karyawans->count() * 100) : 0 }}%
                </div>
                <div class="small text-muted">Persentase</div>
            </div>
        </div>
    </div>

    {{-- Filter --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-sm btn-primary filter-btn active" data-filter="all">Semua</button>
                <button class="btn btn-sm btn-outline-success filter-btn" data-filter="registered">Sudah Daftar</button>
                <button class="btn btn-sm btn-outline-warning filter-btn" data-filter="unregistered">Belum Daftar</button>
            </div>
        </div>
    </div>

    {{-- Grid Karyawan --}}
    <div class="row g-3" id="karyawanGrid">
        @forelse($karyawans as $karyawan)
        @php $isRegistered = $karyawan->isFaceRegistered(); @endphp
        <div class="col-12 col-sm-6 col-lg-4 col-xl-3 karyawan-item"
             data-status="{{ $isRegistered ? 'registered' : 'unregistered' }}">
            <div class="karyawan-card h-100">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img
                        src="{{ $karyawan->foto ? asset('storage/'.$karyawan->foto) : 'https://ui-avatars.com/api/?name='.urlencode($karyawan->nama_lengkap).'&background=f1f5f9&color=64748b&size=80' }}"
                        alt="{{ $karyawan->nama_lengkap }}"
                        class="karyawan-avatar">
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate">{{ $karyawan->nama_lengkap }}</div>
                        <div class="small text-muted">{{ $karyawan->jabatan }}</div>
                        <div class="small text-muted">{{ $karyawan->nik }}</div>
                    </div>
                </div>

                {{-- Status badge --}}
                <div class="mb-3">
                    @if($isRegistered)
                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                        <i class="bi bi-check-circle me-1"></i>Wajah Terdaftar
                    </span>
                    <div class="text-muted mt-1" style="font-size:0.75rem">
                        <i class="bi bi-clock me-1"></i>
                        {{ $karyawan->face_registered_at->format('d M Y H:i') }}
                    </div>
                    @else
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                        <i class="bi bi-exclamation-circle me-1"></i>Belum Daftar
                    </span>
                    @endif
                </div>

                {{-- Actions --}}
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('face-registration.register', $karyawan) }}"
                       class="btn btn-sm {{ $isRegistered ? 'btn-outline-primary' : 'btn-primary' }} flex-grow-1">
                        <i class="bi bi-camera me-1"></i>
                        {{ $isRegistered ? 'Update Wajah' : 'Daftarkan Wajah' }}
                    </a>
                    @if($isRegistered)
                    <form method="POST" action="{{ route('face-registration.reset', $karyawan) }}"
                          onsubmit="return confirm('Reset data wajah {{ $karyawan->nama_lengkap }}?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Reset Wajah">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="text-center py-5 text-muted">
                <i class="bi bi-people display-4 mb-3 d-block"></i>
                <p>Belum ada karyawan aktif di cabang ini.</p>
            </div>
        </div>
        @endforelse
    </div>

</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active', 'btn-primary', 'btn-success', 'btn-warning'));
        this.classList.add('active');
        // Remove outline from all, add solid to active
        const filter = this.dataset.filter;
        document.querySelectorAll('.karyawan-item').forEach(item => {
            if (filter === 'all' || item.dataset.status === filter) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    });
});
</script>
@endpush
