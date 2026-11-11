<?php

/**
 * Konfigurasi Go-Live (dipakai GoLiveSeeder + public/golive.php).
 * Nilai REAL diisi lewat .env production — default di bawah SENGAJA ditolak
 * GoLiveSeeder kalau APP_ENV=production.
 */
return [
    'owner_email' => env('GOLIVE_OWNER_EMAIL', 'owner@dmentai.local'),
    'owner_password' => env('GOLIVE_OWNER_PASSWORD', 'ChangeMe123!'),
    'owner_name' => env('GOLIVE_OWNER_NAME', 'Owner D\'mentai'),

    // Saklar pengaman public/golive.php: harus true di .env, hapus lagi setelah selesai.
    'enabled' => env('GOLIVE_ENABLED', false),

    // Nilai default yang dianggap "belum diganti".
    'default_email' => 'owner@dmentai.local',
    'default_password' => 'ChangeMe123!',
];
