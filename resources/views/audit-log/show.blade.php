@extends('layouts.app')
@section('title', 'Detail Audit Log #' . $log->id)

@push('styles')
<style>
.diff-table th   { font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; padding:.5rem .75rem; }
.diff-table td   { font-size:.82rem; padding:.5rem .75rem; vertical-align:top; word-break:break-word; }
.diff-removed    { background:#fef2f2; }
.diff-added      { background:#f0fdf4; }
.diff-key        { font-weight:600; color:#475569; font-size:.78rem; }
.diff-val-old    { color:#b91c1c; }
.diff-val-new    { color:#16a34a; }
.info-label      { font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:#94a3b8; }
.info-value      { font-size:.88rem; font-weight:500; }
</style>
@endpush

@section('content')
<div class="mb-4 d-flex align-items-center gap-3">
    <a href="{{ route('audit-log.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
    <div>
        <h4 class="fw-bold mb-0">Detail Audit Log</h4>
        <small class="text-muted">ID #{{ $log->id }}</small>
    </div>
</div>

{{-- Info Header --}}
<div class="row g-3 mb-4">
    <div class="col-12 col-md-8">
        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <div class="info-label">Pengguna</div>
                        <div class="info-value">{{ $log->causer?->name ?? '<Sistem>' }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="info-label">Aksi</div>
                        <div class="info-value">
                            @php
                                $eventClass = match($log->event) {
                                    'created' => 'text-success', 'updated' => 'text-primary',
                                    'deleted' => 'text-danger',  'restored' => 'text-warning',
                                    default   => 'text-secondary',
                                };
                            @endphp
                            <span class="{{ $eventClass }} fw-bold">{{ ucfirst($log->event ?? 'log') }}</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="info-label">Model</div>
                        <div class="info-value">{{ $log->log_name }} #{{ $log->subject_id }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="info-label">Waktu</div>
                        <div class="info-value">{{ $log->created_at->format('d/m/Y H:i:s') }}</div>
                    </div>
                    <div class="col-12">
                        <div class="info-label">Deskripsi</div>
                        <div class="info-value">{{ $log->description }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="info-label mb-2">Statistik Perubahan</div>
                @php
                    $old  = (array) ($log->properties['old'] ?? []);
                    $new  = (array) ($log->properties['attributes'] ?? []);
                    $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
                    $changed = collect($keys)->filter(fn($k) => ($old[$k] ?? null) !== ($new[$k] ?? null))->count();
                @endphp
                <div class="d-flex justify-content-between small mb-1">
                    <span>Kolom berubah</span><strong>{{ $changed }}</strong>
                </div>
                <div class="d-flex justify-content-between small mb-1">
                    <span>Total kolom</span><strong>{{ count($keys) }}</strong>
                </div>
                <div class="d-flex justify-content-between small">
                    <span>Batch UUID</span>
                    <span class="text-muted" style="font-size:.68rem">{{ $log->batch_uuid ? substr($log->batch_uuid,0,8).'…' : '—' }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Diff View --}}
@if(!empty($keys))
<div class="card">
    <div class="card-header fw-semibold small d-flex justify-content-between">
        <span><i class="bi bi-arrows-angle-expand me-1"></i>Perubahan Data</span>
        <span class="text-muted">{{ $changed }} kolom berubah</span>
    </div>
    <div class="table-responsive">
        <table class="table diff-table mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:160px">Kolom</th>
                    <th class="diff-removed">Data Lama</th>
                    <th class="diff-added">Data Baru</th>
                </tr>
            </thead>
            <tbody>
                @foreach($keys as $key)
                @php
                    $valOld    = $old[$key] ?? null;
                    $valNew    = $new[$key] ?? null;
                    $isChanged = $valOld !== $valNew;
                @endphp
                <tr class="{{ $isChanged ? '' : 'opacity-50' }}">
                    <td class="diff-key">{{ $key }}</td>
                    <td class="{{ $isChanged ? 'diff-removed' : '' }}">
                        <span class="{{ $isChanged ? 'diff-val-old' : 'text-muted' }}">
                            @if(is_null($valOld)) <em class="opacity-50">null</em>
                            @elseif(is_bool($valOld)) {{ $valOld ? 'true' : 'false' }}
                            @elseif(is_array($valOld)) <pre class="mb-0" style="font-size:.72rem">{{ json_encode($valOld, JSON_PRETTY_PRINT) }}</pre>
                            @else {{ Str::limit((string) $valOld, 200) }}
                            @endif
                        </span>
                    </td>
                    <td class="{{ $isChanged ? 'diff-added' : '' }}">
                        <span class="{{ $isChanged ? 'diff-val-new' : 'text-muted' }}">
                            @if(is_null($valNew)) <em class="opacity-50">null</em>
                            @elseif(is_bool($valNew)) {{ $valNew ? 'true' : 'false' }}
                            @elseif(is_array($valNew)) <pre class="mb-0" style="font-size:.72rem">{{ json_encode($valNew, JSON_PRETTY_PRINT) }}</pre>
                            @else {{ Str::limit((string) $valNew, 200) }}
                            @endif
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@else
<div class="card">
    <div class="card-body text-center py-4 text-muted">
        <i class="bi bi-info-circle me-1"></i>
        Tidak ada detail perubahan kolom untuk log ini.
        @if($log->properties->isNotEmpty())
        <pre class="mt-3 text-start small" style="background:#f8fafc;padding:1rem;border-radius:8px">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        @endif
    </div>
</div>
@endif
@endsection
