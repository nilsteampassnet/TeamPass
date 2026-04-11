<?php

namespace Elegant\Sanitizer\Laravel;

use Illuminate\Support\ServiceProvider;

class SanitizerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Register the sanitizer factory:
        $this->app->singleton('sanitizer', function ($app) {
            return new Factory;
        });
    }
}
