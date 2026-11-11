<?php

namespace App\Support;

use App\Enums\RoleUser;
use App\Models\CustomRole;
use Illuminate\Support\Collection;

/**
 * Tahap D 4.38 — daftar semua role: 7 role sistem (enum) + role buatan
 * (custom_roles). Tiap elemen punya ->value dan ->label().
 */
class Roles
{
    public static function custom(string $kode): ?CustomRoleValue
    {
        $r = CustomRole::where('kode', $kode)->first();

        return $r ? new CustomRoleValue($r->kode, $r->nama, (bool) $r->akses_semua_cabang) : null;
    }

    /** @return Collection<int, RoleUser|CustomRoleValue> */
    public static function semua(bool $termasukOwner = true): Collection
    {
        $sistem = collect(RoleUser::cases())
            ->when(! $termasukOwner, fn ($c) => $c->reject(fn ($r) => $r === RoleUser::Owner));

        $buatan = CustomRole::orderBy('nama')->get()
            ->map(fn ($r) => new CustomRoleValue($r->kode, $r->nama, (bool) $r->akses_semua_cabang));

        return $sistem->concat($buatan)->values();
    }

    /** @return array<int, string> */
    public static function values(bool $termasukOwner = true): array
    {
        return self::semua($termasukOwner)->map(fn ($r) => $r->value)->all();
    }

    public static function find(string $value): RoleUser|CustomRoleValue|null
    {
        return RoleUser::tryFrom($value) ?? self::custom($value);
    }

    public static function isSistem(string $value): bool
    {
        return RoleUser::tryFrom($value) !== null;
    }
}
