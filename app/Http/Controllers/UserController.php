<?php

namespace App\Http\Controllers;

use App\Enums\RoleUser;
use App\Http\Requests\UserRequest;
use App\Models\Cabang;
use App\Models\User;
use App\Services\CascadeDeleteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $authUser = $request->user();
        $query = User::with('cabangs');

        // Role tanpa akses semua cabang hanya lihat user di cabangnya sendiri
        if (! $authUser->canAccessAllBranches()) {
            $cabangIds = $authUser->cabangs()->pluck('cabangs.id');
            $query->whereHas('cabangs', fn($q) => $q->whereIn('cabangs.id', $cabangIds));
        }

        // Filter role
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // Filter cabang
        if ($request->filled('cabang_id')) {
            $query->whereHas('cabangs', fn($q) =>
                $q->where('cabangs.id', $request->cabang_id)
            );
        }

        // Filter status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'aktif');
        }

        // Search
        if ($request->filled('search')) {
            $keyword = $request->search;
            $query->where(fn($q) =>
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('email', 'like', "%{$keyword}%")
                  ->orWhere('telepon', 'like', "%{$keyword}%")
            );
        }

        $users = $query->orderBy('name')->paginate(15)->withQueryString();

        $cabangs = $authUser->canAccessAllBranches()
            ? Cabang::aktif()->orderBy('nama_cabang')->get()
            : $authUser->cabangs()->aktif()->get();

        $roles = \App\Support\Roles::semua()->all(); // Tahap D 4.38: + role buatan

        $stats = [
            'total'  => User::count(),
            'aktif'  => User::where('is_active', true)->count(),
            'owner'  => User::where('role', 'owner')->count() + User::where('role', 'admin_pusat')->count(),
            'staff'  => User::whereIn('role', ['kasir', 'operator_produksi'])->count(),
        ];

        return view('user.index', compact('users', 'cabangs', 'roles', 'stats'));
    }

    public function create(Request $request)
    {
        $authUser = $request->user();
        $roles = \App\Support\Roles::semua($authUser->role === RoleUser::Owner)->all(); // 4.43: Owner hanya oleh Owner
        $cabangs = $authUser->canAccessAllBranches()
            ? Cabang::aktif()->orderBy('nama_cabang')->get()
            : $authUser->cabangs()->aktif()->get();

        return view('user.create', compact('roles', 'cabangs'));
    }

    public function store(UserRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => $data['role'],
            'telepon'  => $data['telepon'] ?? null,
            'is_active'=> true,
            'email_verified_at' => now(),
        ]);

        // Assign cabang
        if (!empty($data['cabang_ids'])) {
            $defaultId = $data['default_cabang_id'] ?? $data['cabang_ids'][0];
            $pivot = [];
            foreach ($data['cabang_ids'] as $cabangId) {
                $pivot[$cabangId] = ['is_default' => ($cabangId == $defaultId)];
            }
            $user->cabangs()->sync($pivot);
        }

        return redirect()
            ->route('user.index')
            ->with('success', "User <strong>{$user->name}</strong> berhasil ditambahkan.");
    }

    public function show(User $user)
    {
        $this->pastikanBolehKelola($user);
        $user->load('cabangs', 'karyawan');
        return view('user.show', compact('user'));
    }

    public function edit(Request $request, User $user)
    {
        $authUser = $request->user();

        $this->pastikanBolehKelola($user);

        $roles   = \App\Support\Roles::semua($authUser->role === RoleUser::Owner)->all(); // 4.43: Owner hanya oleh Owner
        $cabangs = $authUser->canAccessAllBranches()
            ? Cabang::aktif()->orderBy('nama_cabang')->get()
            : $authUser->cabangs()->aktif()->get();

        $userCabangIds   = $user->cabangs()->pluck('cabangs.id')->toArray();
        $defaultCabangId = $user->defaultCabangId();

        return view('user.edit', compact('user', 'roles', 'cabangs', 'userCabangIds', 'defaultCabangId'));
    }

    public function update(UserRequest $request, User $user)
    {
        $this->pastikanBolehKelola($user);
        $data = $request->validated();

        $updateData = [
            'name'    => $data['name'],
            'email'   => $data['email'],
            'role'    => $data['role'],
            'telepon' => $data['telepon'] ?? null,
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user->update($updateData);

        // Sync cabang
        if (isset($data['cabang_ids'])) {
            $defaultId = $data['default_cabang_id'] ?? ($data['cabang_ids'][0] ?? null);
            $pivot = [];
            foreach ($data['cabang_ids'] as $cabangId) {
                $pivot[$cabangId] = ['is_default' => ($cabangId == $defaultId)];
            }
            $user->cabangs()->sync($pivot);
        } else {
            $user->cabangs()->detach();
        }

        return redirect()
            ->route('user.index')
            ->with('success', "User <strong>{$user->name}</strong> berhasil diperbarui.");
    }

    public function destroy(User $user, CascadeDeleteService $cascadeService)
    {
        $this->pastikanBolehKelola($user);

        // Safety: tidak bisa hapus diri sendiri
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak dapat menghapus akun Anda sendiri.');
        }

        // Safety: tidak bisa hapus satu-satunya Owner
        if ($user->role === RoleUser::Owner && User::where('role', RoleUser::Owner->value)->count() <= 1) {
            return back()->with('error', 'Tidak dapat menghapus satu-satunya Owner.');
        }

        try {
            $nama    = $user->name;
            $deleted = $cascadeService->deleteUserCascade($user);

            $labels = ['user' => 'User', 'cabang_assignment' => 'Penugasan Cabang', 'session' => 'Sesi Aktif'];
            $ringkasan = collect($deleted)->map(fn($c, $k) => "{$c} " . ($labels[$k] ?? $k))->filter()->join(', ');

            return redirect()->route('user.index')
                ->with('success', "User <strong>{$nama}</strong> berhasil dihapus. ({$ringkasan})");
        } catch (\Exception $e) {
            return back()->with('error', "Gagal menghapus user: " . $e->getMessage());
        }
    }

    /**
     * Toggle aktif / nonaktif
     */
    public function toggleAktif(User $user)
    {
        $this->pastikanBolehKelola($user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak dapat menonaktifkan akun sendiri.');
        }

        $user->update(['is_active' => !$user->is_active]);
        $status = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "User <strong>{$user->name}</strong> berhasil {$status}.");
    }

    /**
     * Reset password user oleh admin
     */
    public function resetPassword(Request $request, User $user)
    {
        $this->pastikanBolehKelola($user);

        $request->validate([
            'new_password' => ['required', 'confirmed', 'min:8'],
        ], [
            'new_password.required'  => 'Password baru wajib diisi.',
            'new_password.confirmed' => 'Konfirmasi password tidak cocok.',
            'new_password.min'       => 'Password minimal 8 karakter.',
        ]);

        $user->update(['password' => Hash::make($request->new_password)]);

        return back()->with('success', "Password <strong>{$user->name}</strong> berhasil direset.");
    }

    /**
     * 4.43 — akun Owner hanya boleh diurus Owner; role tanpa akses semua
     * cabang hanya boleh mengurus user yang berbagi cabang dengannya.
     */
    private function pastikanBolehKelola(User $user): void
    {
        $authUser = auth()->user();

        if ($user->role === RoleUser::Owner && $authUser->role !== RoleUser::Owner) {
            abort(403, 'Hanya Owner yang boleh mengubah akun Owner.');
        }

        if (! $authUser->canAccessAllBranches()) {
            $milik = $authUser->cabangs()->pluck('cabangs.id')->all();
            $target = $user->cabangs()->pluck('cabangs.id')->all();
            if (! array_intersect($milik, $target)) {
                abort(403, 'Anda tidak berhak mengelola user ini.');
            }
        }
    }
}
