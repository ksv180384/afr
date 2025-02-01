<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);

        // Чтоб ресурсы возвращали результат без data
        ResourceCollection::withoutWrapping();

        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {

            return (new MailMessage)
                ->subject('Подтвердите Email')
                ->line('Нажмите на кнопку ниже, чтобы подтвердить свой адрес электронной почты.')
                ->action('Подтвердить Email', $url);
        });

    }
}
