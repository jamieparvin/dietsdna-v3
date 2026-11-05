<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = [
            'name' => $notifiable->name,
            'actionUrl' => route('nutrition-profile.edit'),
        ];

        // Sent directly: the meal-plans-only worker does not process mail.
        return (new MailMessage)
            ->subject('Welcome to DietsDNA — let’s build your plan')
            ->view('emails.welcome', $data)
            ->text('emails.welcome-text', $data);
    }
}
