<?php

namespace App\Http\Controllers;

use App\Models\BookingNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** Tandai satu notifikasi dibaca, lalu buka daftar booking. */
    public function read(Request $request, BookingNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->markAsRead();

        return redirect()->route($request->user()->isAdmin() && $notification->type === 'cancelled' ? 'booking.calendar' : 'booking.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->bookingNotifications()->unread()->update(['read_at' => now()]);

        return back();
    }
}
