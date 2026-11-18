<?php

namespace App\Console\Commands;

use App\Enums\RoleUser;
use App\Models\User;
use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Akun Tim IT pertama (porting Dimsum 4.53; Dimsum memakai skrip deploy, BABA punya terminal hosting).
 * Isian dibaca LANGSUNG dari file .env (config bisa ter-cache → env() kosong). Kata sandi tidak pernah dicetak.
 */
class BuatAkunTimIt extends Command
{
    protected $signature = 'tim-it:buat {--file= : Lokasi file .env (bawaan: .env aplikasi)}';

    protected $description = 'Membuat akun Tim IT pertama dari TEKNISI_NAMA, TEKNISI_EMAIL, TEKNISI_PASSWORD di file .env';

    public function handle(): int
    {
        $file = $this->option('file') ?: base_path('.env');
        if (! is_file($file)) {
            $this->error("File .env tidak ditemukan: {$file}");

            return self::FAILURE;
        }
        $env = Dotenv::parse((string) file_get_contents($file));
        $nama = trim((string) ($env['TEKNISI_NAMA'] ?? ''));
        $email = strtolower(trim((string) ($env['TEKNISI_EMAIL'] ?? '')));
        $sandi = (string) ($env['TEKNISI_PASSWORD'] ?? '');

        if ($nama === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Isi TEKNISI_NAMA dan TEKNISI_EMAIL (email yang sah) di .env dulu.');

            return self::FAILURE;
        }
        if (strlen($sandi) < 12 || ! preg_match('/[A-Za-z]/', $sandi) || ! preg_match('/\d/', $sandi)) {
            $this->error('TEKNISI_PASSWORD minimal 12 karakter dan berisi huruf + angka.');

            return self::FAILURE;
        }

        $ada = User::where('email', $email)->first();
        if ($ada && ! $ada->isTeknisi()) {
            $this->error("Email {$email} sudah dipakai akun pengguna BABA — pakai email lain untuk Tim IT.");

            return self::FAILURE;
        }
        if ($ada) {
            $this->info("Akun Tim IT {$email} sudah ada — tidak diubah (ganti kata sandi lewat menu Pengaturan › Tim IT).");

            return self::SUCCESS;
        }

        $u = User::create(['name' => $nama, 'email' => $email, 'password' => Hash::make($sandi), 'role' => RoleUser::Teknisi->value, 'is_active' => true]);
        activity('TimIT')->performedOn($u)->log("Akun Tim IT pertama {$u->name} ({$u->email}) dibuat lewat perintah tim-it:buat");
        $this->info("Akun Tim IT {$u->name} ({$u->email}) dibuat. Hapus baris TEKNISI_PASSWORD dari .env sekarang.");

        return self::SUCCESS;
    }
}
