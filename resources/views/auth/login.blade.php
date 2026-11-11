<x-guest-layout>
    <div class="text-center mb-4">
        <div class="auth-logo">
            <img src="{{ $brand->logoUrl() }}" alt="{{ $brand->namaSingkat() }}">
        </div>
        <h1 class="auth-title">Selamat Datang</h1>
        <p class="auth-subtitle">Masuk ke ERP {{ $brand->namaSingkat() }}</p>
    </div>

    <!-- Session Status -->
    @if (session('status'))
        <div class="alert alert-success mb-3">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" name="email"
                class="form-control @error('email') is-invalid @enderror"
                value="{{ old('email') }}" required autofocus autocomplete="username"
                placeholder="email@dmentai.com">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <label for="password" class="form-label mb-0">Password</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-decoration-none" style="font-size:0.8rem;color:#3b82f6">
                        Lupa password?
                    </a>
                @endif
            </div>
            <input id="password" type="password" name="password"
                class="form-control @error('password') is-invalid @enderror"
                required autocomplete="current-password"
                placeholder="••••••••">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="remember_me" name="remember">
            <label class="form-check-label" for="remember_me" style="font-size:0.875rem">
                Ingat saya
            </label>
        </div>

        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-box-arrow-in-right me-2"></i>Masuk
        </button>
    </form>
</x-guest-layout>
