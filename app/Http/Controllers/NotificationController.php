<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Halaman daftar semua notifikasi
     */
    public function index(Request $request)
    {
        $user  = auth()->user();
        $query = $user->notifications();

        // Filter status
        $status = $request->get('status', 'semua');
        if ($status === 'belum_dibaca') {
            $query->whereNull('read_at');
        } elseif ($status === 'sudah_dibaca') {
            $query->whereNotNull('read_at');
        }

        // Filter kategori
        if ($request->filled('kategori')) {
            $query->where('data->kategori', $request->kategori);
        }

        $notifications = $query->latest()->paginate(20)->withQueryString();
        $unreadCount   = $user->unreadNotifications()->count();

        $kategoris = [
            'stok'      => 'Stok & Gudang',
            'pembelian' => 'Pembelian',
            'penjualan' => 'Penjualan',
            'hr'        => 'HR / SDM',
            'evaluasi'  => 'Penilaian',
            'aset'      => 'Aset',
            'keuangan'  => 'Keuangan',
        ];

        return view('notifikasi.index', compact(
            'notifications', 'unreadCount', 'status', 'kategoris'
        ));
    }

    /**
     * Tandai dibaca lalu redirect ke URL tujuan notifikasi (untuk klik dari bell dropdown)
     */
    public function go(string $id)
    {
        $notification = auth()->user()->notifications()->where('id', $id)->first();

        $url = null;
        if ($notification) {
            $data = is_array($notification->data) ? $notification->data : json_decode($notification->data, true);
            $url  = $data['url'] ?? null;
            $notification->markAsRead();
        }

        return redirect($url ?? route('notifikasi.index'));
    }

    /**
     * Tandai satu notifikasi sebagai dibaca (dari halaman notifikasi)
     */
    public function markAsRead(string $id)
    {
        $notification = auth()->user()->notifications()->where('id', $id)->first();

        if ($notification) {
            $notification->markAsRead();
        }

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Notifikasi ditandai sudah dibaca.');
    }

    /**
     * Tandai semua notifikasi sebagai dibaca
     */
    public function markAllAsRead()
    {
        auth()->user()->unreadNotifications->markAsRead();

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    /**
     * Hapus satu notifikasi
     */
    public function destroy(string $id)
    {
        $notification = auth()->user()->notifications()->where('id', $id)->first();

        if ($notification) {
            $notification->delete();
        }

        return back()->with('success', 'Notifikasi dihapus.');
    }

    /**
     * API: Jumlah notifikasi belum dibaca (untuk polling badge)
     */
    public function unreadCount()
    {
        return response()->json([
            'count' => auth()->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * API: Data notifikasi terbaru untuk re-render dropdown (polling)
     */
    public function latest()
    {
        $user  = auth()->user();
        $items = $user->notifications()->latest()->take(10)->get()->map(function ($n) {
            $data = is_array($n->data) ? $n->data : json_decode($n->data, true);
            return [
                'id'       => $n->id,
                'title'    => $data['title']    ?? 'Notifikasi',
                'message'  => $data['message']  ?? '',
                'icon'     => $data['icon']     ?? 'bi-bell',
                'color'    => $data['color']    ?? 'info',
                'kategori' => $data['kategori'] ?? '',
                'go_url'   => route('notifikasi.go', $n->id),
                'diff'     => $n->created_at->diffForHumans(),
                'is_read'  => $n->read_at !== null,
            ];
        });

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'items'        => $items,
        ]);
    }
}
