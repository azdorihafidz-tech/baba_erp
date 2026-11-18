@extends('layouts.app')

@section('title', 'Backup Database')

@section('content')
@php $ukuran = fn ($b) => $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : number_format($b / 1024, 0, ',', '.') . ' KB'; @endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-hdd me-2 text-primary"></i>Backup Database</h5>
    <x-panduan-button slug="backup-database" />
</div>

<div class="alert alert-{{ $sehat ? 'success' : 'warning' }} small py-2" data-uji="status-cadangan">
    @if($sehat)
    <i class="bi bi-database-check me-1"></i><strong>Aman</strong> — cadangan terbaru dibuat kurang dari 26 jam yang lalu.
    @else
    <i class="bi bi-database-exclamation me-1"></i><strong>{{ $files->isEmpty() ? 'Belum ada cadangan.' : 'Cadangan terakhir sudah lebih dari 26 jam.' }}</strong>
    Cadangan dibuat oleh cron server — @if(auth()->user()->aksesPenuh())cara memasangnya ada di <a href="{{ route('pengaturan.jadwal') }}">Pengaturan › Jadwal Otomatis</a>.@else hubungi Owner.@endif
    @endif
</div>

<div class="card mb-3">
    <div class="card-header fw-semibold small">File cadangan (disimpan {{ $simpanHari }} hari, dibuat otomatis jam 02.00)</div>
    <div class="list-group list-group-flush">
    @forelse($files as $f)
        <div class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 small" data-uji="file-backup">
            <span><i class="bi bi-file-earmark-zip me-1"></i>{{ $f['waktu']->format('d/m/Y H:i') }} · {{ $ukuran($f['ukuran']) }}</span>
            <span class="d-flex gap-1">
                @can('download_backup')
                <a href="{{ route('backup.download', ['filename' => $f['nama']]) }}" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-download"></i> Unduh</a>
                @endcan
                @can('hapus_backup')
                <form method="POST" action="{{ route('backup.destroy', ['filename' => $f['nama']]) }}" onsubmit="return confirm('Hapus file cadangan ini?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger py-0"><i class="bi bi-trash"></i></button>
                </form>
                @endcan
            </span>
        </div>
    @empty
        <div class="list-group-item text-center text-muted py-4 small">Belum ada file cadangan.</div>
    @endforelse
    </div>
</div>

<div class="small text-muted">Simpan juga salinan di luar server sesekali (klik Unduh, simpan di komputer/Google Drive). File cadangan berisi seluruh data ERP — jangan dibagikan.</div>
@endsection
