<?php

namespace App\Casts;

use App\Enums\RoleUser;
use App\Support\CustomRoleValue;
use App\Support\Roles;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * Tahap D 4.38 — users.role: role sistem → enum RoleUser (perilaku lama),
 * role buatan → CustomRoleValue. Kode tidak dikenal → null (sama seperti
 * enum lama yang gagal di-resolve: user tanpa hak akses apa pun).
 */
class RoleCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): RoleUser|CustomRoleValue|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return RoleUser::tryFrom((string) $value) ?? Roles::custom((string) $value);
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value instanceof RoleUser || $value instanceof CustomRoleValue) {
            return $value->value;
        }

        return $value === null ? null : (string) $value;
    }
}
