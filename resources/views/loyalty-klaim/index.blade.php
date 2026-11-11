@extends('layouts.app')

@section('title', 'Klaim Event Loyalty')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-megaphone me-2 text-primary"></i>Klaim Event Loyalty</h4>
        <small class="text-muted">Klaim manual (post + link/foto bukti) untuk program loyalty event-based, semua cabang</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('loyalty-program.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-award me-1"></i>Program Loyalty
        </a>
        @can('loyalty.klaim.buat')
        <a href="{{ route('loyalty-klaim.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Buat Klaim
        </a>
        @endcan
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-6 col-md-4">
                <label class="form-label small">Status</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'issued' => 'Issued'] as $val => $label)
                    <option value="{{ $val }}" {{ request('status') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-5">
                <label class="form-label small">Program</label>
                <select name="loyalty_program_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Semua Program</option>
                    @foreach($programs as $p)
                    <option value="{{ $p->id }}" {{ (string) request('loyalty_program_id') === (string) $p->id ? 'selected' : '' }}>{{ $p->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-3">
                <a href="{{ route('loyalty-klaim.index') }}" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Filter
                </a>
            </div>
        </form>
    </div>
</div>

@include('loyalty-klaim._table', ['klaims' => $klaims, 'showProgramColumn' => true])
@endsection
