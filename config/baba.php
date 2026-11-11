<?php

/**
 * Sambungan ERP BABA ↔ aplikasi member (member.babaparfume.id / apimember.babaparfume.id).
 * Dua arah, antar-server, setiap permintaan ditandatangani HMAC-SHA256 dengan kunci rahasia bersama
 * (BABA_MEMBER_API_SECRET, sama dengan ERP_API_SECRET di aplikasi member):
 *  - aplikasi member → ERP: /api/member/* (App\Http\Middleware\VerifikasiAplikasiMember)
 *  - ERP → aplikasi member: /api/erp/*   (App\Services\AplikasiMemberClient)
 */
return [
    'member_api' => [
        'secret' => env('BABA_MEMBER_API_SECRET'),
        // Selisih waktu maksimal antara pengirim & ERP (detik); juga lama nonce diingat.
        'toleransi_detik' => (int) env('BABA_MEMBER_API_TOLERANSI', 300),
    ],

    'member_app' => [
        // Alamat API aplikasi member, mis. https://apimember.babaparfume.id (lokal http://localhost:8000)
        'url'     => rtrim((string) env('BABA_MEMBER_APP_URL', ''), '/'),
        'timeout' => (int) env('BABA_MEMBER_APP_TIMEOUT', 8),
    ],

    // Foto produk online (folder di public/, berisi .htaccess penolak skrip)
    'folder_foto_produk' => 'uploads/produk-online',
];
