<x-guest-layout>
    <div class="text-center mb-4">
        <div class="auth-logo">
            <i class="bi bi-lock-fill"></i>
        </div>
        <h1 class="auth-title">Lupa Password?</h1>
        <p class="auth-subtitle">Masukkan email Anda untuk reset password</p>
    </div>

    @if (session('status'))
        <div class="alert alert-success mb-3">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" name="email"
                class="form-control @error('email') is-invalid @enderror"
                value="{{ old('email') }}" required autofocus
                placeholder="email@dmentai.com">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-100 mb-3">
            <i class="bi bi-envelope me-2"></i>Kirim Link Reset
        </button>

        <div class="text-center">
            <a href="{{ route('login') }}" class="text-decoration-none" style="color:#3b82f6;font-size:0.875rem">
                <i class="bi bi-arrow-left me-1"></i>Kembali ke Login
            </a>
        </div>
    </form>
</x-guest-layout>
