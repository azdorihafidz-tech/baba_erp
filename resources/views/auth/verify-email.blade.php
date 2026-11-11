<x-guest-layout>
    <div class="text-center mb-4">
        <div class="auth-logo">
            <i class="bi bi-envelope-check-fill"></i>
        </div>
        <h1 class="auth-title">Verifikasi Email</h1>
        <p class="auth-subtitle">Terima kasih sudah mendaftar! Silakan verifikasi email Anda dengan klik link yang dikirim ke email Anda.</p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success mb-3">
            Link verifikasi baru telah dikirim ke email Anda.
        </div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="mb-3">
        @csrf
        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-send me-2"></i>Kirim Ulang Email Verifikasi
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn btn-outline-secondary w-100">
            <i class="bi bi-box-arrow-right me-2"></i>Keluar
        </button>
    </form>
</x-guest-layout>
