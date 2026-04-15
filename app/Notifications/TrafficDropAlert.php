<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrafficDropAlert extends Notification
{
    use Queueable;

    public function __construct(public \App\Models\Website $website)
    {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('EBQ Alert: Traffic Drop Detected')
            ->line("A traffic drop was detected for {$this->website->domain}.")
            ->action('Open EBQ', route('dashboard'))
            ->line('Review traffic sources and keyword changes to investigate.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'website_id' => $this->website->id,
            'domain' => $this->website->domain,
        ];
    }
}
