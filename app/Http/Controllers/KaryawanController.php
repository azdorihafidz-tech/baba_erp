<?php

namespace App\Http\Controllers;

use App\Enums\RoleUser;
use App\Http\Requests\KaryawanRequest;
use App\Models\Absensi;
use App\Models\Cabang;
use App\Models\Karyawan;
use App\Models\Penggajian;
use App\Models\Evaluation;
use App\Models\User;
use App\Services\CascadeDeleteService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class KaryawanController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('karyawan.view'), 403);

        $authUser = auth()->user();
        $query = Karyawan::with(['cabang'])->withoutGlobalScope(\App\Models\Scopes\CabangScope::class);

        // Filter cabang: owner/admin_pusat bisa lihat semua
        if (!$authUser->canAccessAllBranches()) {
            $query->where('cabang_id', session('active_cabang_id'));
        } elseif ($request->filled('cabang_id')) {
            $query->where('cabang_id', $request->cabang_id);
        }

        // Filter status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->filled('search')) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->where('nama_lengkap', 'like', "%{$keyword}%")
                  ->orWhere('nik', 'like', "%{$keyword}%")
                  ->orWhere('jabatan', 'like', "%{$keyword}%");
            });
        }

        $karyawans = $query->orderBy('nama_lengkap')->paginate(15)->withQueryString();
        $cabangs   = Cabang::aktif()->orderBy('nama_cabang')->get();

        return view('karyawan.index', compact('karyawans', 'cabangs'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('karyawan.create'), 403);

        $cabangs  = Cabang::aktif()->orderBy('nama_cabang')->get();
        $atasanList = Karyawan::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->where('status', 'aktif')
            ->orderBy('nama_lengkap')
            ->get();

        return view('karyawan.create', compact('cabangs', 'atasanList'));
    }

    public function store(KaryawanRequest $request)
    {
        abort_unless(auth()->user()->can('karyawan.create'), 403);

        // Validasi tambahan untuk opsi buat akun login
        $request->validate([
            'buat_akun_login' => 'nullable|in:1',
            'user_email'      => 'required_if:buat_akun_login,1|nullable|email|unique:users,email',
            'user_role'       => ['required_if:buat_akun_login,1', 'nullable',
                Rule::in(\App\Support\Roles::values(false))],
        ]);

        $data = $request->validated();

        // Auto-generate NIK jika kosong
        if (empty($data['nik'])) {
            $lastNik = Karyawan::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
                ->where('nik', 'like', 'KRY-%')
                ->orderByDesc('nik')
                ->value('nik');
            $nextNum = $lastNik ? (intval(substr($lastNik, 4)) + 1) : 1;
            $data['nik'] = 'KRY-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
        }

        // Upload foto
        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('karyawan', 'public');
        }

        $data['status'] = $data['status'] ?? 'aktif';
        $buatAkun = $request->boolean('buat_akun_login');

        return DB::transaction(function () use ($request, $data, $buatAkun) {
            $karyawan = Karyawan::create($data);

            if ($buatAkun) {
                $user = User::create([
                    'name'      => $karyawan->nama_lengkap,
                    'email'     => $request->user_email,
                    'password'  => Hash::make('password123'),
                    'role'      => $request->user_role,
                    'is_active' => true,
                ]);
                $karyawan->update(['user_id' => $user->id]);
                if ($karyawan->cabang_id) {
                    $user->cabangs()->attach($karyawan->cabang_id, ['is_default' => true]);
                }
                return redirect()->route('karyawan.index')
                    ->with('success', "Karyawan {$karyawan->nama_lengkap} berhasil ditambahkan. Akun login <strong>{$request->user_email}</strong> telah dibuat dengan password default: <code>password123</code>.");
            }

            return redirect()->route('karyawan.index')
                ->with('success', "Karyawan {$karyawan->nama_lengkap} berhasil ditambahkan.");
        });
    }

    public function show(Karyawan $karyawan)
    {
        abort_unless(auth()->user()->can('karyawan.view'), 403);

        $karyawan->load(['cabang', 'atasan']);

        // Rekap absensi bulan ini
        $bulanIni = Carbon::now();
        $absensiRekap = Absensi::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->where('karyawan_id', $karyawan->id)
            ->whereYear('tanggal', $bulanIni->year)
            ->whereMonth('tanggal', $bulanIni->month)
            ->get();

        $absensiSummary = [
            'hadir'       => $absensiRekap->where('status', 'hadir')->count(),
            'izin'        => $absensiRekap->where('status', 'izin')->count(),
            'sakit'       => $absensiRekap->where('status', 'sakit')->count(),
            'alpha'       => $absensiRekap->where('status', 'alpha')->count(),
            'cuti'        => $absensiRekap->where('status', 'cuti')->count(),
            'jam_lembur'  => $absensiRekap->sum('jam_lembur'),
        ];

        // Penggajian terakhir 3 bulan
        $penggajians = Penggajian::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->where('karyawan_id', $karyawan->id)
            ->orderByDesc('periode')
            ->take(3)
            ->get();

        // Evaluasi terakhir
        $evaluasiTerakhir = Evaluation::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->where('karyawan_id', $karyawan->id)
            ->orderByDesc('created_at')
            ->with(['period', 'summaries.aspect'])
            ->first();

        return view('karyawan.show', compact('karyawan', 'absensiSummary', 'penggajians', 'evaluasiTerakhir'));
    }

    public function edit(Karyawan $karyawan)
    {
        abort_unless(auth()->user()->can('karyawan.edit'), 403);

        $karyawan->load('user');

        $cabangs  = Cabang::aktif()->orderBy('nama_cabang')->get();
        $atasanList = Karyawan::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->where('status', 'aktif')
            ->where('id', '!=', $karyawan->id)
            ->orderBy('nama_lengkap')
            ->get();

        // Shift aktif untuk cabang karyawan ini
        $shifts = \App\Models\Shift::where('cabang_id', $karyawan->cabang_id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        return view('karyawan.edit', compact('karyawan', 'cabangs', 'atasanList', 'shifts'));
    }

    public function update(KaryawanRequest $request, Karyawan $karyawan)
    {
        abort_unless(auth()->user()->can('karyawan.edit'), 403);

        $hasUser = !is_null($karyawan->user_id);

        // Validasi tambahan untuk akun login
        $emailRules = ['nullable', 'email'];
        $emailRules[] = $hasUser
            ? Rule::unique('users', 'email')->ignore($karyawan->user_id)
            : 'unique:users,email';
        if (!$hasUser) {
            $emailRules[] = 'required_if:buat_akun_login,1';
        }

        $validRoles = \App\Support\Roles::values(false); // Tahap D 4.38: + role buatan
        $roleRules = ['nullable', Rule::in($validRoles)];
        if (!$hasUser) {
            $roleRules[] = 'required_if:buat_akun_login,1';
        }

        $request->validate([
            'buat_akun_login' => 'nullable|in:1',
            'user_email'      => $emailRules,
            'user_role'       => $roleRules,
        ]);

        $data = $request->validated();

        // Upload foto baru
        if ($request->hasFile('foto')) {
            if ($karyawan->foto) {
                Storage::disk('public')->delete($karyawan->foto);
            }
            $data['foto'] = $request->file('foto')->store('karyawan', 'public');
        } else {
            unset($data['foto']);
        }

        return DB::transaction(function () use ($request, $karyawan, $data, $hasUser) {
            $karyawan->update($data);

            if (!$hasUser && $request->boolean('buat_akun_login')) {
                // Buat akun login baru
                $user = User::create([
                    'name'      => $karyawan->nama_lengkap,
                    'email'     => $request->user_email,
                    'password'  => Hash::make('password123'),
                    'role'      => $request->user_role,
                    'is_active' => true,
                ]);
                $karyawan->update(['user_id' => $user->id]);
                if ($karyawan->cabang_id) {
                    $user->cabangs()->attach($karyawan->cabang_id, ['is_default' => true]);
                }
            } elseif ($hasUser) {
                // Update akun login yang sudah ada
                $existingUser = User::find($karyawan->user_id);
                if ($existingUser) {
                    $updateData = [];
                    if ($request->filled('user_email')) $updateData['email'] = $request->user_email;
                    if ($request->filled('user_role')) $updateData['role'] = $request->user_role;
                    if (!empty($updateData)) $existingUser->update($updateData);
                }
            }

            return redirect()->route('karyawan.show', $karyawan)
                ->with('success', "Data karyawan {$karyawan->nama_lengkap} berhasil diperbarui.");
        });
    }

    public function destroy(Karyawan $karyawan, CascadeDeleteService $cascadeService)
    {
        abort_unless(auth()->user()->can('karyawan.delete'), 403);

        try {
            $nama    = $karyawan->nama_lengkap;
            $deleted = $cascadeService->deleteKaryawanCascade($karyawan);

            $labels = ['absensi' => 'Absensi', 'face_attendance' => 'Absensi Wajah', 'penggajian' => 'Penggajian', 'karyawan' => 'Karyawan'];
            $ringkasan = collect($deleted)->map(fn($c, $k) => "{$c} " . ($labels[$k] ?? $k))->filter()->join(', ');

            return redirect()->route('karyawan.index')
                ->with('success', "Karyawan <strong>{$nama}</strong> beserta data terkait berhasil dihapus. ({$ringkasan})");
        } catch (\Exception $e) {
            return back()->with('error', "Gagal menghapus karyawan: " . $e->getMessage());
        }
    }
}
