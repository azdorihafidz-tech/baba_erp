@extends('layouts.app')

@section('title', 'Edit Panduan: ' . $panduan->judul)

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.panduan.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-bottom py-3">
        <h5 class="mb-0 fw-bold"><i class="bi bi-pencil text-warning me-2"></i>Edit Panduan</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.panduan.update', $panduan) }}">
            @csrf
            @method('PUT')
            @include('admin.panduan.form')
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-warning px-4"
                        style="min-height:44px">
                    <i class="bi bi-check-lg me-1"></i>Perbarui Panduan
                </button>
                <a href="{{ route('admin.panduan.index') }}" class="btn btn-outline-secondary px-4"
                   style="min-height:44px">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
