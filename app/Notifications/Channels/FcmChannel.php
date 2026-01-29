<?php
namespace App\Notifications\Channels;

use Illuminate\Support\Facades\Log;
use App\Services\PushNotificationService;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    /**
     * Send the given notification.
     */
    public function send($notifiable, Notification $notification)
    {

        $message = $notification->toFcm($notifiable);

        if (empty($message)) {
            return;
        }

        $tokens = $notifiable->devices()->pluck('fcm_token')->unique()->toArray();

        if (count($tokens) > 0) {
            $service = new PushNotificationService();
            $report  = $service->sendToDevices(
                $tokens,
                $message['title'],
                $message['body'],
                $message['data'] ?? []
            );

            return $report;
        }
    }
}
