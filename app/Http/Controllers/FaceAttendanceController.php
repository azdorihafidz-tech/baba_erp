<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Cabang;
use App\Models\FaceAttendance;
use App\Models\Karyawan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FaceAttendanceController extends Controller
{
    /** Halaman scan absensi — login biasa, cabang dari session */
    public function scan(Request $request)
    {
        $cabangId = session('active_cabang_id');
        $cabang   = $cabangId ? Cabang::find($cabangId) : null;

        // Owner/Admin Pusat belum pilih cabang → arahkan pilih cabang dulu
        if (!$cabang) {
            return redirect()->route('dashboard')
                ->with('error', 'Pilih cabang aktif terlebih dahulu sebelum membuka halaman scan absensi.');
        }

        return view('face-attendance.scan', compact('cabang'));
    }

    /** API: proses absensi wajah dari browser */
    public function proses(Request $request)
    {
        $request->validate([
            'karyawan_id'        => 'nullable|integer',
            'confidence_score'   => 'nullable|numeric',
            'foto_absen'         => 'nullable|string',
            'latitude'           => 'nullable|numeric|between:-90,90',
            'longitude'          => 'nullable|numeric|between:-180,180',
            'is_liveness_passed' => 'nullable|boolean',
            'tipe_absensi'       => 'nullable|string|in:masuk,keluar,lembur_masuk,lembur_keluar',
        ]);

        $cabangId = session('active_cabang_id');
        $cabang   = $cabangId ? Cabang::find($cabangId) : null;

        if (!$cabang) {
            return response()->json(['success' => false, 'message' => 'Cabang tidak ditemukan.'], 400);
        }

        // Rate limiting: max 5 percobaan per IP per menit
        $cacheKey = 'face_attempts_' . $request->ip();
        $attempts = Cache::get($cacheKey, 0);
        if ($attempts >= 5) {
            return response()->json([
                'success' => false,
                'status'  => 'rate_limited',
                'message' => 'Terlalu banyak percobaan. Tunggu sebentar.',
            ], 429);
        }

        $lat = $request->latitude  !== null ? (float) $request->latitude  : null;
        $lng = $request->longitude !== null ? (float) $request->longitude : null;

        // ── 1. Validasi Liveness ──────────────────────────────────────────
        // Hanya ditolak jika is_liveness_passed secara eksplisit dikirim sebagai false
        if ($request->has('is_liveness_passed') && !$request->boolean('is_liveness_passed')) {
            // Coba ambil karyawan jika dikirim (bisa null jika wajah tak dikenali saat liveness)
            $karyawanId = null;
            if ($request->karyawan_id && (int) $request->karyawan_id > 0) {
                $karyawanId = Karyawan::where('id', $request->karyawan_id)
                    ->where('cabang_id', $cabang->id)
                    ->aktif()
                    ->value('id');
            }
            $this->logPercobaan($cabang->id, $karyawanId, 'clock_in', 'liveness_gagal', null,
                $request->confidence_score, $lat, $lng, null, false);
            Cache::put($cacheKey, $attempts + 1, 60);
            return response()->json([
                'success' => false,
                'status'  => 'liveness_gagal',
                'message' => '❌ Liveness check gagal. Gelengkan kepala ke kiri atau kanan 1 kali dengan jelas.',
            ]);
        }

        // ── 2. Validasi Karyawan ──────────────────────────────────────────
        $karyawan = Karyawan::where('id', $request->karyawan_id)
            ->where('cabang_id', $cabang->id)
            ->aktif()
            ->first();

        if (!$karyawan) {
            $this->logPercobaan($cabang->id, null, 'clock_in', 'tidak_dikenali', null,
                $request->confidence_score, $lat, $lng, null, null);
            Cache::put($cacheKey, $attempts + 1, 60);
            return response()->json(['success' => false, 'status' => 'tidak_dikenali', 'message' => 'Wajah tidak dikenali.']);
        }

        // ── 3. Tentukan tipe ──────────────────────────────────────────────
        $tipeMap = [
            'masuk'         => 'clock_in',
            'keluar'        => 'clock_out',
            'lembur_masuk'  => 'lembur_masuk',
            'lembur_keluar' => 'lembur_keluar',
        ];
        if ($request->filled('tipe_absensi') && isset($tipeMap[$request->tipe_absensi])) {
            $tipe = $tipeMap[$request->tipe_absensi];
        } else {
            $sudahClockIn = FaceAttendance::where('karyawan_id', $karyawan->id)
                ->where('cabang_id', $cabang->id)
                ->whereDate('waktu', today())
                ->where('tipe', 'clock_in')
                ->where('status', 'valid')
                ->exists();
            $tipe = $sudahClockIn ? 'clock_out' : 'clock_in';
        }

        // ── 4. Hitung jarak GPS (non-blocking, untuk log) ─────────────────
        $jarak = null;
        if ($lat !== null && $lng !== null) {
            $jarak = (int) round($cabang->hitungJarak($lat, $lng));
        }

        // ── 5. Anti-duplikat: tolak jika tipe ini sudah diabsen hari ini ──
        $tipeJamField = [
            'clock_in'      => 'jam_masuk',
            'clock_out'     => 'jam_keluar',
            'lembur_masuk'  => 'jam_lembur_masuk',
            'lembur_keluar' => 'jam_lembur_keluar',
        ];
        $jamField = $tipeJamField[$tipe];

        $absensiToday = Absensi::where('karyawan_id', $karyawan->id)
            ->where('cabang_id', $cabang->id)
            ->where('tanggal', today()->toDateString())
            ->first();

        if ($absensiToday && $absensiToday->$jamField !== null) {
            $jamValue   = $absensiToday->$jamField;
            $jamDisplay = $jamValue instanceof \Carbon\Carbon
                ? $jamValue->format('H:i')
                : substr((string) $jamValue, 0, 5);

            $tipeLabelMap = [
                'clock_in'      => 'Masuk',
                'clock_out'     => 'Keluar',
                'lembur_masuk'  => 'Lembur Masuk',
                'lembur_keluar' => 'Lembur Keluar',
            ];
            $this->logPercobaan($cabang->id, $karyawan->id, $tipe, 'duplikat', null,
                $request->confidence_score, $lat, $lng, $jarak, true);
            Cache::put($cacheKey, $attempts + 1, 60);
            return response()->json([
                'success' => false,
                'status'  => 'duplikat',
                'message' => "⚠️ Anda sudah absen {$tipeLabelMap[$tipe]} pada {$jamDisplay}",
            ]);
        }

        // ── 6. Validasi GPS radius ────────────────────────────────────────
        if ($lat !== null && $lng !== null) {
            if (!$cabang->isLokasiValid($lat, $lng)) {
                $radius = $cabang->radius_absen_meter ?? 100;
                $this->logPercobaan($cabang->id, $karyawan->id, $tipe, 'invalid_lokasi', null,
                    $request->confidence_score, $lat, $lng, $jarak, true);
                Cache::put($cacheKey, $attempts + 1, 60);
                return response()->json([
                    'success' => false,
                    'status'  => 'invalid_lokasi',
                    'message' => "❌ Anda berada {$jarak}m dari cabang. Batas maksimal: {$radius}m",
                ]);
            }
        }

        // ── 7. Simpan foto ────────────────────────────────────────────────
        $fotoPath = null;
        if ($request->foto_absen) {
            $timestamp = now()->format('YmdHis');
            $fotoPath  = "face-attendance/{$karyawan->id}/{$tipe}_{$timestamp}.jpg";
            $this->simpanFotoBase64($request->foto_absen, $fotoPath);
        }

        // ── 8. Log valid → dapatkan ID untuk FK di absensi ───────────────
        $faceRec = $this->logPercobaan($cabang->id, $karyawan->id, $tipe, 'valid', $fotoPath,
            $request->confidence_score, $lat, $lng, $jarak, true);

        // ── 9. Sync absensi harian dengan data per-tipe ───────────────────
        $this->syncAbsensi($karyawan, $cabang, $tipe, $lat, $lng, $fotoPath, $faceRec->id);

        Cache::forget($cacheKey);

        // ── 10. Status absensi hari ini untuk success overlay ────────────
        $absensiHariIni = Absensi::where('karyawan_id', $karyawan->id)
            ->where('cabang_id', $cabang->id)
            ->where('tanggal', today()->toDateString())
            ->first();

        $formatJam = function (string $field) use ($absensiHariIni): ?string {
            if (!$absensiHariIni || !$absensiHariIni->$field) return null;
            $v = $absensiHariIni->$field;
            return $v instanceof \Carbon\Carbon ? $v->format('H:i') : substr((string) $v, 0, 5);
        };

        $pesanTipe = match ($tipe) {
            'clock_in'      => '✅ Clock In Berhasil!',
            'clock_out'     => '✅ Clock Out Berhasil!',
            'lembur_masuk'  => '⏰ Lembur Dimulai!',
            'lembur_keluar' => '✅ Lembur Selesai!',
            default         => '✅ Absensi Berhasil!',
        };

        return response()->json([
            'success' => true,
            'tipe'    => $tipe,
            'karyawan' => [
                'id'      => $karyawan->id,
                'nama'    => $karyawan->nama_lengkap,
                'jabatan' => $karyawan->jabatan,
                'foto'    => $karyawan->foto ? url('/img/' . $karyawan->foto) : null,
            ],
            'waktu'      => now()->format('H:i:s'),
            'tanggal'    => now()->format('d/m/Y'),
            'jarak'      => $jarak,
            'message'    => $pesanTipe,
            'foto_absen' => $fotoPath ? url('/img/' . $fotoPath) : null,
            'absensi_hari_ini' => [
                'jam_masuk'         => $formatJam('jam_masuk'),
                'jam_keluar'        => $formatJam('jam_keluar'),
                'jam_lembur_masuk'  => $formatJam('jam_lembur_masuk'),
                'jam_lembur_keluar' => $formatJam('jam_lembur_keluar'),
            ],
        ]);
    }

    /** Dashboard absensi hari ini */
    public function today(Request $request)
    {
        $cabangId   = session('active_cabang_id');
        $cabang     = $cabangId ? Cabang::find($cabangId) : null;
        $tanggal    = today();
        $statusFil  = $request->input('status', 'all'); // all|hadir|belum|telat

        $karyawans = Karyawan::where('cabang_id', $cabangId)
            ->aktif()
            ->orderBy('nama_lengkap')
            ->get();

        // Query Absensi hari ini (1 row per karyawan)
        $absensiToday = Absensi::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->where('cabang_id', $cabangId)
            ->whereDate('tanggal', $tanggal)
            ->get()
            ->keyBy('karyawan_id');

        $jamMasuk   = $cabang?->jam_masuk ?? '08:00';
        $statistics = ['hadir' => 0, 'terlambat' => 0, 'belum' => 0];

        foreach ($karyawans as $k) {
            $abs = $absensiToday->get($k->id);
            if ($abs && $abs->jam_masuk) {
                $statistics['hadir']++;
                $jamMasukRaw = substr((string) $abs->getRawOriginal('jam_masuk'), 0, 5);
                if ($jamMasukRaw > $jamMasuk) $statistics['terlambat']++;
            } else {
                $statistics['belum']++;
            }
        }

        // Filter karyawan berdasarkan status
        if ($statusFil === 'hadir') {
            $karyawans = $karyawans->filter(fn($k) => $absensiToday->has($k->id) && $absensiToday->get($k->id)->jam_masuk);
        } elseif ($statusFil === 'belum') {
            $karyawans = $karyawans->filter(fn($k) => !$absensiToday->has($k->id) || !$absensiToday->get($k->id)->jam_masuk);
        } elseif ($statusFil === 'telat') {
            $karyawans = $karyawans->filter(function ($k) use ($absensiToday, $jamMasuk) {
                $abs = $absensiToday->get($k->id);
                if (!$abs || !$abs->jam_masuk) return false;
                return substr((string) $abs->getRawOriginal('jam_masuk'), 0, 5) > $jamMasuk;
            });
        }

        return view('face-attendance.today', compact(
            'karyawans', 'absensiToday', 'cabang', 'tanggal',
            'statistics', 'jamMasuk', 'statusFil'
        ));
    }

    /** Riwayat log absensi wajah */
    public function log(Request $request)
    {
        $authUser       = auth()->user();
        $canAllBranches = $authUser->canAccessAllBranches();
        $cabangId       = $canAllBranches
            ? $request->input('cabang_id', session('active_cabang_id'))
            : session('active_cabang_id');

        // Default: hari ini, bisa range
        $dari    = $request->input('dari',    today()->toDateString());
        $sampai  = $request->input('sampai',  today()->toDateString());
        $tipeFil = $request->input('tipe',    'all');
        $statusFil   = $request->input('status',   'all');
        $karyawanFil = $request->input('karyawan_id');

        $query = FaceAttendance::with(['karyawan', 'device'])
            ->where('cabang_id', $cabangId)
            ->whereDate('waktu', '>=', $dari)
            ->whereDate('waktu', '<=', $sampai)
            ->orderByDesc('waktu');

        if ($tipeFil !== 'all') {
            $tipeMap = [
                'masuk'         => 'clock_in',
                'keluar'        => 'clock_out',
                'lembur_masuk'  => 'lembur_masuk',
                'lembur_keluar' => 'lembur_keluar',
            ];
            if (isset($tipeMap[$tipeFil])) {
                $query->where('tipe', $tipeMap[$tipeFil]);
            }
        }
        if ($statusFil !== 'all') {
            $query->where('status', $statusFil);
        }
        if ($karyawanFil) {
            $query->where('karyawan_id', $karyawanFil);
        }

        $logs = $query->paginate(30)->withQueryString();

        $karyawanList = Karyawan::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->where('cabang_id', $cabangId)
            ->aktif()
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap']);

        $cabangs = $canAllBranches
            ? \App\Models\Cabang::aktif()->orderBy('nama_cabang')->get()
            : collect();

        return view('face-attendance.log', compact(
            'logs', 'dari', 'sampai', 'tipeFil', 'statusFil', 'karyawanFil',
            'karyawanList', 'cabangs', 'cabangId', 'canAllBranches'
        ));
    }

    /** Hapus 1 log face attendance (permission: hapus_log_absensi) */
    public function destroyLog(FaceAttendance $faceAttendance)
    {
        abort_unless(auth()->user()->can('hapus_log_absensi'), 403, 'Anda tidak memiliki akses untuk menghapus log absensi.');

        $karyawanNama = $faceAttendance->karyawan?->nama_lengkap ?? 'Karyawan';
        $waktu        = $faceAttendance->waktu ? \Carbon\Carbon::parse($faceAttendance->waktu)->format('d/m/Y H:i') : '-';

        $faceAttendance->delete();

        return back()->with('success', "Log absensi wajah {$karyawanNama} ({$waktu}) berhasil dihapus.");
    }

    // ======================== PRIVATE ========================

    private function logPercobaan(
        int $cabangId,
        ?int $karyawanId,
        string $tipe,
        string $status,
        ?string $fotoPath,
        ?float $confidence,
        ?float $latitude = null,
        ?float $longitude = null,
        ?int $jarak = null,
        ?bool $isLivenessPassed = null,
    ): FaceAttendance {
        return FaceAttendance::create([
            'karyawan_id'        => $karyawanId,
            'cabang_id'          => $cabangId,
            'tipe'               => $tipe,
            'waktu'              => now(),
            'foto_absen'         => $fotoPath,
            'confidence_score'   => $confidence,
            'latitude'           => $latitude,
            'longitude'          => $longitude,
            'jarak_dari_cabang'  => $jarak,
            'status'             => $status,
            'is_liveness_passed' => $isLivenessPassed,
            'created_at'         => now(),
        ]);
    }

    /**
     * Hitung apakah karyawan terlambat saat clock-in.
     * Prioritas: shift karyawan > jam_masuk cabang (fallback 08:00).
     * Kembalikan [bool $isTelat, int $menitTelat].
     */
    private function hitungKeterlambatan(Karyawan $karyawan, Cabang $cabang, string $jamSekarang): array
    {
        $karyawan->loadMissing('shift');
        $shift = $karyawan->shift;

        if ($shift) {
            $jamMasukShift    = substr($shift->jam_masuk, 0, 5);          // "08:00"
            $toleransiMenit   = (int) ($shift->toleransi_telat_menit ?? 15);
        } else {
            $jamMasukShift    = substr($cabang->jam_masuk ?? '08:00', 0, 5);
            $toleransiMenit   = 15;
        }

        // Hitung batas toleransi sebagai Carbon pada tanggal hari ini
        $batasToleransi = \Carbon\Carbon::today()
            ->setTimeFromTimeString($jamMasukShift)
            ->addMinutes($toleransiMenit);
        $waktuAbsen     = \Carbon\Carbon::today()->setTimeFromTimeString(substr($jamSekarang, 0, 8));

        if ($waktuAbsen->gt($batasToleransi)) {
            return [true, (int) $batasToleransi->diffInMinutes($waktuAbsen)];
        }

        return [false, 0];
    }

    private function syncAbsensi(
        Karyawan $karyawan,
        Cabang $cabang,
        string $tipe,
        ?float $lat = null,
        ?float $lng = null,
        ?string $fotoPath = null,
        ?int $faceAttendanceId = null,
    ): void {
        $jamSekarang = now()->format('H:i:s');

        $absensi = Absensi::firstOrNew([
            'karyawan_id' => $karyawan->id,
            'tanggal'     => today()->toDateString(),
            'cabang_id'   => $cabang->id,
        ]);

        $absensi->cabang_id = $cabang->id;

        if ($tipe === 'clock_in') {
            $absensi->status       = 'hadir';
            $absensi->jam_masuk    = $jamSekarang;
            $absensi->tipe_absensi = 'masuk';
            $absensi->lat_masuk    = $lat;
            $absensi->lng_masuk    = $lng;
            $absensi->foto_masuk   = $fotoPath;
            $absensi->face_attendance_masuk_id = $faceAttendanceId;

            // Deteksi telat berdasarkan shift karyawan atau jam_masuk cabang
            [$isTelat, $menitTelat] = $this->hitungKeterlambatan($karyawan, $cabang, $jamSekarang);
            $absensi->is_telat    = $isTelat;
            $absensi->menit_telat = $menitTelat;

        } elseif ($tipe === 'clock_out') {
            $absensi->jam_keluar   = $jamSekarang;
            $absensi->tipe_absensi = 'keluar';
            $absensi->lat_keluar   = $lat;
            $absensi->lng_keluar   = $lng;
            $absensi->foto_keluar  = $fotoPath;
            $absensi->face_attendance_keluar_id = $faceAttendanceId;
        } elseif ($tipe === 'lembur_masuk') {
            if (!$absensi->exists) {
                $absensi->status = 'hadir';
            }
            $absensi->tipe_absensi          = 'lembur_masuk';
            $absensi->jam_lembur_masuk      = $jamSekarang;
            $absensi->lat_lembur_masuk      = $lat;
            $absensi->lng_lembur_masuk      = $lng;
            $absensi->foto_lembur_masuk     = $fotoPath;
            $absensi->face_attendance_lembur_masuk_id = $faceAttendanceId;
            $keterangan = $absensi->keterangan ?? '';
            $absensi->keterangan = trim($keterangan . ' [Lembur mulai ' . substr($jamSekarang, 0, 5) . ']');
        } elseif ($tipe === 'lembur_keluar') {
            $absensi->tipe_absensi           = 'lembur_keluar';
            $absensi->jam_lembur_keluar      = $jamSekarang;
            $absensi->lat_lembur_keluar      = $lat;
            $absensi->lng_lembur_keluar      = $lng;
            $absensi->foto_lembur_keluar     = $fotoPath;
            $absensi->face_attendance_lembur_keluar_id = $faceAttendanceId;
            // Hitung durasi lembur dari jam_lembur_masuk yang tersimpan di absensi
            if ($absensi->jam_lembur_masuk) {
                $mulai   = $absensi->jam_lembur_masuk instanceof \Carbon\Carbon
                    ? $absensi->jam_lembur_masuk
                    : \Carbon\Carbon::createFromFormat('H:i:s', $absensi->getRawOriginal('jam_lembur_masuk'));
                $diffJam = $mulai->diffInMinutes(now()) / 60;
                $absensi->jam_lembur = round($diffJam, 2);
            }
            $keterangan = $absensi->keterangan ?? '';
            $absensi->keterangan = trim($keterangan . ' [Lembur selesai ' . substr($jamSekarang, 0, 5) . ']');
        }

        $absensi->dicatat_oleh = null;
        $absensi->save();
    }

    private function simpanFotoBase64(string $base64, string $path): void
    {
        if (!str_contains($base64, 'base64,')) {
            Log::warning('[FotoAbsen] base64 tidak valid untuk path: ' . $path);
            return;
        }
        $data = base64_decode(substr($base64, strpos($base64, ',') + 1));
        if (!$data) {
            Log::warning('[FotoAbsen] Gagal decode base64 untuk path: ' . $path);
            return;
        }
        Storage::disk('public')->put($path, $data);
        $fullPath = storage_path('app/public/' . $path);
        $publicUrl = asset('storage/' . $path);
        $exists    = file_exists($fullPath);
        Log::info('[FotoAbsen] Disimpan: ' . $path
            . ' | Ada di disk: ' . ($exists ? 'YA' : 'TIDAK')
            . ' | URL: ' . $publicUrl);
    }
}
