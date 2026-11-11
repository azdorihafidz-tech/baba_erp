{{--
    Component: <x-back-button-pwa />
    Tombol "Kembali ke Dashboard" untuk halaman fullscreen yang
    menyembunyikan sidebar/topbar (mis. Scan Absensi, Mode Produksi).
    Selalu tampil (PWA maupun browser normal) — halaman fullscreen ini
    juga menyembunyikan Chrome back button-nya sendiri, jadi tombol ini
    tetap dibutuhkan di luar mode PWA.
--}}
<a href="{{ route('dashboard') }}"
   class="btn btn-light shadow rounded-circle back-btn-pwa"
   title="Kembali ke Dashboard"
   style="position:fixed;top:15px;left:15px;z-index:9999;width:48px;height:48px;display:flex;align-items:center;justify-content:center;">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
        <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
    </svg>
</a>

<style>
.back-btn-pwa {
    background: rgba(255, 255, 255, 0.95);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    transition: transform 0.2s ease;
}
.back-btn-pwa:hover, .back-btn-pwa:active {
    transform: scale(1.08);
}
</style>
