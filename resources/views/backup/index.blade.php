@extends('layouts.app')
@section('title', 'Backup Database')

@push('styles')
<style>
.backup-item { border-radius:10px; border:1px solid #e2e8f0; transition:all .15s; }
.backup-item:hover { border-color:#3b82f6; box-shadow:0 2px 8px rgba(59,130,246,.1); }
.backup-size  { font-size:.78rem; color:#64748b; }
.backup-date  { font-size:.78rem; color:#94a3b8; }
.backup-icon  { width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0; }
</style>
@endpush

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-hdd me-2 text-primary"></i>Backup Database</h4>
        <small class="text-muted">Backup otomatis: setiap hari pukul 02:00 WIB &bull; Simpan 30 hari</small>
    </div>
    <form method="POST" action="{{ route('backup.run') }}"
          onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='<span class=\'spinner-border spinner-border-sm me-1\'></span>Membackup...'">
        @csrf
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-hdd-fill me-1"></i>Buat Backup Sekarang
        </button>
    </form>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-3">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show mb-3">
    <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="alert alert-info d-flex gap-2 mb-4" style="font-size:.85rem">
    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
    <div>
        File backup tersimpan di <code>storage/app/backups/</code>.
        Backup otomatis berjalan via Laravel Scheduler (jalankan <code>php artisan schedule:run</code> via cron).
    </div>
</div>

@if(empty($files))
<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-hdd-x display-5 d-block mb-2 opacity-25"></i>
        <p>Belum ada file backup.</p>
        <p class="small">Klik "Buat Backup Sekarang" untuk membuat backup pertama.</p>
    </div>
</div>
@else
<div class="card mb-3">
    <div class="card-header fw-semibold small d-flex justify-content-between">
        <span><i class="bi bi-archive me-1"></i>File Backup</span>
        <span class="text-muted">{{ count($files) }} file</span>
    </div>
    <div class="list-group list-group-flush">
        @foreach($files as $file)
        <div class="list-group-item px-3 py-3">
            <div class="d-flex align-items-center gap-3">
                <div class="backup-icon bg-primary-subtle text-primary">
                    <i class="bi bi-file-zip"></i>
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold small text-truncate" title="{{ $file['name'] }}">{{ $file['name'] }}</div>
                    <div class="d-flex gap-3 mt-1">
                        <span class="backup-size"><i class="bi bi-hdd me-1"></i>{{ $file['size'] }}</span>
                        <span class="backup-date"><i class="bi bi-clock me-1"></i>{{ $file['date'] }}</span>
                        <span class="backup-date text-muted">{{ $file['folder'] }}</span>
                    </div>
                </div>
                <div class="d-flex gap-1 flex-shrink-0">
                    <a href="{{ route('backup.download', ['filename' => urlencode($file['name'])]) }}"
                       class="btn btn-sm btn-outline-success py-1 px-2" title="Download">
                        <i class="bi bi-download"></i>
                    </a>
                    <form method="POST" action="{{ route('backup.destroy', ['filename' => urlencode($file['name'])]) }}"
                          onsubmit="return confirm('Hapus file backup {{ $file['name'] }}?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Hapus backup ini">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

<div class="card">
    <div class="card-header fw-semibold small"><i class="bi bi-info-circle me-1"></i>Konfigurasi Backup</div>
    <div class="card-body small">
        <div class="row g-2">
            <div class="col-6 col-md-3"><div class="text-muted">Jadwal Backup</div><div class="fw-semibold">Setiap hari 02:00</div></div>
            <div class="col-6 col-md-3"><div class="text-muted">Cleanup</div><div class="fw-semibold">Setiap hari 02:30</div></div>
            <div class="col-6 col-md-3"><div class="text-muted">Retensi</div><div class="fw-semibold">30 hari</div></div>
            <div class="col-6 col-md-3"><div class="text-muted">Lokasi</div><div class="fw-semibold">Local Storage</div></div>
        </div>
        <div class="mt-3 p-2 rounded" style="background:#f8fafc;font-size:.78rem">
            <strong>Setup cron (server):</strong><br>
            <code>* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1</code>
        </div>
    </div>
</div>
@endsection
