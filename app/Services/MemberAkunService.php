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
        // Tahap 2c: upline terdekat yang benar-benar akun (kursi kosong dilewati aplikasi member) — ikut disimpan
        $uplineId = null;
        if (\Illuminate\Support\Facades\Schema::hasColumn('member_akun', 'upline_member_id') && is_array($p['upline'] ?? null)
            && is_numeric($p['upline']['id'] ?? null) && (int) $p['upline']['id'] !== $memberId) {
            $uplineId = (int) $p['upline']['id'];
            $this->isi(MemberAkun::firstOrNew(['member_id' => $uplineId]), $p['upline'])->save();
        }
        $akun = $this->isi(MemberAkun::firstOrNew(['member_id' => $memberId]), $p);
        if (\Illuminate\Support\Facades\Schema::hasColumn('member_akun', 'upline_member_id')) {
            $akun->upline_member_id = $uplineId;
        }
        $akun->save();

        return $akun;
    }

    /** @param array<string,mixed> $p */
    private function isi(MemberAkun $akun, array $p): MemberAkun
    {
        return $akun->fill([
            'nama'             => mb_substr(trim((string) ($p['nama'] ?? $akun->nama ?? 'Member')), 0, 150),
            'no_id'            => isset($p['no_id']) ? mb_substr((string) $p['no_id'], 0, 30) : null,
            'no_member'        => isset($p['no_member']) ? mb_substr((string) $p['no_member'], 0, 20) : null,
            'jabatan_id'       => isset($p['jabatan_id']) && is_numeric($p['jabatan_id']) ? (int) $p['jabatan_id'] : null,
            'jabatan'          => isset($p['jabatan']) ? mb_substr((string) $p['jabatan'], 0, 60) : null,
            'parent_member_id' => isset($p['parent_id']) && is_numeric($p['parent_id']) ? (int) $p['parent_id'] : null,
            'cabang'           => isset($p['cabang']) ? mb_substr((string) $p['cabang'], 0, 20) : null,
            'aktif'            => (bool) ($p['aktif'] ?? true),
            'diperbarui_at'    => now(),
        ]);
    }
}
