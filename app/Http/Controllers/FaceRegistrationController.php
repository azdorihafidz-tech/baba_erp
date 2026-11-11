<?php

namespace App\Http\Controllers;

use App\Models\Karyawan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FaceRegistrationController extends Controller
{
    /** Daftar karyawan + status registrasi wajah */
    public function index(Request $request)
    {
        $karyawans = Karyawan::with('cabang')
            ->aktif()
            ->orderBy('nama_lengkap')
            ->get();

        $terdaftar  = $karyawans->filter(fn($k) => $k->isFaceRegistered())->count();
        $belumDaftar = $karyawans->reject(fn($k) => $k->isFaceRegistered())->count();

        return view('face-registration.index', compact('karyawans', 'terdaftar', 'belumDaftar'));
    }

    /** Form registrasi wajah karyawan */
    public function register(Karyawan $karyawan)
    {
        return view('face-registration.register', compact('karyawan'));
    }

    /** Simpan face descriptor dan foto hasil registrasi */
    public function store(Request $request, Karyawan $karyawan)
    {
        $request->validate([
            'face_descriptors'   => 'required|string', // JSON string array of Float32Array
            'face_photos'        => 'required|array|min:1|max:5',
            'face_photos.*'      => 'required|string', // base64 data URL
        ]);

        // Simpan foto-foto ke storage
        $fotoPaths = [];
        foreach ($request->face_photos as $i => $base64) {
            $path = $this->simpanFotoBase64($base64, "face-registration/{$karyawan->id}/foto_{$i}.jpg");
            if ($path) {
                $fotoPaths[] = $path;
            }
        }

        $karyawan->update([
            'face_data'           => $request->face_descriptors, // raw JSON string (Float32Array[])
            'face_photos'         => $fotoPaths,
            'face_registered_at'  => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Wajah {$karyawan->nama_lengkap} berhasil didaftarkan.",
        ]);
    }

    /** Reset / hapus data wajah karyawan */
    public function reset(Karyawan $karyawan)
    {
        // Hapus foto lama
        if ($karyawan->face_photos) {
            foreach ($karyawan->face_photos as $path) {
                Storage::disk('public')->delete($path);
            }
        }

        $karyawan->update([
            'face_data'          => null,
            'face_photos'        => null,
            'face_registered_at' => null,
        ]);

        return back()->with('success', "Data wajah {$karyawan->nama_lengkap} berhasil direset.");
    }

    /** API: ambil semua face descriptor karyawan di cabang untuk diload di browser */
    public function faceDataCabang(Request $request)
    {
        $cabangId = session('active_cabang_id');

        if (!$cabangId) {
            return response()->json([]);
        }

        $karyawans = Karyawan::where('cabang_id', $cabangId)
            ->aktif()
            ->whereNotNull('face_data')
            ->whereNotNull('face_registered_at')
            ->select(['id', 'nama_lengkap', 'jabatan', 'foto', 'face_data'])
            ->get();

        $result = $karyawans->map(function ($k) {
            return [
                'id'           => $k->id,
                'nama'         => $k->nama_lengkap,
                'jabatan'      => $k->jabatan,
                'foto'         => $k->foto ? asset('storage/' . $k->foto) : null,
                'descriptors'  => json_decode($k->face_data, true),
            ];
        });

        return response()->json($result);
    }

    private function simpanFotoBase64(string $base64, string $path): ?string
    {
        // Parse data URL: data:image/jpeg;base64,xxxxx
        if (!preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
            return null;
        }
        $imageData = substr($base64, strpos($base64, ',') + 1);
        $imageData = base64_decode($imageData);
        if ($imageData === false) {
            return null;
        }

        Storage::disk('public')->put($path, $imageData);
        return $path;
    }
}
