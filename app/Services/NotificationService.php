<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class NotificationService
{
    /**
     * Kirim notifikasi ke sekumpulan user
     */
    public static function send(iterable $users, object $notification): void
    {
        foreach ($users as $user) {
            try {
                $user->notify($notification);
            } catch (\Exception $e) {
                // Jangan biarkan error notifikasi menghentikan proses bisnis
                \Log::warning('Gagal kirim notifikasi ke user ' . $user->id . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Get Owner dan Admin Pusat (akses semua cabang)
     */
    public static function getOwnerAndAdminPusat(): Collection
    {
        return User::whereIn('role', ['owner', 'admin_pusat'])->get();
    }

    /**
     * Get Admin Gudang Pusat
     */
    public static function getAdminGudang(): Collection
    {
        return User::where('role', 'admin_gudang')
            ->whereHas('cabangs', fn($q) => $q->where('tipe', 'gudang_pusat'))
            ->get();
    }

    /**
     * Get Manajer Cabang untuk cabang tertentu
     */
    public static function getManajerCabang(int $cabangId): Collection
    {
        return User::where('role', 'manajer_cabang')
            ->whereHas('cabangs', fn($q) => $q->where('cabangs.id', $cabangId))
            ->get();
    }

    /**
     * Get semua user di cabang tertentu
     */
    public static function getUsersByCabang(int $cabangId): Collection
    {
        return User::whereHas('cabangs', fn($q) => $q->where('cabangs.id', $cabangId))->get();
    }

    /**
     * Get Owner + Admin Pusat + Manajer Cabang untuk cabang tertentu
     */
    public static function getManagement(int $cabangId): Collection
    {
        $owners  = static::getOwnerAndAdminPusat();
        $manajer = static::getManajerCabang($cabangId);
        return $owners->merge($manajer)->unique('id');
    }

    /**
     * Get Owner + Admin Pusat + Admin Gudang + Manajer Cabang untuk cabang tertentu
     */
    public static function getManagementAndGudang(int $cabangId): Collection
    {
        $management = static::getManagement($cabangId);
        $gudang     = static::getAdminGudang();
        return $management->merge($gudang)->unique('id');
    }

    /**
     * Get user berdasarkan role di cabang tertentu
     */
    public static function getUsersByRoleAndCabang(array $roles, int $cabangId): Collection
    {
        return User::whereIn('role', $roles)
            ->whereHas('cabangs', fn($q) => $q->where('cabangs.id', $cabangId))
            ->get();
    }
}
