<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->isProduction() || config('app.force_https')) {
            URL::forceScheme('https');
        }

        VerifyEmail::createUrlUsing(function ($notifiable) {
            $frontendUrl = (string) (config('app.frontend_url') ?: 'http://localhost:3000');
            $expires = Carbon::now()->addMinutes(60)->timestamp;
            $id = $notifiable->getKey();
            $hash = sha1($notifiable->getEmailForVerification());
            $key = (string) config('app.key');
            $data = "id={$id}&hash={$hash}&expires={$expires}";
            $signature = hash_hmac('sha256', $data, $key);

            return "{$frontendUrl}/auth/verificar-email?id={$id}&hash={$hash}&expires={$expires}&signature={$signature}";
        });
    }
}
