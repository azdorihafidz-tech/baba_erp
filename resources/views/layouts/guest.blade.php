<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ERP {{ $brand->namaSingkat() }}</title>
    <link rel="icon" type="image/png" href="{{ $brand->faviconUrl() }}">
    <link rel="shortcut icon" type="image/png" href="{{ $brand->faviconUrl() }}">
    <link rel="apple-touch-icon" href="{{ $brand->faviconUrl() }}">

    <x-brand-warna />
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(145deg, var(--brand-sidebar) 0%, var(--brand-sidebar) 40%, var(--brand-utama) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
            overflow: hidden;
        }

        /* Dekorasi lingkaran di background */
        body::before {
            content: '';
            position: fixed;
            top: -120px; right: -120px;
            width: 400px; height: 400px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            pointer-events: none;
        }
        body::after {
            content: '';
            position: fixed;
            bottom: -100px; left: -100px;
            width: 320px; height: 320px;
            border-radius: 50%;
            background: rgba(255,255,255,0.04);
            pointer-events: none;
        }

        .auth-card {
            background: white;
            border-radius: 20px;
            padding: 3rem 3.5rem;
            width: 100%;
            max-width: 520px;
            box-shadow: 0 25px 70px rgba(0,0,0,0.35);
            position: relative;
            z-index: 1;
        }

        /* Garis aksen merah di atas card */
        .auth-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--brand-sidebar), var(--brand-utama));
            border-radius: 20px 20px 0 0;
        }

        .auth-logo {
            width: 90px;
            height: 90px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            overflow: hidden;
            background: var(--brand-latar);
            box-shadow: 0 4px 16px rgba(var(--brand-utama-rgb),0.25);
        }

        .auth-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 6px;
        }

        .auth-title {
            font-size: 1.65rem;
            font-weight: 700;
            color: #1e293b;
        }

        .auth-subtitle {
            color: #64748b;
            font-size: 0.95rem;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border-color: #e2e8f0;
            padding: 0.8rem 1rem;
            font-size: 1rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--brand-utama);
            box-shadow: 0 0 0 3px rgba(var(--brand-utama-rgb),0.15);
        }

        .btn-primary {
            background: linear-gradient(90deg, var(--brand-sidebar), var(--brand-utama));
            border: none;
            border-radius: 10px;
            padding: 0.85rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .btn-primary:hover {
            background: linear-gradient(90deg, var(--brand-sidebar), var(--brand-utama-gelap));
            border: none;
        }

        .btn-primary:focus {
            box-shadow: 0 0 0 3px rgba(var(--brand-utama-rgb),0.3);
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            font-size: 0.95rem;
        }

        .invalid-feedback { font-size: 0.85rem; }

        /* Teks link lupa password */
        a[href*="password"] {
            color: var(--brand-utama) !important;
        }
        a[href*="password"]:hover {
            color: #1A1A1A !important;
        }

        /* Footer branding kecil di bawah card */
        .auth-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.75rem;
            color: rgba(255,255,255,0.5);
        }
    </style>
</head>
<body>
    <div>
        <div class="auth-card">
            {{ $slot }}
        </div>
        <div class="auth-footer">
            &copy; {{ date('Y') }} {{ $brand->namaSingkat() }} &mdash; ERP System
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
