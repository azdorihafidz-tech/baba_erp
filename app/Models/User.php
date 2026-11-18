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

    public function isTeknisi(): bool
    {
        return $this->role === RoleUser::Teknisi;
    }

    /** Owner pelanggan atau Tim IT — semua yang dulu "khusus Owner". Hak baru "khusus Owner" WAJIB memakai ini. */
    public function aksesPenuh(): bool
    {
        return $this->role === RoleUser::Owner || $this->role === RoleUser::Teknisi;
    }

    /** User milik pelanggan (bukan Tim IT) — dipakai di daftar/pilihan user & penerima notifikasi. */
    public function scopePelanggan($query)
    {
        return $query->where('users.role', '<>', RoleUser::Teknisi->value);
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
        // Paket: izin milik modul yang tidak termasuk paket selalu ditolak (juga Owner & Tim IT)
        if (! \App\Support\Paket::izinDiizinkan($permission)) {
            return false;
        }

        // Owner & Tim IT selalu bisa segalanya (di dalam paket)
        if ($this->aksesPenuh()) {
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
        if ($this->aksesPenuh()) {
            return Permission::pluck('name')->filter(fn ($n) => \App\Support\Paket::izinDiizinkan($n))->values()->toArray();
        }

        $roleValue = $this->role?->value;
        if (!$roleValue) {
            return [];
        }

        $izin = Cache::remember("role_permissions_{$roleValue}", 300, function () use ($roleValue) {
            return DB::table('role_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('role_permissions.role', $roleValue)
                ->pluck('permissions.name')
                ->toArray();
        });

        return array_values(array_filter($izin, fn ($n) => \App\Support\Paket::izinDiizinkan($n)));
    }

    public function karyawan()
    {
        return $this->hasOne(Karyawan::class);
    }
}
