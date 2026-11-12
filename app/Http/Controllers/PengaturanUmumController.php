<?php

namespace App\Http\Controllers;

use App\Models\PengaturanUmum;
use App\Support\Branding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PengaturanUmumController extends Controller
{
    public function edit()
    {
        abort_unless(auth()->user()->can('pengaturan.umum_lihat'), 403);

        $setting = $this->setting();

        return view('pengaturan.umum', compact('setting'));
    }

    public function update(Request $request, Branding $branding)
    {
        abort_unless(auth()->user()->can('pengaturan.umum_edit'), 403);

        // 4.44 — SVG tidak diterima lagi (bisa berisi script & tidak bisa diolah
        // jadi ikon). Logo baru disimpan ulang sebagai PNG + ikon otomatis.
        $validated = $request->validate([
            'nama_perusahaan'   => ['required', 'string', 'max:100'],
            'nama_singkat'      => ['nullable', 'string', 'max:30'],
            'tagline'           => ['nullable', 'string', 'max:150'],
            'alamat_perusahaan' => ['nullable', 'string', 'max:1000'],
            'telepon'           => ['nullable', 'string', 'max:30'],
            'tanggal_mulai_periode' => ['nullable', 'integer', 'min:1', 'max:28'],
            'mode_ro'           => ['nullable', 'in:' . implode(',', array_keys(\App\Services\RoService::MODE))],
            // BABA Tahap 7d — bawaan stok opname
            'opname_hitung_buta'       => ['nullable', 'boolean'],
            'opname_batas_foto_hilang' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'logo'              => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            // 4.48 — Warna Aplikasi (#RRGGBB). Latar wajib warna terang supaya tulisan terbaca.
            'warna_utama'       => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'warna_sidebar'     => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'warna_latar'       => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/', function ($attr, $nilai, $gagal) {
                if (Branding::hexValid($nilai) && Branding::teksDiAtas($nilai) === '#FFFFFF') {
                    $gagal('Warna Latar Halaman harus warna terang (mis. putih, krem, abu muda) supaya tulisan tetap terbaca.');
                }
            }],
        ], [
            'logo.mimes' => 'Logo harus berupa gambar JPG, PNG, atau WebP.',
            'logo.max'   => 'Ukuran logo maksimal 2 MB.',
            'warna_utama.regex'   => 'Warna Utama tidak valid (contoh: #FF6B00).',
            'warna_sidebar.regex' => 'Warna Sidebar tidak valid (contoh: #1A1A1A).',
            'warna_latar.regex'   => 'Warna Latar tidak valid (contoh: #FFF8E7).',
        ]);

        $setting = $this->setting();

        $setting->nama_perusahaan   = $validated['nama_perusahaan'];
        $setting->nama_singkat      = $validated['nama_singkat'] ?? null;
        $setting->tagline           = $validated['tagline'] ?? null;
        $setting->alamat_perusahaan = $validated['alamat_perusahaan'] ?? null;
        $setting->telepon           = $validated['telepon'] ?? null;
        if (! empty($validated['mode_ro']) && $validated['mode_ro'] !== $setting->mode_ro) {
            activity()->performedOn($setting)->log('Mode RO belanja online diubah: ' . ($setting->mode_ro ?? 'lunas') . ' → ' . $validated['mode_ro']);
            $setting->mode_ro = $validated['mode_ro'];
        }
        if ($request->has('opname_batas_foto_hilang')) {
            $batas = round((float) ($validated['opname_batas_foto_hilang'] ?? 0), 2);
            $buta = $request->boolean('opname_hitung_buta');
            if ($batas !== (float) $setting->opname_batas_foto_hilang || $buta !== (bool) $setting->opname_hitung_buta) {
                activity()->performedOn($setting)->log('Pengaturan stok opname diubah: hitung buta ' . ($buta ? 'ya' : 'tidak') . ', batas foto hilang Rp ' . number_format($batas, 0, ',', '.'));
            }
            $setting->opname_hitung_buta = $buta;
            $setting->opname_batas_foto_hilang = $batas;
        }
        if (isset($validated['tanggal_mulai_periode'])) {
            $setting->tanggal_mulai_periode = (int) $validated['tanggal_mulai_periode'];
        }

        // 4.48 — sama dengan bawaan / kosong disimpan NULL (= ikut warna bawaan program)
        foreach (['utama', 'sidebar', 'latar'] as $jenis) {
            if ($request->has('warna_' . $jenis)) {
                $hex = Branding::hexValid($validated['warna_' . $jenis] ?? null);
                $setting->{'warna_' . $jenis} = ($hex && $hex !== Branding::WARNA_BAWAAN[$jenis]) ? $hex : null;
            }
        }

        if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
            try {
                $baru = $branding->simpanLogo($request->file('logo'));
            } catch (\Throwable $e) {
                Log::warning('Gagal memproses logo: ' . $e->getMessage());

                return back()->withInput()->withErrors(['logo' => 'Logo gagal diproses. Coba file PNG/JPG lain.']);
            }
            $this->hapusLogoLamaDisk($setting->logo_path);
            $setting->logo_path = $baru;
        } elseif ($request->boolean('hapus_logo') && $setting->logo_path) {
            $branding->hapusFileLogo();
            $this->hapusLogoLamaDisk($setting->logo_path);
            $setting->logo_path = null;
        }

        $setting->save();
        $branding->lupakan();

        activity()
            ->performedOn($setting)
            ->log('Update pengaturan umum perusahaan');

        return redirect()
            ->route('pengaturan.umum')
            ->with('success', 'Pengaturan umum berhasil disimpan.');
    }

    private function setting(): PengaturanUmum
    {
        return PengaturanUmum::firstOrCreate([], ['nama_perusahaan' => "D'mentai"]);
    }

    /** Logo yang di-upload sebelum fitur Identitas Usaha tersimpan di disk public. */
    private function hapusLogoLamaDisk(?string $path): void
    {
        if ($path && ! str_starts_with($path, Branding::FOLDER . '/') && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
