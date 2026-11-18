<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use App\Models\User;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('lihat_audit_log'), 403, 'Anda tidak memiliki akses ke Audit Log.');

        $query = Activity::with('causer')->latest();
        self::sembunyikanTimIt($query);

        if ($request->filled('causer_id')) {
            $query->where('causer_id', $request->causer_id)
                  ->where('causer_type', User::class);
        }

        if ($request->filled('log_name')) {
            $query->where('log_name', $request->log_name);
        }

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('dari')) {
            $query->whereDate('created_at', '>=', $request->dari);
        }

        if ($request->filled('sampai')) {
            $query->whereDate('created_at', '<=', $request->sampai);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('description', 'like', '%' . $request->search . '%')
                  ->orWhere('subject_type', 'like', '%' . $request->search . '%');
            });
        }

        $logs      = $query->paginate(30)->withQueryString();
        $users     = (auth()->user()->isTeknisi() ? User::query() : User::pelanggan())->orderBy('name')->get(['id', 'name']);
        $logNames  = Activity::distinct()->pluck('log_name')->when(! auth()->user()->isTeknisi(), fn ($c) => $c->reject(fn ($n) => $n === 'TimIT'))->sort()->values();
        $events    = Activity::distinct()->pluck('event')->filter()->sort()->values();

        return view('audit-log.index', compact('logs', 'users', 'logNames', 'events'));
    }

    /**
     * Pelanggan (termasuk Owner) tidak melihat catatan Tim IT: log 'TimIT' dan
     * semua perubahan yang dilakukan akun Tim IT. Tim IT melihat semuanya.
     */
    private static function sembunyikanTimIt($query)
    {
        if (! auth()->user()->isTeknisi()) {
            $query->where(fn ($q) => $q->whereNull('log_name')->orWhere('log_name', '<>', 'TimIT'))
                ->where(fn ($q) => $q->whereNull('causer_id')->orWhere('causer_type', '<>', User::class)
                    ->orWhereNotIn('causer_id', User::where('role', \App\Enums\RoleUser::Teknisi->value)->select('id')));
        }

        return $query;
    }

    public function show($id)
    {
        abort_unless(auth()->user()->can('lihat_audit_log'), 403, 'Anda tidak memiliki akses ke Audit Log.');

        $log = self::sembunyikanTimIt(Activity::with('causer'))->findOrFail($id);
        return view('audit-log.show', compact('log'));
    }
}
