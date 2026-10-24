<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->string('filter')->toString() ?: 'unread';

        $query = AppNotification::where('audience', 'admin')
            ->with('customer:id,name,customer_code');

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        } elseif ($filter === 'read') {
            $query->whereNotNull('read_at');
        }

        $notifications = $query->orderByDesc('id')->paginate(25)->withQueryString();

        $counts = [
            'unread' => AppNotification::where('audience', 'admin')->whereNull('read_at')->count(),
            'all' => AppNotification::where('audience', 'admin')->count(),
        ];

        return view('admin.notifications.index', compact('notifications', 'filter', 'counts'));
    }

    public function markRead(AppNotification $notification)
    {
        abort_unless($notification->audience === 'admin', 403);

        $notification->forceFill(['read_at' => now()])->save();

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllRead()
    {
        AppNotification::where('audience', 'admin')->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
