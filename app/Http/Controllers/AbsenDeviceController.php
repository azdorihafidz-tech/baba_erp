<?php

namespace App\Http\Controllers;

use App\Models\AbsenDevice;
use App\Models\Cabang;
use Illuminate\Http\Request;

class AbsenDeviceController extends Controller
{
    public function index()
    {
        $cabangId = session('cabang_id');
        $cabang   = Cabang::find($cabangId);

        // Owner bisa lihat semua, manajer hanya cabang sendiri
        if (auth()->user()->canAccessAllBranches()) {
            $devices = AbsenDevice::with('cabang')->orderBy('cabang_id')->orderBy('device_name')->get();
        } else {
            $devices = AbsenDevice::with('cabang')
                ->where('cabang_id', $cabangId)
                ->orderBy('device_name')
                ->get();
        }

        $cabangs = Cabang::aktif()->orderBy('nama_cabang')->get();

        return view('absen-device.index', compact('devices', 'cabangs', 'cabang'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'cabang_id'   => 'required|exists:cabangs,id',
            'device_name' => 'required|string|max:100',
        ]);

        $device = AbsenDevice::create([
            'cabang_id'    => $request->cabang_id,
            'device_name'  => $request->device_name,
            'device_token' => AbsenDevice::generateToken(),
            'is_active'    => true,
            'registered_at' => now(),
        ]);

        return back()->with('success', "Device \"{$device->device_name}\" berhasil didaftarkan. Token: {$device->device_token}");
    }

    public function toggleAktif(AbsenDevice $device)
    {
        $device->update(['is_active' => !$device->is_active]);
        $status = $device->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "Device \"{$device->device_name}\" berhasil {$status}.");
    }

    public function resetToken(AbsenDevice $device)
    {
        $token = AbsenDevice::generateToken();
        $device->update(['device_token' => $token]);
        return back()->with('success', "Token device \"{$device->device_name}\" berhasil direset. Token baru: {$token}");
    }

    public function destroy(AbsenDevice $device)
    {
        $nama = $device->device_name;
        $device->delete();
        return back()->with('success', "Device \"{$nama}\" berhasil dihapus.");
    }

    /** Generate URL scan untuk device */
    public function scanUrl(AbsenDevice $device)
    {
        $url = route('face-attendance.scan', ['token' => $device->device_token]);
        return response()->json(['url' => $url, 'token' => $device->device_token]);
    }
}
