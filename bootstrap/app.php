<?php

use App\Http\Middleware\CabangMiddleware;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        channels: __DIR__.'/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'cabang' => CabangMiddleware::class,
        ]);
        // 4.44 — nama & ikon PWA dari Pengaturan Umum
        $middleware->web(append: [\App\Http\Middleware\TerapkanBranding::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Closing / Tutup Buku — perubahan data di periode terkunci tampil
        // sebagai pesan, bukan halaman 500.
        $exceptions->render(function (\App\Exceptions\PeriodeTerkunciException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withInput()->with('error', e($e->getMessage()));
        });
        $exceptions->dontReport([\App\Exceptions\PeriodeTerkunciException::class]);
    })->create();
