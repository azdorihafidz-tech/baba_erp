{{-- 4.50 Tombol Kamus Menu di header: langsung ke penjelasan menu yang sedang dibuka (kalau ada). --}}
@php
    $kamusIni = auth()->check()
        ? \App\Models\KamusMenu::untukRoute(request()->route()?->getName(), auth()->user())
        : null;
@endphp
@auth
<a href="{{ $kamusIni ? route('kamus-menu.show', $kamusIni->slug) : route('kamus-menu.index') }}"
   class="btn btn-sm btn-light d-flex align-items-center gap-1"
   title="{{ $kamusIni ? 'Kamus Menu: ' . $kamusIni->judul . ' — fungsi, syarat, alur & dampaknya' : 'Kamus Menu — cari penjelasan menu' }}">
    <i class="bi bi-search-heart" style="font-size:0.9rem"></i>
    <span class="d-none d-lg-inline" style="font-size:0.78rem">Kamus</span>
</a>
@endauth
