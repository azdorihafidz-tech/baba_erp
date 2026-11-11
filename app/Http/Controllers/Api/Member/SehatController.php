<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Support\Branding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Cek sambungan aplikasi member → ERP (dipanggil server aplikasi member, lihat VerifikasiAplikasiMember). */
class SehatController extends Controller
{
    public function __invoke(Request $request, Branding $brand): JsonResponse
    {
        return response()->json([
            'ok'        => true,
            'erp'       => $brand->namaSingkat(),
            'waktu'     => now()->toIso8601String(),
            'member_id' => $request->attributes->get('member_id'),
        ]);
    }
}
