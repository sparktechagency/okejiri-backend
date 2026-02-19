<?php
namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class ExpoChannel
{
    /**
     * Send the given notification.
     */
    public function send($notifiable, Notification $notification)
    {
        if (! method_exists($notification, 'toExpo')) {
            return;
        }

        $message = $notification->toExpo($notifiable);

        if (empty($message)) {
            return;
        }

        $tokens = $notifiable->devices()->pluck('fcm_token')->unique()->toArray();

        if (count($tokens) > 0) {

            $messages = [];

            foreach ($tokens as $token) {
                $messages[] = [
                    'to'    => $token,
                    'title' => $message['title'] ?? '',
                    'body'  => $message['body'] ?? '',
                    'data'  => $message['data'] ?? [],
                    'sound' => 'default',
                ];
            }

            $response = Http::timeout(20)
                ->post('https://exp.host/--/api/v2/push/send', $messages);

            return $response->json();
        }

    }
}
