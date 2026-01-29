<?php
namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

class PushNotificationService
{
    protected $messaging;

    public function __construct()
    {
        $factory         = (new Factory)->withServiceAccount(base_path('firebase.json'));
        $this->messaging = $factory->createMessaging();
    }

    public function sendToDevices($tokens, $title, $body, $data = [])
    {
        if (empty($tokens)) {
            return;
        }
        try {

            // $payloadData = array_merge([
            //     'title' => $title,
            //     'body'  => $body,
            // ], $data);

            // $message = CloudMessage::new ()
            //     ->withData($payloadData);

            $safeData = [];

            foreach ($data as $key => $value) {
                $safeData[$key] = is_array($value)
                    ? json_encode($value)
                    : (string) $value;
            }

            $payloadData = array_merge([
                'title' => (string) $title,
                'body'  => (string) $body,
            ], $safeData);

            $message = CloudMessage::new ()
                ->withData($payloadData);

            return $this->messaging->sendMulticast($message, $tokens);
        } catch (Exception $e) {
            Log::error("FCM Service Error: " . $e->getMessage());
        }
    }
}
