<?php

namespace App\Providers;

use App\Notifications\WelcomeNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $broker = config('auth.defaults.passwords');
            $minutes = (int) config("auth.passwords.{$broker}.expire", 60);
            $resetUrl = route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);

            return (new MailMessage)
                ->subject('Reset your DietsDNA password')
                ->view('emails.reset-password', [
                    'name' => $notifiable->name,
                    'actionUrl' => $resetUrl,
                    'expires' => $minutes,
                ])
                ->text('emails.reset-password-text', [
                    'name' => $notifiable->name,
                    'actionUrl' => $resetUrl,
                    'expires' => $minutes,
                ]);
        });

        Event::listen(Registered::class, function (Registered $event): void {
            // Send only for registration, not when an existing user logs in.
            // A mail outage must not turn a successful registration into an error.
            try {
                $event->user->notify(new WelcomeNotification);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
