@extends('layouts.app')

@section('title', 'Tambah Produk Jual')

@section('content')

<div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Tambah Produk Jual</h5>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('master.produk-jual.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
        <x-panduan-button slug="produk-jual" />
    </div>
</div>

@include('master.produk-jual._form', [
    'formAction' => route('master.produk-jual.store'),
    'formMethod' => 'POST',
    'item' => null,
])

@endsection
