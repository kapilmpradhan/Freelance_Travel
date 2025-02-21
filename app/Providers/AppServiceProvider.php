<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use App\Services\IEmailService;
use App\Services\BrevoEmailService;
use Illuminate\Support\Facades\URL;
use Laravel\Pennant\Feature;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(IEmailService::class, BrevoEmailService::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);

        if (config('app.env') !== 'local') {
            URL::forceScheme('https');
        }

        // Define the "order-data-validation-feature"
        Feature::define('order-data-validation-feature', function () {
            return true;
        });
    }
}
