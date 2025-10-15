<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExtendDeliveryTimeNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public $provider;
    public $request_id;

    public function __construct($provider, $request_id)
    {
        $this->provider=$provider;
        $this->request_id=$request_id;
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
            'title'      => 'Request for delivery time extension.',
            'sub_title'  => 'Tap to see details',
            'provider'   => $this->provider,
            'request_id' => $this->request_id,
            'type'       => 'extend_delivery_time',
        ];
    }
}
