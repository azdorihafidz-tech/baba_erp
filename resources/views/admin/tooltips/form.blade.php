{{-- Shared form partial untuk create & edit --}}

{{-- Key --}}
<div class="mb-3">
    <label class="form-label fw-semibold" for="key">
        Key <span class="text-danger">*</span>
        <small class="text-muted fw-normal">(contoh: <code>adjustment.qty_fisik</code>)</small>
    </label>
    <input type="text" name="key" id="key"
           class="form-control @error('key') is-invalid @enderror"
           value="{{ old('key', $tooltip->key ?? '') }}"
           placeholder="modul.nama_field" required>
    <div class="form-text">Format: <code>nama_modul.nama_elemen</code>. Huruf kecil, titik sebagai pemisah.</div>
    @error('key') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

{{-- Modul (hybrid: pilih dari daftar atau ketik baru) --}}
<div class="mb-3">
    <label class="form-label fw-semibold" for="modul_select">
        Modul <span class="text-danger">*</span>
    </label>
    @php
        $currentModul = old('modul', $tooltip->modul ?? '');
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
    <div class="form-text">Nama modul/halaman, mis. <code>adjustment</code>, <code>penjualan</code>, <code>stok</code>.</div>
    @error('modul') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

{{-- Title --}}
<div class="mb-3">
    <label class="form-label fw-semibold" for="title">Judul (Opsional)</label>
    <input type="text" name="title" id="title"
           class="form-control @error('title') is-invalid @enderror"
           value="{{ old('title', $tooltip->title ?? '') }}"
           placeholder="Qty Stok Fisik" maxlength="100">
    <div class="form-text">Ditampilkan sebagai judul bold di atas konten tooltip.</div>
    @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

{{-- Content --}}
<div class="mb-3">
    <label class="form-label fw-semibold" for="content">
        Konten <span class="text-danger">*</span>
    </label>
    <textarea name="content" id="content" rows="3"
              class="form-control @error('content') is-invalid @enderror"
              placeholder="Penjelasan singkat yang muncul saat user hover/tap ikon ❓"
              maxlength="500" required>{{ old('content', $tooltip->content ?? '') }}</textarea>
    <div class="form-text d-flex justify-content-between">
        <span>Maksimal 500 karakter.</span>
        <span id="contentCount" class="text-muted">0/500</span>
    </div>
    @error('content') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

{{-- Urutan --}}
<div class="mb-3">
    <label class="form-label fw-semibold" for="urutan">Urutan</label>
    <input type="number" name="urutan" id="urutan"
           class="form-control @error('urutan') is-invalid @enderror"
           value="{{ old('urutan', $tooltip->urutan ?? 0) }}"
           min="0" style="max-width:120px">
    <div class="form-text">Urutan tampil di halaman admin. Angka kecil = muncul lebih awal.</div>
    @error('urutan') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

{{-- Aktif --}}
<div class="mb-4">
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="aktif" id="aktif" value="1"
               {{ old('aktif', $tooltip->aktif ?? true) ? 'checked' : '' }}>
        <label class="form-check-label fw-semibold" for="aktif">Aktif</label>
    </div>
    <div class="form-text">Tooltip yang tidak aktif tidak akan muncul di halaman, meski sudah dipasang.</div>
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
    const ta = document.getElementById('content');
    const counter = document.getElementById('contentCount');
    if (!ta || !counter) return;
    function update() { counter.textContent = ta.value.length + '/500'; }
    ta.addEventListener('input', update);
    update();
})();
</script>
@endpush
