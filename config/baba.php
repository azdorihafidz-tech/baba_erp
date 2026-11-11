<?php

/**
 * Sambungan ERP BABA ↔ aplikasi member (member.babaparfume.id / apimember.babaparfume.id).
 * Aplikasi member memanggil ERP antar-server: setiap permintaan ditandatangani HMAC-SHA256
 * dengan kunci rahasia bersama (BABA_MEMBER_API_SECRET, sama dengan ERP_API_SECRET di aplikasi member).
 * Lihat App\Http\Middleware\VerifikasiAplikasiMember.
 */
return [
    'member_api' => [
        'secret' => env('BABA_MEMBER_API_SECRET'),
        // Selisih waktu maksimal antara pengirim & ERP (detik); juga lama nonce diingat.
        'toleransi_detik' => (int) env('BABA_MEMBER_API_TOLERANSI', 300),
    ],
];
