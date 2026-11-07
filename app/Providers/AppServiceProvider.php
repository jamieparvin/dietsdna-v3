<?php

namespace App\Providers;

use App\Notifications\WelcomeNotification;
use App\Services\Onboarding;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
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
        View::composer(['layouts.app', 'nutrition-profile.edit', 'food-preferences.edit', 'weekly-meals.create'], function ($view) {
            if (auth()->check() && ! array_key_exists('setup', $view->getData())) {
                $view->with('setup', app(Onboarding::class)->state(auth()->user()));
            }
        });

        View::composer(['meal-ideas.index', 'weekly-meals.show', 'meal-plan.swap', 'saved-meals.index', 'meal-plan.index'], function ($view): void {
            $view->with('preferredMealMinutes', auth()->user()?->nutritionProfile?->cooking_minutes);
        });

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
