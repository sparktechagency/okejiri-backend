<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WithdrawRequestPendingNotification extends Notification
{
    use Queueable;

    public $payout_id;
    public function __construct($payout_id)
    {
        $this->payout_id = $payout_id;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title'     => 'Withdrawal request in Pending',
            'sub_title' => 'After approving from the admin side you will get your money.',
            'payout_id' => $this->payout_id,
            'type'      => 'payout_request',
        ];
    }
}
