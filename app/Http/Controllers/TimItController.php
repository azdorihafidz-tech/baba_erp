<?php

namespace App\Http\Controllers;

use App\Enums\RoleUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Akun Tim IT / Teknisi (porting Dimsum 4.53 — _rancangan/RANCANGAN-KAMUS-TIMIT-PAKET.md) — hanya Teknisi
 * yang membuka (route role:teknisi). Akun ini tidak terlihat dari Manajemen User.
 */
class TimItController extends Controller
{
    private function aturanSandi(): Password
    {
        return Password::min(12)->letters()->numbers();
    }

    public function index()
    {
        $tim = User::where('role', RoleUser::Teknisi->value)->orderBy('name')->get();
        $loginTerakhir = DB::table('activity_log')->where('log_name', 'TimIT')->where('description', 'Login')
            ->whereIn('causer_id', $tim->pluck('id'))->groupBy('causer_id')
            ->selectRaw('causer_id, MAX(created_at) as terakhir')->pluck('terakhir', 'causer_id');

        return view('tim-it.index', compact('tim', 'loginTerakhir'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', $this->aturanSandi()],
        ], ['password.min' => 'Kata sandi Tim IT minimal 12 karakter.', 'email.unique' => 'Email sudah dipakai akun lain.']);

        $u = User::create([
            'name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password']),
            'role' => RoleUser::Teknisi->value, 'is_active' => true,
        ]);
        activity('TimIT')->performedOn($u)->causedBy(auth()->user())->log("Menambah akun Tim IT {$u->name} ({$u->email})");

        return back()->with('success', "Akun Tim IT {$u->name} dibuat.");
    }

    private function anggota(User $user): User
    {
        abort_unless($user->isTeknisi(), 404);

        return $user;
    }

    public function toggle(User $user)
    {
        $this->anggota($user);
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak bisa menonaktifkan akun sendiri.');
        }
        if ($user->is_active && User::where('role', RoleUser::Teknisi->value)->where('is_active', true)->count() <= 1) {
            return back()->with('error', 'Harus ada minimal satu akun Tim IT yang aktif.');
        }
        $user->update(['is_active' => ! $user->is_active]);
        activity('TimIT')->performedOn($user)->causedBy(auth()->user())
            ->log(($user->is_active ? 'Mengaktifkan' : 'Menonaktifkan') . " akun Tim IT {$user->name}");

        return back()->with('success', "Akun {$user->name} " . ($user->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->anggota($user);
        $request->validate(['password' => ['required', 'confirmed', $this->aturanSandi()]], ['password.min' => 'Kata sandi Tim IT minimal 12 karakter.']);
        $user->update(['password' => Hash::make($request->input('password'))]);
        activity('TimIT')->performedOn($user)->causedBy(auth()->user())->log("Mengganti kata sandi akun Tim IT {$user->name}");

        return back()->with('success', "Kata sandi {$user->name} diganti.");
    }
}
