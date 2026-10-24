<?php

namespace App\Providers;

use App\View\Composers\AppLayoutComposer;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer(['layouts.app', 'layouts.customer', 'admin.*', 'customer.*'], AppLayoutComposer::class);

        // Force HTTPS in production so session cookies are marked secure.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Payment proof uploads stay on the private disk.
        config(['filesystems.disks.payment_proofs' => [
            'driver' => 'local',
            'root' => storage_path('app/private/payment-proofs'),
            'visibility' => 'private',
        ]]);
    }
}
