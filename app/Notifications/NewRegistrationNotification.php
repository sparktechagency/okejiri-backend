<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewRegistrationNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public $count;
    public $message;
    public $newUserRole;

    public function __construct($count, $message, $newUserRole)
    {
        $this->count       = $count;
        $this->message     = $message;
        $this->newUserRole = $newUserRole;
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
        $subTitle = 'Tap to view new ' . ($this->newUserRole === 'provider' ? 'providers' : 'users');

        return [
            'count'     => $this->count,
            'title'     => $this->message,
            'sub_title' => $subTitle,
            'type'      => $this->newUserRole,
        ];
    }
}
