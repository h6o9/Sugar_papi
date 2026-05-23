<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\JobNotification;
use App\Jobs\NotificationJob;
use App\Models\AdminNotification;
use App\Models\Notification;
use App\Models\SubAdmin;
use App\Models\User;
use App\Models\UserRolePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {

        $notifications = AdminNotification::latest()->get();

        $users = User::all();

        return view('admin.notification.index', compact('notifications', 'users'));

    }

    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'user_type' => 'required',

    //     ],
    //         [
    //             'user_type.required' => 'User Type is required',
    //         ]);

    //     AdminNotification::create([
    //         'title' => $request->title,
    //         'description' => $request->description,
    //     ]);

    //     // Iterate through the arrays and create notifications
    //     foreach ($request->users as $userId) {
    //         $notification = Notification::create([
    //             'user_id' => $userId,
    //             'title' => $request->title,
    //             'description' => $request->description,
    //             'created_at' => now(),
    //         ]);

    //                $customer = User::find($userId);
    //         if ($customer && $customer->fcm_token) {
    //             $data = [
    //                 'id' => $notification->id,
    //                 'title' => $request->title,
    //                 'body' => $request->description,

    //             ];
    //             dispatch(new NotificationJob($customer->fcm_token, $request->title, $request->description, $data));
    //         }
    //     }

    //     return redirect()->route('notification.index')->with(['success' => 'Notification Sent Successfully']);
    // }

    public function store(Request $request)
    {
        // 1️⃣ Validation
        $request->validate(
            [
                'user_type' => 'required',
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'users' => 'required|array', // Ensure users array provided
            ],
            [
                'user_type.required' => 'User Type is required',
            ]
        );

        // 2️⃣ Create Admin Notification (if you need a record for admin)
        AdminNotification::create([
            'title' => $request->title,
            'description' => $request->description,
        ]);

        // 3️⃣ Iterate through the arrays and create notifications for each user
        foreach ($request->users as $userId) {
            // 3.1 DB Notification for User
            $notification = Notification::create([
                'user_id' => $userId,
                'title' => $request->title,
                'description' => $request->description,
                'seenByUser' => 0, // default unseen
                'created_at' => now(),
            ]);

            // 3.2 Push Notification (if user has fcm_token)
            $customer = User::where('id', $userId)->first();
            // dd($customer);

            if ($customer && $customer->fcmtoken) {
                // dd($customer->fcmtoken);
                $data = [
                    'id' => $notification->id,
                    'type' => 'admin_notification', // optional type field
                    'title' => $request->title,
                    'body' => $request->description,
                    "screen_name" => "Notifications"
                ];

                // dd($data);

                // Dispatch the notification job
                dispatch(new JobNotification(
                    $customer->fcmtoken,
                    $request->title,
                    $request->description,
                    $data
                ));
            }
        }

        // 4️⃣ Redirect back with success after loop completes
        return redirect()->route('notification.index')
            ->with(['success' => 'Notifications sent successfully']);
    }

    public function destroy(Request $request, $id)
    {

        $notification = AdminNotification::find($id);
        $notification->delete();

        return redirect()->route('notification.index')->with(['success' => 'Notification Deleted Successfully']);
    }

    public function deleteAll()
    {

        AdminNotification::truncate();  // or Notification::query()->delete(); if you want model events to trigger

        return redirect()->route('notification.index')->with(['success' => 'All notifications have been deleted']);

    }

    public function getUsersByType(Request $request)
    {

        $type = $request->type;

        $users = [];

        switch ($type) {

            case 'subadmin':

                $users = SubAdmin::select('id', 'name', 'email')->get();

                break;

            case 'web':

                $users = User::select('id', 'name', 'email')->get();

                break;

        }

        return response()->json($users);

    }

	// Web Notifications
	 public function Webindex()
    {
		
        if (!Auth::guard('user')->check()) {
            return redirect()->route('login');
        }

        $userId = Auth::guard('user')->id();
        
        // Get all notifications
        $notifications = Notification::where('user_id', $userId)
            ->latest()
            ->paginate(20); // 20 notifications per page

        // Count unread notifications
        $unreadCount = Notification::where('user_id', $userId)
            ->count();

        return view('home.index-notificaiton', compact('notifications', 'unreadCount'));
    }

    public function markAsRead(Request $request)
    {
        if (!Auth::guard('user')->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $notificationId = $request->id;
        $userId = Auth::guard('user')->id();

        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if ($notification) {
            $notification->seenByUser = '1';
            $notification->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);
        }

        return response()->json(['error' => 'Notification not found'], 404);
    }

   public function clearAllNotifications()
{
    if (!Auth::guard('user')->check()) {
        return redirect()->route('login');
    }

    $userId = Auth::guard('user')->id();

    // Delete all notifications of this user
    Notification::where('user_id', $userId)->delete();

    return redirect()->back()->with('success', 'All notifications cleared successfully');
}
}
