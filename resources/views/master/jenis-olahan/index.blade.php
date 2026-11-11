@extends('layouts.app')

@section('title', 'Master Jenis Menu')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-list-check me-2 text-primary"></i>Master Jenis Menu</h4>
        <small class="text-muted">Kelola jenis olahan yang tersedia di dropdown POS &amp; Jasa Giling</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('master.jenis-olahan.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Tambah Jenis Menu
        </a>
        <x-panduan-button slug="jenis-olahan" />
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="alert alert-info py-2 small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    <strong>Catatan:</strong> Menonaktifkan jenis olahan hanya menyembunyikannya dari dropdown POS.
    Transaksi lama yang menggunakan jenis tersebut <strong>tetap aman dan tidak terpengaruh</strong>.
    Slug tidak bisa diubah setelah dibuat.
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <x-search-box placeholder="Nama jenis olahan..." col="col-12 col-sm-6 col-md-4" />
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
            </div>
            @if(request('search'))
            <div class="col-auto">
                <a href="{{ route('master.jenis-olahan.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th>
                    <th>Nama Jenis Menu</th>
                    <th class="d-none d-md-table-cell">Slug (value di DB)</th>
                    <th class="text-center" style="min-width:90px">Status</th>
                    <th class="text-center" style="min-width:120px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($jenisOlahans as $i => $jenis)
                <tr class="{{ !$jenis->is_active ? 'text-muted' : '' }}">
                    <td class="small text-muted">{{ $jenisOlahans->firstItem() + $i }}</td>
                    <td class="fw-semibold">{{ $jenis->nama }}</td>
                    <td class="d-none d-md-table-cell">
                        <code class="text-secondary" style="font-size:0.82rem">{{ $jenis->slug }}</code>
                    </td>
                    <td class="text-center">
                        @if($jenis->is_active)
                            <span class="badge text-bg-success">Aktif</span>
                        @else
                            <span class="badge text-bg-secondary">Nonaktif</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <div class="d-flex justify-content-center gap-1">
                            <a href="{{ route('master.jenis-olahan.edit', $jenis) }}"
                               class="btn btn-xs btn-sm btn-outline-primary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('master.jenis-olahan.destroy', $jenis) }}" method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('{{ $jenis->is_active ? 'Nonaktifkan' : 'Aktifkan kembali' }} jenis olahan \"{{ $jenis->nama }}\"?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="btn btn-xs btn-sm {{ $jenis->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}"
                                        title="{{ $jenis->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                    <i class="bi bi-{{ $jenis->is_active ? 'pause-circle' : 'play-circle' }}"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-5">
                        <i class="bi bi-list-check display-6 d-block mb-2 opacity-25"></i>
                        Belum ada jenis olahan.
                        <a href="{{ route('master.jenis-olahan.create') }}">Tambah sekarang</a>.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($jenisOlahans->hasPages())
    <div class="card-footer d-flex justify-content-center py-3">
        {{ $jenisOlahans->links() }}
    </div>
    @endif
</div>
@endsection
