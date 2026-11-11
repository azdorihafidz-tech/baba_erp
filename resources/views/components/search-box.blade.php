{{--
    Search box reusable — dipasang DI DALAM form filter GET yang sudah ada
    (row g-2 [align-items-end]) supaya search + filter dropdown/tanggal lain
    otomatis "nyatu" dalam satu submit, tanpa perlu JS penggabung query
    string. Murni pengganti markup <input name="search"> yang sebelumnya
    ditulis ulang manual di tiap halaman (lihat CLAUDE.md Rule terkait).

    Props:
        name        – nama field GET, default 'search'
        placeholder – teks placeholder, WAJIB diisi spesifik per halaman
                      (sebutkan field yang di-search, mis. "Cari nama / kode / telepon...")
        col         – class kolom Bootstrap, default responsive standar
        label       – teks label di atas input (opsional, default null/tanpa label).
                      WAJIB diisi (mis. "Cari") kalau field filter LAIN di baris
                      yang sama juga punya <label> — supaya tinggi elemen sejajar.
                      Biarkan kosong kalau field filter lain di baris yang sama
                      juga tanpa label (biar tetap seragam).
--}}
@props([
    'name' => 'search',
    'placeholder' => 'Cari...',
    'col' => 'col-12 col-sm-7 col-md-5',
    'label' => null,
])
<div class="{{ $col }}">
    @if($label)
    <label class="form-label form-label-sm mb-1">{{ $label }}</label>
    @endif
    <div class="input-group input-group-sm">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="{{ $name }}" class="form-control form-control-sm"
            placeholder="{{ $placeholder }}" value="{{ request($name) }}">
    </div>
</div>
