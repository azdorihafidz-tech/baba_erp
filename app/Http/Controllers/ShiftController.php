<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\Karyawan;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        $authUser = auth()->user();
        $query    = Shift::with('cabang')
            ->withCount('karyawans')
            ->orderBy('cabang_id')
            ->orderBy('nama_shift');

        if (!$authUser->canAccessAllBranches()) {
            $query->where('cabang_id', session('active_cabang_id'));
        } elseif ($request->filled('cabang_id')) {
            $query->where('cabang_id', $request->cabang_id);
        }

        if ($request->filled('status') && $request->status !== 'semua') {
            $query->where('is_active', $request->status === 'aktif');
        }

        if ($request->filled('cari')) {
            $query->where('nama_shift', 'like', '%' . $request->cari . '%');
        }

        $shifts  = $query->paginate(20)->withQueryString();
        $cabangs = Cabang::aktif()->orderBy('nama_cabang')->get();

        return view('shift.index', compact('shifts', 'cabangs'));
    }

    public function create()
    {
        $cabangs = Cabang::aktif()->orderBy('nama_cabang')->get();
        $defaultCabang = session('active_cabang_id');
        return view('shift.create', compact('cabangs', 'defaultCabang'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cabang_id'             => ['required', 'exists:cabangs,id'],
            'nama_shift'            => [
                'required', 'string', 'max:100',
                Rule::unique('shifts')->where('cabang_id', $request->cabang_id),
            ],
            'jam_masuk'             => ['required', 'date_format:H:i'],
            'jam_keluar'            => ['required', 'date_format:H:i'],
            'toleransi_telat_menit' => ['required', 'integer', 'min:0', 'max:120'],
            'is_active'             => ['boolean'],
            'deskripsi'             => ['nullable', 'string', 'max:500'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        Shift::create($data);

        return redirect()->route('shift.index')
            ->with('success', "Shift \"{$data['nama_shift']}\" berhasil ditambahkan.");
    }

    public function edit(Shift $shift)
    {
        $cabangs = Cabang::aktif()->orderBy('nama_cabang')->get();
        return view('shift.edit', compact('shift', 'cabangs'));
    }

    public function update(Request $request, Shift $shift)
    {
        $data = $request->validate([
            'cabang_id'             => ['required', 'exists:cabangs,id'],
            'nama_shift'            => [
                'required', 'string', 'max:100',
                Rule::unique('shifts')->where('cabang_id', $request->cabang_id)->ignore($shift->id),
            ],
            'jam_masuk'             => ['required', 'date_format:H:i'],
            'jam_keluar'            => ['required', 'date_format:H:i'],
            'toleransi_telat_menit' => ['required', 'integer', 'min:0', 'max:120'],
            'is_active'             => ['boolean'],
            'deskripsi'             => ['nullable', 'string', 'max:500'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $shift->update($data);

        return redirect()->route('shift.index')
            ->with('success', "Shift \"{$shift->nama_shift}\" berhasil diperbarui.");
    }

    public function destroy(Shift $shift)
    {
        $jumlahKaryawan = Karyawan::where('shift_id', $shift->id)->where('status', 'aktif')->count();
        if ($jumlahKaryawan > 0) {
            return back()->with('error', "Tidak bisa hapus shift \"{$shift->nama_shift}\" — masih dipakai oleh {$jumlahKaryawan} karyawan aktif. Pindahkan karyawan ke shift lain terlebih dahulu.");
        }

        $nama = $shift->nama_shift;
        $shift->delete();

        return redirect()->route('shift.index')
            ->with('success', "Shift \"{$nama}\" berhasil dihapus.");
    }

    public function toggleAktif(Shift $shift)
    {
        $shift->update(['is_active' => !$shift->is_active]);
        $status = $shift->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "Shift \"{$shift->nama_shift}\" berhasil {$status}.");
    }
}
