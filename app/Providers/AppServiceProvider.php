<?php
// @usim: feature="admin", type="provider"
namespace App\Providers;

use App\Contracts\KioskPostResolverContract;
use App\Contracts\PostServiceContract;
use App\Services\Post\KioskPostResolver;
use App\Services\Post\PostService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PostServiceContract::class, PostService::class);
        $this->app->singleton(KioskPostResolverContract::class, KioskPostResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
