<?php

namespace App\Providers;

use App\Services\ReversePhoneOSINT\BingSearchProvider;
use App\Services\ReversePhoneOSINT\SearchProviderInterface;
use Illuminate\Support\ServiceProvider;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SearchProviderInterface::class, BingSearchProvider::class);
    }



    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        
    }
}
