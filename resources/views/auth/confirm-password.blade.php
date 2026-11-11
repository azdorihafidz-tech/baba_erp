<x-guest-layout>
    <div class="text-center mb-4">
        <div class="auth-logo">
            <i class="bi bi-shield-lock-fill"></i>
        </div>
        <h1 class="auth-title">Konfirmasi Password</h1>
        <p class="auth-subtitle">Ini adalah area aman. Konfirmasi password Anda untuk melanjutkan.</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input id="password" type="password" name="password"
                class="form-control @error('password') is-invalid @enderror"
                required autocomplete="current-password" placeholder="••••••••">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-shield-check me-2"></i>Konfirmasi
        </button>
    </form>
</x-guest-layout>
