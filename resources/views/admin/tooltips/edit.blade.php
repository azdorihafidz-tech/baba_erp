@extends('layouts.app')

@section('title', 'Edit Tooltip')

@section('content')
<div class="container-fluid">

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.tooltips.index') }}">Tooltip Helper</a></li>
            <li class="breadcrumb-item active">Edit: {{ $tooltip->key }}</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0 pb-0 pt-3 px-4">
                    <h5 class="card-title fw-bold mb-0">
                        <i class="bi bi-pencil-square me-2 text-warning"></i>Edit Tooltip
                    </h5>
                    <p class="text-muted mb-0" style="font-size:.82rem">
                        Key: <code>{{ $tooltip->key }}</code>
                    </p>
                </div>
                <div class="card-body px-4 py-3">
                    <form method="POST" action="{{ route('admin.tooltips.update', $tooltip) }}">
                        @csrf
                        @method('PUT')
                        @include('admin.tooltips.form')
                        <div class="d-flex gap-2 flex-column flex-sm-row">
                            <button type="submit" class="btn btn-warning text-white flex-fill" style="min-height:44px">
                                <i class="bi bi-save me-2"></i>Perbarui
                            </button>
                            <a href="{{ route('admin.tooltips.index') }}"
                               class="btn btn-outline-secondary flex-fill" style="min-height:44px">
                                <i class="bi bi-arrow-left me-2"></i>Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
