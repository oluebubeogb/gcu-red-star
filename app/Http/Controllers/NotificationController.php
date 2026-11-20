<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Member/Notifications', [
            'notifications' => $request->user()->notifications()->limit(50)->get()
                ->map(fn ($n) => ['id' => $n->id, 'data' => $n->data, 'read' => (bool) $n->read_at, 'created_at' => $n->created_at]),
        ]);
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return back();
    }
}
