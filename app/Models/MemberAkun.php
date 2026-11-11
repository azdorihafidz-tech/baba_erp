<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Salinan identitas akun aplikasi member (sumber kebenaran tetap di aplikasi member). */
class MemberAkun extends Model
{
    protected $table = 'member_akun';

    protected $fillable = ['member_id', 'nama', 'no_id', 'no_member', 'jabatan_id', 'jabatan', 'parent_member_id', 'cabang', 'aktif', 'diperbarui_at'];

    protected function casts(): array
    {
        return ['member_id' => 'integer', 'jabatan_id' => 'integer', 'parent_member_id' => 'integer', 'aktif' => 'boolean', 'diperbarui_at' => 'datetime'];
    }

    /** "No ID · Nama" untuk tampilan. */
    public function getLabelAttribute(): string
    {
        return trim(($this->no_id ? $this->no_id . ' · ' : '') . $this->nama . ($this->no_member ? " ({$this->no_member})" : ''));
    }
}
