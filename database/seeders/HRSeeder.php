<?php

namespace Database\Seeders;

use App\Models\Absensi;
use App\Models\Cabang;
use App\Models\EvaluationAspect;
use App\Models\Karyawan;
use App\Models\Penggajian;
use App\Models\Cuti;
use App\Models\SaldoCuti;
use App\Models\User;
use App\Services\PenggajianService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class HRSeeder extends Seeder
{
    public function run(): void
    {
        // Pastikan EvaluationAspect sudah ada
        if (EvaluationAspect::count() === 0) {
            $this->call(EvaluationAspectSeeder::class);
        }

        $cabangA = Cabang::where('kode_cabang', 'CA001')->first();
        if (!$cabangA) {
            $this->command->warn('Cabang A tidak ditemukan. Skip HRSeeder.');
            return;
        }

        $manajerUser = User::where('email', 'manajer.a@berkahmulyo.com')->first();

        // ===== BUAT KARYAWAN =====
        $karyawanData = [
            [
                'nik'           => 'KRY-001',
                'nama_lengkap'  => 'Budi Santoso',
                'jenis_kelamin' => 'L',
                'jabatan'       => 'Operator Produksi',
                'tipe_karyawan' => 'tetap',
                'tanggal_masuk' => '2024-01-01',
                'gaji_pokok'    => 3500000,
                'telepon'       => '081234567801',
            ],
            [
                'nik'           => 'KRY-002',
                'nama_lengkap'  => 'Siti Rahayu',
                'jenis_kelamin' => 'P',
                'jabatan'       => 'Kasir',
                'tipe_karyawan' => 'tetap',
                'tanggal_masuk' => '2024-03-01',
                'gaji_pokok'    => 3000000,
                'telepon'       => '081234567802',
            ],
            [
                'nik'           => 'KRY-003',
                'nama_lengkap'  => 'Ahmad Fauzi',
                'jenis_kelamin' => 'L',
                'jabatan'       => 'Operator Produksi',
                'tipe_karyawan' => 'kontrak',
                'tanggal_masuk' => '2024-06-01',
                'gaji_pokok'    => 2800000,
                'telepon'       => '081234567803',
            ],
            [
                'nik'           => 'KRY-004',
                'nama_lengkap'  => 'Dewi Lestari',
                'jenis_kelamin' => 'P',
                'jabatan'       => 'Admin',
                'tipe_karyawan' => 'tetap',
                'tanggal_masuk' => '2024-09-01',
                'gaji_pokok'    => 3200000,
                'telepon'       => '081234567804',
            ],
        ];

        $karyawans = [];
        foreach ($karyawanData as $idx => $data) {
            // Skip jika NIK sudah ada
            $existing = Karyawan::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
                ->where('nik', $data['nik'])->first();
            if ($existing) {
                $karyawans[] = $existing;
                continue;
            }

            $k = Karyawan::create(array_merge($data, [
                'cabang_id' => $cabangA->id,
                'status'    => 'aktif',
                'alamat'    => 'Jl. Contoh No. ' . ($idx + 1),
            ]));

            // Set atasan: KRY-002, KRY-003, KRY-004 atasannya adalah KRY-001
            if ($idx > 0 && isset($karyawans[0])) {
                $k->update(['atasan_id' => $karyawans[0]->id]);
            }

            $karyawans[] = $k;
        }

        // ===== GENERATE ABSENSI MARET 2026 =====
        $tahun = 2026;
        $bulan = 3;
        $tanggalAwal  = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $tanggalAkhir = $tanggalAwal->copy()->endOfMonth();

        // Karyawan yang alpha: KRY-001 alpha di tgl 5, KRY-003 alpha di tgl 10 & 15
        $alphaSchedule = [
            $karyawans[0]->id ?? null => [5],
            $karyawans[2]->id ?? null => [10, 15],
        ];

        // Lembur schedule (jam)
        $lemburSchedule = [
            $karyawans[0]->id ?? null => [3 => 2, 8 => 1.5, 18 => 2],
            $karyawans[1]->id ?? null => [7 => 1, 20 => 1.5],
            $karyawans[3]->id ?? null => [12 => 2, 25 => 1],
        ];

        $current = $tanggalAwal->copy();
        while ($current <= $tanggalAkhir) {
            if ($current->dayOfWeek === 0) { // Minggu = libur
                $current->addDay();
                continue;
            }

            foreach ($karyawans as $k) {
                if (!$k->id) continue;

                $day = $current->day;
                $isAlpha = in_array($day, $alphaSchedule[$k->id] ?? []);
                $jamLembur = $lemburSchedule[$k->id][$day] ?? 0;

                // Skip jika sudah ada
                $alreadyExists = Absensi::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
                    ->where('karyawan_id', $k->id)
                    ->whereDate('tanggal', $current->format('Y-m-d'))
                    ->exists();

                if (!$alreadyExists) {
                    Absensi::create([
                        'karyawan_id'  => $k->id,
                        'cabang_id'    => $cabangA->id,
                        'tanggal'      => $current->format('Y-m-d'),
                        'status'       => $isAlpha ? 'alpha' : 'hadir',
                        'jam_masuk'    => '08:00',
                        'jam_keluar'   => $isAlpha ? null : ($jamLembur > 0 ? '18:00' : '17:00'),
                        'jam_lembur'   => $jamLembur,
                        'keterangan'   => $isAlpha ? 'Tanpa keterangan' : null,
                        'dicatat_oleh' => auth()->id() ?? 1,
                    ]);
                }
            }

            $current->addDay();
        }

        // ===== GENERATE PENGGAJIAN FEBRUARI 2026 =====
        $service = new PenggajianService();
        $periode = '2026-02';

        foreach ($karyawans as $k) {
            if (!$k->id) continue;
            $existing = Penggajian::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
                ->where('karyawan_id', $k->id)->where('periode', $periode)->first();
            if (!$existing) {
                $penggajian = $service->generate($k, $periode);
                $penggajian->update([
                    'status'        => 'dibayar',
                    'tanggal_bayar' => '2026-02-28',
                    'approved_by'   => $manajerUser?->id ?? 1,
                ]);
            }
        }

        // ===== BUAT DATA CUTI =====
        $k1 = $karyawans[0] ?? null;
        $k2 = $karyawans[1] ?? null;

        if ($k1 && $k1->id) {
            // Cuti disetujui untuk KRY-001
            $existing = Cuti::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
                ->where('karyawan_id', $k1->id)->where('tipe', 'cuti_tahunan')->first();
            if (!$existing) {
                Cuti::create([
                    'karyawan_id'      => $k1->id,
                    'cabang_id'        => $cabangA->id,
                    'tipe'             => 'cuti_tahunan',
                    'tanggal_mulai'    => '2026-03-02',
                    'tanggal_selesai'  => '2026-03-04',
                    'jumlah_hari'      => 3,
                    'alasan'           => 'Keperluan keluarga',
                    'status'           => 'disetujui',
                    'approved_by'      => $manajerUser?->id ?? 1,
                    'approved_at'      => '2026-03-01 09:00:00',
                    'catatan_approver' => 'Disetujui',
                ]);

                // Update saldo cuti
                SaldoCuti::updateOrCreate(
                    ['karyawan_id' => $k1->id, 'tahun' => 2026],
                    ['saldo_awal' => 12, 'terpakai' => 3, 'sisa' => 9]
                );
            }
        }

        if ($k2 && $k2->id) {
            // Cuti pending untuk KRY-002
            $existing = Cuti::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
                ->where('karyawan_id', $k2->id)->where('status', 'pending')->first();
            if (!$existing) {
                Cuti::create([
                    'karyawan_id'     => $k2->id,
                    'cabang_id'       => $cabangA->id,
                    'tipe'            => 'izin',
                    'tanggal_mulai'   => '2026-04-01',
                    'tanggal_selesai' => '2026-04-01',
                    'jumlah_hari'     => 1,
                    'alasan'          => 'Periksa dokter',
                    'status'          => 'pending',
                ]);
            }
        }

        $this->command->info('HRSeeder: ' . count($karyawans) . ' karyawan, absensi Maret 2026, penggajian Februari 2026, dan data cuti berhasil dibuat.');
    }
}
