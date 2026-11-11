<?php

namespace Tests\Feature\Tahap7;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Tahap 7 D'mentai (Bug 3 + Bug 5) — GUARD RAIL: <form> NESTED di HTML
 * adalah pola HARAM di project ini. Browser membuang tag <form> dalam yang
 * bersarang tapi tetap memasukkan child input-nya (termasuk hidden
 * _method/_token) ke form LUAR, menyebabkan method-spoofing salah sasaran
 * (Bug 3: "Simpan" ter-spoof jadi DELETE) atau form salah kirim (Bug 5:
 * tombol ter-asosiasi ke form GET yang salah, fitur silent tidak jalan).
 *
 * Test ini scan SEMUA file blade project (bukan cuma yang sudah diketahui
 * bermasalah) dan gagal kalau nested form baru muncul — supaya pola ini
 * tidak masuk lagi lewat fitur baru ke depan tanpa ketahuan.
 *
 * NOTE: ini static-analysis atas source .blade.php (bukan rendered HTML),
 * jadi @if/@can/@foreach diabaikan sebagai teks biasa -- False positive
 * MUNGKIN terjadi kalau ada 2 form independen yang salah satunya dibungkus
 * kondisional sedemikian rupa hingga urutan tag di source terlihat
 * bersarang padahal saat render tidak (jarang terjadi, WAJIB verifikasi
 * manual dgn baca file kalau test ini gagal, sebelum asumsi itu bug nyata).
 */
class NestedFormGuardRailTest extends TestCase
{
    public function test_tidak_ada_file_blade_dengan_nested_form(): void
    {
        $basePath = base_path('resources/views');
        $files = File::allFiles($basePath);

        $bermasalah = [];

        foreach ($files as $f) {
            if (! str_ends_with($f->getFilename(), '.blade.php')) continue;

            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $f->getPathname());
            $content = File::get($f->getPathname());

            // Buang komentar blade {{-- ... --}} supaya contoh/dokumentasi
            // (mis. komentar penjelasan fix Bug 3/5 ini sendiri) tidak ikut
            // ke-parse sebagai <form> sungguhan.
            $content = preg_replace('/\{\{--.*?--\}\}/s', '', $content);

            preg_match_all('/<form\b|<\/form>/i', $content, $matches);

            $depth = 0;
            $maxDepth = 0;
            foreach ($matches[0] as $tag) {
                if (stripos($tag, '</form>') === 0) {
                    $depth = max(0, $depth - 1); // max(0,..) toleransi @if/@endif split form di source
                } else {
                    $depth++;
                }
                $maxDepth = max($maxDepth, $depth);
            }

            if ($maxDepth > 1) {
                $bermasalah[] = "{$relativePath} (depth {$maxDepth})";
            }
        }

        $this->assertEmpty(
            $bermasalah,
            "NESTED <form> terdeteksi di file berikut:\n" . implode("\n", $bermasalah) .
            "\n\nIni pola HARAM (lihat CLAUDE.md 4.13/4.14) -- pisahkan jadi sibling form + attribute form=\"id\" pada tombolnya."
        );
    }
}
