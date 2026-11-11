<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sedang Offline - {{ $brand->namaSingkat() }}</title>
    <x-brand-warna />
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            background: var(--brand-sidebar);
            color: rgb(var(--brand-sidebar-teks-rgb));
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 20px;
        }
        .container { max-width: 400px; }
        .logo {
            width: 120px;
            height: 120px;
            margin: 0 auto 24px;
            border-radius: 50%;
            background: var(--brand-utama);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: bold;
            color: #fff;
        }
        h1 { font-size: 24px; margin: 0 0 12px; }
        p { font-size: 16px; line-height: 1.5; margin: 0 0 24px; opacity: 0.9; }
        button {
            background: var(--brand-utama);
            color: #fff;
            border: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover { background: #cc5600; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">D'</div>
        <h1>Sedang Offline</h1>
        <p>Kamu sedang tidak terhubung ke internet.<br>Silakan cek koneksi dan refresh halaman ini.</p>
        <button onclick="window.location.reload()">Coba Lagi</button>
    </div>
</body>
</html>
