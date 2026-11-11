<?php

namespace App\Models;

use App\Enums\RoleUser;
use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected array $auditedAttributes = [
        'name', 'email', 'role', 'telepon', 'is_active',
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'telepon',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => \App\Casts\RoleCast::class, // Tahap D 4.38: enum sistem + role buatan
            'is_active' => 'boolean',
        ];
    }

    // Relasi ke cabang melalui pivot
    public function cabangs()
    {
        return $this->belongsToMany(Cabang::class, 'cabang_user')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function defaultCabang()
    {
        return $this->cabangs()->wherePivot('is_default', true)->first();
    }

    public function defaultCabangId(): ?int
    {
        return $this->defaultCabang()?->id;
    }

    public function canAccessAllBranches(): bool
    {
        return $this->role?->canAccessAllBranches() ?? false;
    }

    /**
     * Cek apakah user memiliki permission tertentu.
     * Owner selalu punya semua permission.
     * Cek berdasarkan tabel role_permissions.
     *
     * Contoh: $user->hasPermission('stok.adjustment')
     */
    public function hasPermission(string $permission): bool
    {
        // Owner selalu bisa segalanya
        if ($this->role === RoleUser::Owner) {
            return true;
        }

        $roleValue = $this->role?->value;
        if (!$roleValue) {
            return false;
        }

        // Cache per role agar tidak query berkali-kali per request
        $permissions = Cache::remember("role_permissions_{$roleValue}", 300, function () use ($roleValue) {
            return DB::table('role_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('role_permissions.role', $roleValue)
                ->pluck('permissions.name')
                ->toArray();
        });

        return in_array($permission, $permissions);
    }

    /**
     * Ambil semua permission name milik user ini.
     */
    public function getPermissions(): array
    {
        if ($this->role === RoleUser::Owner) {
            return Permission::pluck('name')->toArray();
        }

        $roleValue = $this->role?->value;
        if (!$roleValue) {
            return [];
        }

        return Cache::remember("role_permissions_{$roleValue}", 300, function () use ($roleValue) {
            return DB::table('role_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('role_permissions.role', $roleValue)
                ->pluck('permissions.name')
                ->toArray();
        });
    }

    public function karyawan()
    {
        return $this->hasOne(Karyawan::class);
    }
}
