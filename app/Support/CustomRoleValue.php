<?php

namespace App\Support;

/**
 * Tahap D 4.38 — nilai role buatan (custom_roles) yang perilakunya sama dgn
 * enum App\Enums\RoleUser: punya ->value, label(), canAccessAllBranches().
 * Jadi kode lama yang memakai $user->role->value / ->label() tetap jalan.
 * Perbandingan `=== RoleUser::X` selalu false untuk role buatan (benar:
 * role buatan bukan Owner/Admin Pusat/dst).
 */
final class CustomRoleValue
{
    public function __construct(
        public readonly string $value,
        public readonly string $nama,
        public readonly bool $aksesSemuaCabang = false,
    ) {
    }

    public function label(): string
    {
        return $this->nama;
    }

    public function canAccessAllBranches(): bool
    {
        return $this->aksesSemuaCabang;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
