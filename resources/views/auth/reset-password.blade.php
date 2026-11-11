<x-guest-layout>
    <div class="text-center mb-4">
        <div class="auth-logo">
            <i class="bi bi-key-fill"></i>
        </div>
        <h1 class="auth-title">Reset Password</h1>
        <p class="auth-subtitle">Masukkan password baru Anda</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" name="email"
                class="form-control @error('email') is-invalid @enderror"
                value="{{ old('email', $request->email) }}" required autofocus>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password Baru</label>
            <input id="password" type="password" name="password"
                class="form-control @error('password') is-invalid @enderror"
                required autocomplete="new-password" placeholder="Min. 8 karakter">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password_confirmation" class="form-label">Konfirmasi Password</label>
            <input id="password_confirmation" type="password" name="password_confirmation"
                class="form-control" required autocomplete="new-password" placeholder="Ulangi password">
        </div>

        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-shield-check me-2"></i>Reset Password
        </button>
    </form>
</x-guest-layout>
