<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Storage\MagazineStorage::class, fn () => new \App\Storage\FilesystemMagazineStorage(config('redstar.magazine_disk', 'magazines')));
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // SMTP the Paddy/Neaby way: IPv4-first connect + port-driven TLS.
        \Illuminate\Support\Facades\Mail::extend('smtp', fn (array $config) => \App\Mail\SmtpTransportFactory::make($config));

        if ($this->app->environment('production') && str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        \Illuminate\Support\Facades\Gate::define('admin', fn ($u) => $u->isAdmin());
        \Illuminate\Support\Facades\Gate::define('editorial', fn ($u) => $u->isEditor());
    }
}
