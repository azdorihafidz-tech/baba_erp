@extends('layouts.app')

@section('title', 'Kategori Transaksi')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h5 class="fw-bold mb-0"><i class="bi bi-tags me-2 text-primary"></i>Kategori Transaksi</h5>
        <p class="text-muted mb-0 small">Kelola kategori pemasukan & pengeluaran</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        @can('kategori.create')
        <a href="{{ route('kategori-transaksi.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Tambah Kategori
        </a>
        @endcan
        <x-panduan-button slug="kategori-transaksi" />
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2" role="alert">
    {!! session('success') !!}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
    {!! session('error') !!}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Pemasukan --}}
@foreach(['pemasukan' => ['label' => 'Pemasukan', 'badge' => 'bg-success', 'icon' => 'arrow-down-circle'],
           'pengeluaran' => ['label' => 'Pengeluaran', 'badge' => 'bg-danger', 'icon' => 'arrow-up-circle'],
           'keduanya' => ['label' => 'Pemasukan & Pengeluaran', 'badge' => 'bg-secondary', 'icon' => 'arrow-left-right']
          ] as $tipe => $meta)
@php
    $tipeKategoris = $kategoris->filter(fn($k) => $k->tipe === $tipe);
    $children = $kategoris->flatMap(fn($k) => $k->children)->filter(fn($k) => $k->tipe === $tipe);
    $all = $tipeKategoris->merge($children)->unique('id');
@endphp
@if($tipeKategoris->count() > 0)
<div class="card mb-3">
    <div class="card-header d-flex align-items-center gap-2 py-2">
        <span class="badge {{ $meta['badge'] }}">
            <i class="bi bi-{{ $meta['icon'] }} me-1"></i>{{ $meta['label'] }}
        </span>
        <span class="text-muted small ms-1">{{ $tipeKategoris->count() }} kategori</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th width="100">Kode</th>
                    <th>Nama</th>
                    <th class="d-none d-md-table-cell">Sub Kategori</th>
                    <th class="d-none d-lg-table-cell" width="160">Kode Akun COA</th>
                    <th class="d-none d-md-table-cell text-center" width="80">Urutan</th>
                    <th class="text-center" width="80">Status</th>
                    <th class="text-center" width="120">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tipeKategoris as $kat)
                <tr>
                    <td><code style="font-size:0.8rem">{{ $kat->kode }}</code></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-semibold">{{ $kat->nama }}</span>
                            @if($kat->is_system)
                                <span class="badge bg-warning-subtle text-warning border border-warning" style="font-size:0.65rem">SISTEM</span>
                            @endif
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell">
                        @if($kat->children->count() > 0)
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($kat->children as $child)
                            <span class="badge bg-light text-dark border" style="font-size:0.72rem">
                                {{ $child->nama }}
                            </span>
                            @endforeach
                        </div>
                        @else
                        <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="d-none d-lg-table-cell">
                        @if($kat->kode_akun_coa)
                        <code style="font-size:0.78rem">{{ $kat->kode_akun_coa }}</code>
                        <span class="text-muted small d-block">{{ $kat->chartOfAccount?->nama }}</span>
                        @else
                        <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="d-none d-md-table-cell text-center text-muted small">{{ $kat->urutan }}</td>
                    <td class="text-center">
                        <span class="badge {{ $kat->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}" style="font-size:0.72rem">
                            {{ $kat->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </td>
                    <td class="text-center">
                        @can('kategori.edit')
                        <a href="{{ route('kategori-transaksi.edit', $kat) }}"
                           class="btn btn-xs btn-outline-warning py-0 px-2 me-1" title="Edit" style="font-size:0.72rem">
                            <i class="bi bi-pencil"></i>
                        </a>
                        @endcan
                        @can('kategori.delete')
                        @if(!$kat->is_system)
                        <button type="button"
                            onclick="confirmHapus({{ $kat->id }}, '{{ addslashes($kat->nama) }}')"
                            class="btn btn-xs btn-outline-danger py-0 px-2" title="Hapus" style="font-size:0.72rem">
                            <i class="bi bi-trash"></i>
                        </button>
                        @else
                        <span class="text-muted" title="Kategori sistem tidak bisa dihapus" style="font-size:0.72rem"><i class="bi bi-lock"></i></span>
                        @endif
                        @endcan
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endforeach

{{-- Modal Hapus --}}
<div class="modal fade" id="hapusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-trash me-2"></i>Hapus Kategori</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Hapus kategori <strong id="hapusNama"></strong>? Kategori yang sudah dipakai oleh transaksi tidak bisa dihapus.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <form id="hapusForm" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Ya, Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function confirmHapus(id, nama) {
    document.getElementById('hapusNama').textContent = nama;
    document.getElementById('hapusForm').action = '/kategori-transaksi/' + id;
    new bootstrap.Modal(document.getElementById('hapusModal')).show();
}
</script>
@endpush
