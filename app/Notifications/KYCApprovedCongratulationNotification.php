<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KYCApprovedCongratulationNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */

    public $title, $sub_title;
    public function __construct($title, $sub_title)
    {
        $this->title     = $title;
        $this->sub_title = $sub_title;
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
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title'     => $this->title,
            'sub_title' => $this->sub_title,
            'type'      => 'kyc_approved',
        ];
    }
}
