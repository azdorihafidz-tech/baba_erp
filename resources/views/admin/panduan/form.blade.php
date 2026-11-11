{{-- Shared form partial for create & edit --}}
<div class="row g-3">

    {{-- Slug --}}
    <div class="col-12 col-md-6">
        <label class="form-label fw-semibold" for="slug">
            Slug <span class="text-danger">*</span>
            <small class="text-muted fw-normal">(unik, huruf kecil + tanda hubung)</small>
        </label>
        <input type="text" name="slug" id="slug" class="form-control @error('slug') is-invalid @enderror"
               value="{{ old('slug', $panduan->slug) }}"
               placeholder="contoh: cara-pakai-pos"
               pattern="[a-z0-9\-]+"
               required>
        @error('slug')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Modul (hybrid: pilih dari daftar atau ketik baru) --}}
    <div class="col-12 col-md-6">
        <label class="form-label fw-semibold" for="modul_select">
            Modul <span class="text-danger">*</span>
        </label>
        @php
            $currentModul = old('modul', $panduan->modul ?? '');
            $modulInList  = $currentModul !== '' && $moduls->contains($currentModul);
            $isTypingNew  = $currentModul !== '' && !$modulInList;
        @endphp
        <select id="modul_select"
                name="{{ $isTypingNew ? '' : 'modul' }}"
                class="form-select @error('modul') is-invalid @enderror"
                onchange="toggleModulInput(this)">
            @if(!$currentModul)
            <option value="" disabled selected>-- Pilih Modul --</option>
            @endif
            @foreach($moduls as $m)
            <option value="{{ $m }}" @selected($modulInList && $currentModul === $m)>{{ $m }}</option>
            @endforeach
            <option value="__new__" @selected($isTypingNew)>+ Ketik modul baru...</option>
        </select>
        <input type="text" id="modul_input"
               name="{{ $isTypingNew ? 'modul' : '' }}"
               class="form-control mt-1 @error('modul') is-invalid @enderror"
               style="{{ $isTypingNew ? '' : 'display:none' }}"
               value="{{ $isTypingNew ? $currentModul : '' }}"
               placeholder="ketik nama modul baru (huruf kecil, tanpa spasi)..."
               {{ $isTypingNew ? 'required' : '' }}>
        @error('modul')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Judul --}}
    <div class="col-12">
        <label class="form-label fw-semibold" for="judul">
            Judul <span class="text-danger">*</span>
        </label>
        <input type="text" name="judul" id="judul"
               class="form-control @error('judul') is-invalid @enderror"
               value="{{ old('judul', $panduan->judul) }}"
               placeholder="contoh: Cara Pakai Menu POS"
               maxlength="200" required>
        @error('judul')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Konten (Markdown) --}}
    <div class="col-12">
        <label class="form-label fw-semibold d-flex justify-content-between align-items-center" for="konten">
            <span>Konten <span class="text-danger">*</span>
                <small class="text-muted fw-normal">(format Markdown)</small>
            </span>
            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2"
                    style="font-size:.75rem"
                    data-bs-toggle="modal" data-bs-target="#previewModal">
                <i class="bi bi-eye me-1"></i>Preview
            </button>
        </label>
        <textarea name="konten" id="konten" rows="18"
                  class="form-control font-monospace @error('konten') is-invalid @enderror"
                  placeholder="## Judul&#10;Tulis panduan dalam format Markdown..."
                  required>{{ old('konten', $panduan->konten) }}</textarea>
        <div class="d-flex justify-content-between mt-1">
            @error('konten')
            <div class="text-danger small">{{ $message }}</div>
            @else
            <small class="text-muted">Gunakan Markdown: ## Heading, **bold**, - list, 1. numbered</small>
            @enderror
            <small class="text-muted ms-auto" id="charCount">0 karakter</small>
        </div>
    </div>

    {{-- Urutan + Aktif --}}
    <div class="col-12 col-md-4">
        <label class="form-label fw-semibold" for="urutan">Urutan</label>
        <input type="number" name="urutan" id="urutan" class="form-control"
               value="{{ old('urutan', $panduan->urutan ?? 0) }}" min="0">
    </div>

    <div class="col-12 col-md-8 d-flex align-items-end pb-1">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="aktif" id="aktif" value="1"
                   {{ old('aktif', $panduan->aktif ?? true) ? 'checked' : '' }}>
            <label class="form-check-label fw-semibold" for="aktif">Aktif (tampil di halaman Panduan)</label>
        </div>
    </div>

</div>

{{-- Preview Modal --}}
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-md-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Preview Konten</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height:65vh;overflow-y:auto">
                <div class="panduan-content" id="previewBody">
                    <p class="text-muted"><em>Klik Preview untuk melihat hasil render...</em></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function toggleModulInput(sel) {
    const inp = document.getElementById('modul_input');
    if (sel.value === '__new__') {
        inp.style.display = '';
        inp.name = 'modul';
        inp.required = true;
        sel.name = '';
        inp.focus();
    } else {
        inp.style.display = 'none';
        inp.name = '';
        inp.required = false;
        sel.name = 'modul';
        inp.value = '';
    }
}

(function () {
    const textarea  = document.getElementById('konten');
    const charCount = document.getElementById('charCount');
    const previewEl = document.getElementById('previewBody');
    const previewModal = document.getElementById('previewModal');

    function updateCount() {
        charCount.textContent = textarea.value.length + ' karakter';
    }
    textarea.addEventListener('input', updateCount);
    updateCount();

    // Fetch rendered preview from server
    previewModal.addEventListener('show.bs.modal', function () {
        previewEl.innerHTML = '<p class="text-muted"><em>Memuat...</em></p>';
        fetch('{{ route("admin.panduan.preview") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ konten: textarea.value }),
        })
        .then(r => r.json())
        .then(d => { previewEl.innerHTML = d.html || '<em>Kosong</em>'; })
        .catch(() => { previewEl.innerHTML = '<em class="text-danger">Gagal memuat preview.</em>'; });
    });
})();
</script>
<style>
.panduan-content h1,.panduan-content h2,.panduan-content h3{font-size:1.1rem;font-weight:700;margin-top:1.2rem;margin-bottom:.4rem}
.panduan-content h1{font-size:1.25rem}
.panduan-content ul,.panduan-content ol{padding-left:1.4rem;margin-bottom:.8rem}
.panduan-content li{margin-bottom:.25rem;line-height:1.6}
.panduan-content p{margin-bottom:.7rem;line-height:1.7}
.panduan-content strong{font-weight:600}
.panduan-content code{background:#f1f5f9;padding:.1em .35em;border-radius:3px;font-size:.85em;color:#0f172a}
</style>
@endpush
