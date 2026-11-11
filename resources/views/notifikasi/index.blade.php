@extends('layouts.app')
@section('title', 'Notifikasi')

@section('content')

@php
    $colorMap = [
        'danger'  => ['text' => 'text-danger',  'light' => 'bg-danger-subtle',  'border' => 'border-danger-subtle'],
        'warning' => ['text' => 'text-warning', 'light' => 'bg-warning-subtle', 'border' => 'border-warning-subtle'],
        'success' => ['text' => 'text-success', 'light' => 'bg-success-subtle', 'border' => 'border-success-subtle'],
        'info'    => ['text' => 'text-info',    'light' => 'bg-info-subtle',    'border' => 'border-info-subtle'],
    ];
@endphp

<div class="d-flex align-items-center justify-content-between gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <h5 class="fw-bold mb-0"><i class="bi bi-bell-fill me-2 text-primary"></i>Notifikasi</h5>
        @if($unreadCount > 0)
        <span class="badge bg-danger">{{ $unreadCount }} belum dibaca</span>
        @endif
    </div>
    <x-panduan-button slug="notifikasi" />
</div>

{{-- Flash --}}
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Filter Bar --}}
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('notifikasi.index') }}" class="row g-2 align-items-end">
            {{-- Status --}}
            <div class="col-12 col-sm-auto">
                <label class="form-label mb-1" style="font-size:0.78rem">Status</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="semua"       {{ $status === 'semua'       ? 'selected' : '' }}>Semua</option>
                    <option value="belum_dibaca"{{ $status === 'belum_dibaca'? 'selected' : '' }}>Belum Dibaca</option>
                    <option value="sudah_dibaca"{{ $status === 'sudah_dibaca'? 'selected' : '' }}>Sudah Dibaca</option>
                </select>
            </div>
            {{-- Kategori --}}
            <div class="col-12 col-sm-auto">
                <label class="form-label mb-1" style="font-size:0.78rem">Kategori</label>
                <select name="kategori" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Semua Kategori</option>
                    @foreach($kategoris as $val => $label)
                    <option value="{{ $val }}" {{ request('kategori') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            {{-- Bulk action --}}
            @if($unreadCount > 0)
            <div class="col-12 col-sm-auto ms-sm-auto">
                <button type="submit" form="formTandaiSemuaDibaca" class="btn btn-outline-primary btn-sm w-100">
                    <i class="bi bi-check2-all me-1"></i>Tandai Semua Dibaca
                </button>
            </div>
            @endif
        </form>
    </div>
</div>

{{--
    Tahap 7 D'mentai (Bug 5 fix, 2026-09-15) — form "Tandai Semua Dibaca"
    WAJIB sibling dari form filter (GET), BUKAN nested (root cause sama
    dengan Bug 3, lihat CLAUDE.md 4.13). Nested <form> di sini membuat
    tombol ter-asosiasi ke form filter GET yang salah, bukan POST ke
    notifikasi.read-all -- fitur "tandai semua dibaca" silent tidak
    berfungsi (tidak error, cuma reload filter doang).
--}}
@if($unreadCount > 0)
<form method="POST" id="formTandaiSemuaDibaca" action="{{ route('notifikasi.read-all') }}">
    @csrf
</form>
@endif

{{-- Daftar Notifikasi --}}
@if($notifications->isEmpty())
<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-bell-slash fs-1 text-secondary opacity-50 d-block mb-3"></i>
        <p class="text-secondary mb-0">Tidak ada notifikasi
            @if(request('status') === 'belum_dibaca') yang belum dibaca
            @elseif(request('status') === 'sudah_dibaca') yang sudah dibaca
            @endif.
        </p>
    </div>
</div>
@else

{{-- Desktop: List --}}
<div class="d-none d-md-block">
    <div class="card p-0 overflow-hidden">
        @foreach($notifications as $notif)
        @php
            $data   = is_array($notif->data) ? $notif->data : json_decode($notif->data, true);
            $color  = $data['color'] ?? 'info';
            $colors = $colorMap[$color] ?? $colorMap['info'];
            $isRead = $notif->read_at !== null;
            $url    = $data['url'] ?? '#';
        @endphp
        <div class="d-flex gap-3 px-4 py-3 border-bottom {{ $isRead ? 'bg-white' : 'bg-primary-subtle' }}"
             style="{{ $loop->last ? 'border-bottom:none!important' : '' }}">
            {{-- Ikon --}}
            <div class="flex-shrink-0 d-flex align-items-start pt-1">
                <div class="{{ $colors['light'] }} {{ $colors['text'] }} rounded-circle d-flex align-items-center justify-content-center"
                     style="width:40px;height:40px;font-size:1rem">
                    <i class="bi {{ $data['icon'] ?? 'bi-bell' }}"></i>
                </div>
            </div>
            {{-- Konten --}}
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <div>
                        <p class="mb-0 fw-{{ $isRead ? 'normal' : 'semibold' }}" style="font-size:0.875rem;color:#1e293b">
                            {{ $data['title'] ?? 'Notifikasi' }}
                            @if(!$isRead)
                            <span class="badge bg-primary ms-1" style="font-size:0.6rem;vertical-align:middle">Baru</span>
                            @endif
                        </p>
                        <p class="mb-1 text-secondary" style="font-size:0.8rem">{{ $data['message'] ?? '' }}</p>
                        <div class="d-flex align-items-center gap-2" style="font-size:0.72rem;color:#94a3b8">
                            <span><i class="bi bi-clock me-1"></i>{{ $notif->created_at->diffForHumans() }}</span>
                            @if($isRead && $notif->read_at)
                            <span><i class="bi bi-check2 me-1"></i>Dibaca {{ $notif->read_at->diffForHumans() }}</span>
                            @endif
                            @if(isset($data['kategori']))
                            <span class="badge {{ $colors['light'] }} {{ $colors['text'] }} border {{ $colors['border'] }}"
                                  style="font-size:0.62rem">
                                {{ $kategoris[$data['kategori']] ?? ucfirst($data['kategori']) }}
                            </span>
                            @endif
                        </div>
                    </div>
                    {{-- Aksi --}}
                    <div class="flex-shrink-0 d-flex gap-1">
                        @if(!$isRead)
                        <form method="POST" action="{{ route('notifikasi.read', $notif->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-primary" title="Tandai Dibaca">
                                <i class="bi bi-check2"></i>
                            </button>
                        </form>
                        @endif
                        @if($url && $url !== '#')
                        <a href="{{ $url }}" class="btn btn-sm btn-outline-secondary" title="Buka">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                        @endif
                        <form method="POST" action="{{ route('notifikasi.destroy', $notif->id) }}"
                              onsubmit="return confirm('Hapus notifikasi ini?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>

{{-- Mobile: Card View --}}
<div class="d-block d-md-none">
    @foreach($notifications as $notif)
    @php
        $data   = is_array($notif->data) ? $notif->data : json_decode($notif->data, true);
        $color  = $data['color'] ?? 'info';
        $colors = $colorMap[$color] ?? $colorMap['info'];
        $isRead = $notif->read_at !== null;
        $url    = $data['url'] ?? '#';
    @endphp
    <div class="card mb-2 {{ $isRead ? '' : 'border-primary' }}">
        <div class="card-body py-2 px-3">
            <div class="d-flex gap-2">
                <div class="{{ $colors['light'] }} {{ $colors['text'] }} rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:36px;height:36px;font-size:0.9rem">
                    <i class="bi {{ $data['icon'] ?? 'bi-bell' }}"></i>
                </div>
                <div class="flex-grow-1 min-w-0">
                    <p class="mb-0 fw-{{ $isRead ? 'normal' : 'semibold' }}" style="font-size:0.82rem">
                        {{ $data['title'] ?? 'Notifikasi' }}
                        @if(!$isRead)<span class="badge bg-primary ms-1" style="font-size:0.58rem">Baru</span>@endif
                    </p>
                    <p class="mb-1 text-secondary" style="font-size:0.75rem;line-height:1.4">{{ $data['message'] ?? '' }}</p>
                    <div class="d-flex align-items-center justify-content-between">
                        <small class="text-muted" style="font-size:0.7rem">
                            <i class="bi bi-clock me-1"></i>{{ $notif->created_at->diffForHumans() }}
                            @if(isset($data['kategori']))
                            &bull;
                            <span class="{{ $colors['text'] }}">{{ $kategoris[$data['kategori']] ?? ucfirst($data['kategori']) }}</span>
                            @endif
                        </small>
                        <div class="d-flex gap-1">
                            @if(!$isRead)
                            <form method="POST" action="{{ route('notifikasi.read', $notif->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-xs btn-outline-primary" style="padding:1px 6px;font-size:0.7rem">
                                    <i class="bi bi-check2"></i>
                                </button>
                            </form>
                            @endif
                            @if($url && $url !== '#')
                            <a href="{{ $url }}" class="btn btn-xs btn-outline-secondary" style="padding:1px 6px;font-size:0.7rem">
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                            @endif
                            <form method="POST" action="{{ route('notifikasi.destroy', $notif->id) }}"
                                  onsubmit="return confirm('Hapus?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-outline-danger" style="padding:1px 6px;font-size:0.7rem">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Pagination --}}
<div class="mt-3">
    {{ $notifications->links() }}
</div>
@endif

@endsection
