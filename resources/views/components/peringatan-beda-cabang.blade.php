{{--
    Peringatan saat membuka data milik cabang lain dari cabang aktif yang
    berbeda (mis. lewat link/bookmark) — supaya tidak tanpa sadar mengubah
    data cabang lain (CLAUDE.md 4.39). Props: $cabangId (pemilik data).
--}}
@props(['cabangId' => null])
@php
    $aktif = \App\Support\CabangAktif::id();
    $beda = $cabangId && $aktif && (int) $cabangId !== (int) $aktif;
@endphp
@if($beda)
    <div class="alert alert-warning d-flex align-items-start gap-2 py-2">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div>
            Data ini milik <strong>{{ \App\Models\Cabang::find($cabangId)?->nama_cabang ?? 'cabang lain' }}</strong>,
            bukan cabang yang sedang dipilih (<strong>{{ \App\Models\Cabang::find($aktif)?->nama_cabang }}</strong>).
            Perubahan di sini berlaku untuk cabang pemiliknya.
        </div>
    </div>
@endif
