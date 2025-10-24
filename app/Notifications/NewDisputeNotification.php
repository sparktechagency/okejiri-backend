<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewDisputeNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public $dispute_id;
    public function __construct($dispute_id)
    {
        $this->dispute_id = $dispute_id;
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
            'title'      => 'New dispute.',
            'sub_title'  => 'Tap to view',
            'dispute_id' => $this->dispute_id,
            'type'       => 'new_dispute',
        ];
    }
}
