{{--
    Cabang Switcher Dropdown Component
    Usage: <x-cabang-switcher />
    Requires: $authUser, $activeCabang, $userCabangs (shared via CabangMiddleware)
--}}

@if(isset($authUser))
<div class="dropdown">
    <button
        class="btn btn-sm p-0 border-0 bg-transparent"
        type="button"
        data-bs-toggle="dropdown"
        aria-expanded="false"
        title="Klik untuk ganti cabang aktif">
        <div class="cabang-badge">
            <i class="bi bi-{{ isset($activeCabang) && $activeCabang
                ? ($activeCabang->tipe->value === 'gudang_pusat' ? 'building' : 'geo-alt-fill')
                : 'globe' }}"></i>
            <span class="cabang-badge-text">
                @if(isset($activeCabang) && $activeCabang)
                    {{ $activeCabang->nama_cabang }}
                @else
                    Semua Cabang
                @endif
            </span>
            <i class="bi bi-chevron-down" style="font-size:0.55rem;opacity:0.7"></i>
        </div>
    </button>

    <div class="dropdown-menu dropdown-menu-end shadow cabang-switcher-menu" style="min-width:260px;border:1px solid #e2e8f0">
        {{-- Header --}}
        <div class="px-3 py-2 border-bottom" style="background:#f8fafc">
            <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;font-weight:600">
                <i class="bi bi-arrow-left-right me-1"></i>Pilih Cabang Aktif
            </div>
        </div>

        {{-- Mode Semua Cabang — hanya Owner / Admin Pusat --}}
        @if($authUser->canAccessAllBranches())
        <div class="px-2 py-1">
            <form action="{{ route('cabang.switch') }}" method="POST">
                @csrf
                <input type="hidden" name="cabang_id" value="">
                <button type="submit"
                    class="dropdown-item rounded-2 d-flex align-items-center gap-2 py-2
                           {{ !isset($activeCabang) || !$activeCabang ? 'active' : '' }}">
                    <div style="width:30px;height:30px;border-radius:6px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="bi bi-globe" style="font-size:0.85rem;color:#64748b"></i>
                    </div>
                    <div class="flex-grow-1 min-w-0 text-start">
                        <div style="font-size:0.8rem;font-weight:600;line-height:1.2">Semua Cabang</div>
                        <div style="font-size:0.7rem;color:#94a3b8;line-height:1.2">Tampilkan data gabungan</div>
                    </div>
                    @if(!isset($activeCabang) || !$activeCabang)
                    <i class="bi bi-check2 text-primary flex-shrink-0"></i>
                    @endif
                </button>
            </form>
        </div>
        <div class="dropdown-divider my-1"></div>
        @endif

        {{-- Daftar cabang user --}}
        @if(isset($userCabangs) && $userCabangs->isNotEmpty())
        <div class="px-2 py-1">
            @foreach($userCabangs as $cabang)
            <form action="{{ route('cabang.switch') }}" method="POST" class="mb-1">
                @csrf
                <input type="hidden" name="cabang_id" value="{{ $cabang->id }}">
                <button type="submit"
                    class="dropdown-item rounded-2 d-flex align-items-center gap-2 py-2
                           {{ isset($activeCabang) && $activeCabang?->id == $cabang->id ? 'active' : '' }}">
                    <div style="width:30px;height:30px;border-radius:6px;background:{{ $cabang->tipe->value === 'gudang_pusat' ? '#fef3c7' : '#dbeafe' }};display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="bi bi-{{ $cabang->tipe->value === 'gudang_pusat' ? 'building' : 'shop' }}"
                           style="font-size:0.85rem;color:{{ $cabang->tipe->value === 'gudang_pusat' ? '#92400e' : '#1e40af' }}"></i>
                    </div>
                    <div class="flex-grow-1 min-w-0 text-start">
                        <div style="font-size:0.8rem;font-weight:600;line-height:1.2" class="text-truncate">
                            {{ $cabang->nama_cabang }}
                        </div>
                        <div style="font-size:0.7rem;color:#94a3b8;line-height:1.2">
                            {{ $cabang->kode_cabang }}
                            &bull;
                            {{ $cabang->tipe->label() }}
                        </div>
                    </div>
                    @if(isset($activeCabang) && $activeCabang?->id == $cabang->id)
                    <i class="bi bi-check2 text-primary flex-shrink-0"></i>
                    @endif
                </button>
            </form>
            @endforeach
        </div>
        @else
        <div class="px-3 py-3 text-center text-muted" style="font-size:0.8rem">
            <i class="bi bi-inbox me-1"></i>Tidak ada cabang tersedia
        </div>
        @endif

        {{-- Footer: link ke manajemen cabang (owner only) --}}
        @if($authUser->canAccessAllBranches())
        <div class="border-top px-3 py-2 mt-1" style="background:#f8fafc">
            <a href="{{ route('cabang.index') }}"
               class="d-flex align-items-center gap-2 text-decoration-none"
               style="font-size:0.75rem;color:#3b82f6">
                <i class="bi bi-gear"></i>
                <span>Kelola Cabang & Gudang</span>
                <i class="bi bi-arrow-right ms-auto"></i>
            </a>
        </div>
        @endif
    </div>
</div>
@endif
