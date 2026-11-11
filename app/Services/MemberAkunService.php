<?php

namespace App\Services;

use App\Models\MemberAkun;

/**
 * Identitas member dari aplikasi member (header X-Baba-Profil, ikut ditandatangani — lihat
 * VerifikasiAplikasiMember). Disimpan/diperbarui di member_akun setiap permintaan.
 */
class MemberAkunService
{
    /** @param array<string,mixed> $p */
    public function simpan(int $memberId, array $p): MemberAkun
    {
        $akun = MemberAkun::firstOrNew(['member_id' => $memberId]);
        $akun->fill([
            'nama'             => mb_substr(trim((string) ($p['nama'] ?? $akun->nama ?? 'Member')), 0, 150),
            'no_id'            => isset($p['no_id']) ? mb_substr((string) $p['no_id'], 0, 30) : null,
            'no_member'        => isset($p['no_member']) ? mb_substr((string) $p['no_member'], 0, 20) : null,
            'jabatan_id'       => isset($p['jabatan_id']) && is_numeric($p['jabatan_id']) ? (int) $p['jabatan_id'] : null,
            'jabatan'          => isset($p['jabatan']) ? mb_substr((string) $p['jabatan'], 0, 60) : null,
            'parent_member_id' => isset($p['parent_id']) && is_numeric($p['parent_id']) ? (int) $p['parent_id'] : null,
            'cabang'           => isset($p['cabang']) ? mb_substr((string) $p['cabang'], 0, 20) : null,
            'aktif'            => (bool) ($p['aktif'] ?? true),
            'diperbarui_at'    => now(),
        ])->save();

        return $akun;
    }
}
