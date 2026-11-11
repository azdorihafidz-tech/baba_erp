<?php

namespace App\Http\Controllers;

use App\Enums\RoleUser;
use App\Models\CustomRole;
use App\Models\Permission;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    /**
     * Tampilkan semua role (7 role sistem + role buatan) dengan jumlah user
     * dan permissions.
     */
    public function index()
    {
        $roles = Roles::semua()->map(function ($role) {
            $permissionIds = DB::table('role_permissions')
                ->where('role', $role->value)
                ->pluck('permission_id')
                ->toArray();

            return [
                'value'          => $role->value,
                'label'          => $role->label(),
                'user_count'     => User::where('role', $role->value)->count(),
                'permission_ids' => $permissionIds,
                'can_all_branch' => $role->canAccessAllBranches(),
                'is_sistem'      => $role instanceof RoleUser,
            ];
        });

        $permissions = Permission::orderBy('group')->orderBy('name')->get()
            ->groupBy('group');

        $customRoles = CustomRole::orderBy('nama')->get()
            ->map(fn ($r) => $r->setAttribute('user_count', User::where('role', $r->kode)->count()));

        return view('role.index', compact('roles', 'permissions', 'customRoles'));
    }

    /**
     * Update permissions untuk satu role
     */
    public function updatePermissions(Request $request, string $role)
    {
        // Validasi role valid (sistem atau buatan)
        $roleObj = Roles::find($role);
        if (!$roleObj) {
            abort(404, 'Role tidak ditemukan.');
        }

        $request->validate([
            'permission_ids'   => ['nullable', 'array'],
            'permission_ids.*' => ['exists:permissions,id'],
        ]);

        $permissionIds = $request->input('permission_ids', []);

        // Hapus semua permission lama untuk role ini
        DB::table('role_permissions')->where('role', $role)->delete();

        // Insert yang baru
        if (!empty($permissionIds)) {
            $insert = array_map(fn($id) => [
                'role'          => $role,
                'permission_id' => $id,
            ], $permissionIds);
            DB::table('role_permissions')->insert($insert);
        }

        // Hapus cache permissions untuk role ini
        Cache::forget("role_permissions_{$role}");

        return back()->with('success', 'Permissions untuk role <strong>' . e($roleObj->label()) . '</strong> berhasil diperbarui.');
    }

    // =====================================================================
    // Tahap D 4.38 — Role buatan (mis. "Kru")
    // =====================================================================

    public function storeCustom(Request $request)
    {
        $data = $request->validate([
            'nama'               => ['required', 'string', 'max:100'],
            'akses_semua_cabang' => ['nullable', 'boolean'],
            'deskripsi'          => ['nullable', 'string', 'max:255'],
        ]);

        $kode = $this->kodeUnik($data['nama']);
        CustomRole::create([
            'kode'               => $kode,
            'nama'               => $data['nama'],
            'akses_semua_cabang' => $request->boolean('akses_semua_cabang'),
            'deskripsi'          => $data['deskripsi'] ?? null,
        ]);

        return redirect()->route('role.index')
            ->with('success', 'Role <strong>' . e($data['nama']) . '</strong> berhasil ditambahkan. Sekarang centang hak aksesnya di tab role tersebut.');
    }

    public function updateCustom(Request $request, CustomRole $customRole)
    {
        $data = $request->validate([
            'nama'               => ['required', 'string', 'max:100'],
            'akses_semua_cabang' => ['nullable', 'boolean'],
            'deskripsi'          => ['nullable', 'string', 'max:255'],
        ]);

        $customRole->update([
            'nama'               => $data['nama'],
            'akses_semua_cabang' => $request->boolean('akses_semua_cabang'),
            'deskripsi'          => $data['deskripsi'] ?? null,
        ]);
        Cache::forget("role_permissions_{$customRole->kode}");

        return redirect()->route('role.index')->with('success', 'Role <strong>' . e($customRole->nama) . '</strong> berhasil diperbarui.');
    }

    public function destroyCustom(CustomRole $customRole)
    {
        $jumlahUser = User::where('role', $customRole->kode)->count();
        if ($jumlahUser > 0) {
            return redirect()->route('role.index')
                ->with('error', 'Role <strong>' . e($customRole->nama) . "</strong> masih dipakai {$jumlahUser} user. Pindahkan user ke role lain dulu.");
        }

        DB::transaction(function () use ($customRole) {
            DB::table('role_permissions')->where('role', $customRole->kode)->delete();
            $customRole->delete();
        });
        Cache::forget("role_permissions_{$customRole->kode}");

        return redirect()->route('role.index')->with('success', 'Role <strong>' . e($customRole->nama) . '</strong> dihapus.');
    }

    /** Kode role dari nama (mis. "Kru Dapur" → kru_dapur), tidak boleh bentrok role sistem / role lain. */
    private function kodeUnik(string $nama): string
    {
        $dasar = Str::limit(Str::slug($nama, '_'), 40, '') ?: 'role';
        $kode = $dasar;
        $i = 2;
        while (RoleUser::tryFrom($kode) || CustomRole::where('kode', $kode)->exists()) {
            $kode = $dasar . '_' . $i++;
        }

        return $kode;
    }
}
