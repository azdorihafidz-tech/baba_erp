@props([
    'slug',
    'label'   => 'Cara Pakai',
    'variant' => 'outline-info',
])
@php $panduan = \App\Models\Panduan::getBySlug($slug); @endphp
@if($panduan)
    @php $modalId = 'panduanModal-' . Str::slug($slug); @endphp

    {{-- Trigger Button --}}
    <button type="button"
            class="btn btn-sm btn-{{ $variant }}"
            style="min-height:32px"
            data-bs-toggle="modal"
            data-bs-target="#{{ $modalId }}">
        <i class="bi bi-book me-1"></i>{{ $label }}
    </button>

    {{-- Modal (full-screen on mobile) --}}
    <div class="modal fade"
         id="{{ $modalId }}"
         tabindex="-1"
         aria-labelledby="{{ $modalId }}Label"
         aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-md-down">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="{{ $modalId }}Label">
                        <i class="bi bi-book text-info me-2"></i>{{ $panduan->judul }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <div class="modal-body" style="max-height:70vh;overflow-y:auto">
                    <div class="panduan-content">
                        {!! $panduan->konten_html !!}
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Tutup
                    </button>
                </div>

            </div>
        </div>
    </div>

    {{-- Scoped styles for markdown content --}}
    @once
    <style>
    .panduan-content h1,.panduan-content h2,.panduan-content h3 {
        font-size:1.1rem;font-weight:700;margin-top:1.2rem;margin-bottom:.4rem;
    }
    .panduan-content h1 { font-size:1.25rem; }
    .panduan-content ul,.panduan-content ol {
        padding-left:1.4rem;margin-bottom:.8rem;
    }
    .panduan-content li { margin-bottom:.25rem;line-height:1.6; }
    .panduan-content p { margin-bottom:.7rem;line-height:1.7; }
    .panduan-content strong { font-weight:600; }
    .panduan-content code {
        background:#f1f5f9;padding:.1em .35em;border-radius:3px;
        font-size:.85em;color:#0f172a;
    }
    .panduan-content hr { margin:1rem 0;border-color:#e2e8f0; }
    body.dark-mode .panduan-content code { background:#334155;color:#e2e8f0; }
    </style>
    @endonce
@endif
