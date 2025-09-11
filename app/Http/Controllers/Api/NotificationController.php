<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    use ApiResponse;
    public function notifications(Request $request)
    {
        $user          = Auth::user();
        $perPage       = $request->input('per_page', 20);
        $notifications = $user->notifications()
            ->orderByDesc('created_at')
            ->paginate($perPage);
        $unreadCount = $user->unreadNotifications()->count();

        $data = [
            'unread_notifications_count' => $unreadCount,
            'notifications'              => $notifications,
        ];

        return $this->responseSuccess($data,'Notifications retrieved successfully.');
    }

    public function singleMark($notification_id)
    {
        try {
            $notification = Auth::user()
                ->unreadNotifications()
                ->where('id', $notification_id)
                ->first();

            if (! $notification) {
                return $this->responseError(null, 'Notification not found or already marked as read.', 404);
            }

            $notification->markAsRead();
            return $this->responseSuccess($notification, 'Notification marked as read successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'An error occurred while marking the notification.', 500);
        }
    }

    public function allMark()
    {
        try {
            $notifications = Auth::user()->unreadNotifications;

            if ($notifications->isEmpty()) {
                return $this->responseError(null, 'No unread notifications found.', 404);
            }

            $notifications->markAsRead();
            return $this->responseSuccess(null, 'All notifications marked as read successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'An error occurred while marking notifications.', 500);
        }
    }
}
