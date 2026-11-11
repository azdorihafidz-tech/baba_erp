@extends('layouts.app')

@section('title', 'Form Penilaian')

@push('styles')
<style>
.rating-slider { width: 100%; }
.skor-display { font-size: 1.5rem; font-weight: 800; color: var(--bs-primary); min-width: 2rem; text-align: center; }
.skor-label { font-size: 0.75rem; color: #64748b; }
.aspect-card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem; transition: border-color 0.2s; }
.aspect-card:hover { border-color: #3b82f6; }
.slider-labels { display: flex; justify-content: space-between; font-size: 0.7rem; color: #94a3b8; margin-top: 4px; }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2 text-primary"></i>Form Penilaian</h4>
        <small class="text-muted">Isi penilaian jujur dan objektif untuk karyawan berikut</small>
    </div>
    <a href="{{ route('evaluasi.my-reviews') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Penilaian Saya
    </a>
</div>

{{-- Info Penilaian --}}
<div class="card mb-3 border-primary border-opacity-25">
    <div class="card-body py-2">
        <div class="row g-2">
            <div class="col-6 col-md-3">
                <small class="text-muted d-block">Karyawan Dinilai</small>
                <div class="fw-semibold">{{ $evaluation->karyawan?->nama_lengkap }}</div>
                <small class="text-muted">{{ $evaluation->karyawan?->jabatan }}</small>
            </div>
            <div class="col-6 col-md-3">
                <small class="text-muted d-block">Periode</small>
                <div class="fw-semibold">{{ $evaluation->period?->nama_periode }}</div>
            </div>
            <div class="col-6 col-md-3">
                <small class="text-muted d-block">Penilai</small>
                <div class="fw-semibold">{{ auth()->user()->name }}</div>
            </div>
            <div class="col-6 col-md-3">
                <small class="text-muted d-block">Tipe Penilai</small>
                @php $tipe = is_object($reviewer->tipe_reviewer) ? $reviewer->tipe_reviewer->value : $reviewer->tipe_reviewer; @endphp
                <span class="badge {{ $tipe === 'atasan' ? 'bg-danger' : ($tipe === 'self_assessment' ? 'bg-warning text-dark' : 'bg-info') }}">
                    {{ match($tipe) {
                        'atasan' => 'Atasan Langsung',
                        'rekan_kerja' => 'Rekan Kerja',
                        'self_assessment' => 'Self Assessment',
                        default => $tipe
                    } }}
                </span>
                <small class="d-block text-muted">Bobot: {{ $reviewer->bobot_reviewer_persen }}%</small>
            </div>
        </div>
    </div>
</div>

<form action="{{ route('evaluasi.submit-penilaian', $evaluation) }}" method="POST">
    @csrf

    <div class="row">
        <div class="col-12 col-lg-8">
            @foreach($aspects as $i => $aspect)
            <div class="aspect-card">
                <input type="hidden" name="scores[{{ $i }}][aspect_id]" value="{{ $aspect->id }}">

                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="fw-semibold">{{ $aspect->nama_aspek }}</div>
                        <small class="text-muted">{{ $aspect->deskripsi }}</small>
                        <small class="text-muted d-block">Bobot: {{ $aspect->bobot_persen }}%</small>
                    </div>
                    <div class="text-center">
                        <div class="skor-display" id="skorDisplay_{{ $i }}">3</div>
                        <div class="skor-label" id="skorLabel_{{ $i }}">Cukup</div>
                    </div>
                </div>

                <input type="range" name="scores[{{ $i }}][skor]" class="form-range rating-slider"
                       min="1" max="5" step="1" value="3" id="slider_{{ $i }}"
                       oninput="updateSkor({{ $i }}, this.value)">
                <div class="slider-labels">
                    <span>1 - Sangat Kurang</span>
                    <span>2 - Kurang</span>
                    <span>3 - Cukup</span>
                    <span>4 - Baik</span>
                    <span>5 - Sangat Baik</span>
                </div>

                <div class="mt-2">
                    <textarea name="scores[{{ $i }}][komentar]" class="form-control form-control-sm"
                              rows="2" placeholder="Komentar / catatan untuk aspek ini (opsional)..."></textarea>
                </div>
            </div>
            @endforeach

            <div class="d-grid d-md-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary btn-lg"
                        onclick="return confirm('Kirim penilaian? Setelah dikirim tidak bisa diubah.')">
                    <i class="bi bi-send me-2"></i>Kirim Penilaian
                </button>
                <a href="{{ route('evaluasi.my-reviews') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </div>

        {{-- Panel Info --}}
        <div class="col-12 col-lg-4">
            <div class="card bg-light">
                <div class="card-header">Panduan Penilaian</div>
                <div class="card-body small">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td><strong>1</strong></td><td class="text-danger">Sangat Kurang</td></tr>
                        <tr><td><strong>2</strong></td><td class="text-warning">Kurang</td></tr>
                        <tr><td><strong>3</strong></td><td class="text-secondary">Cukup</td></tr>
                        <tr><td><strong>4</strong></td><td class="text-primary">Baik</td></tr>
                        <tr><td><strong>5</strong></td><td class="text-success">Sangat Baik</td></tr>
                    </table>
                    <hr>
                    <p class="text-muted mb-0">Berikan penilaian yang jujur dan objektif. Penilaian ini bersifat rahasia dan hanya digunakan untuk pengembangan karir karyawan.</p>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
const skorLabels = {1:'Sangat Kurang', 2:'Kurang', 3:'Cukup', 4:'Baik', 5:'Sangat Baik'};
const skorColors = {1:'#dc3545', 2:'#fd7e14', 3:'#6c757d', 4:'#0d6efd', 5:'#198754'};

function updateSkor(index, value) {
    const v = parseInt(value);
    const display = document.getElementById('skorDisplay_' + index);
    const label = document.getElementById('skorLabel_' + index);
    display.textContent = v;
    display.style.color = skorColors[v];
    label.textContent = skorLabels[v];
}

// Init semua slider
@foreach($aspects as $i => $aspect)
updateSkor({{ $i }}, 3);
@endforeach
</script>
@endpush
@endsection
