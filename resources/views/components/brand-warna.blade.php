{{-- 4.48 — Warna Aplikasi dari Pengaturan Umum sebagai variabel CSS
     (--brand-utama, --brand-sidebar, --brand-latar, dst). Dipakai layout
     aplikasi, login, halaman error & offline. PDF tidak memakai ini (dompdf
     tidak mendukung variabel CSS) — PDF membaca $brand->warna() langsung. --}}
@php $brandWarna = $brand ?? app(\App\Support\Branding::class); @endphp
<style id="brandWarna">{!! $brandWarna->cssVariabel() !!}</style>
{{-- Untuk grafik Chart.js (kanvas tidak bisa membaca variabel CSS) --}}
<script>window.BRAND = @json($brandWarna->warnaJs());</script>
