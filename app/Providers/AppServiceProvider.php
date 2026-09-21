<?php
// @usim: feature="admin", type="provider"
namespace App\Providers;

use App\Contracts\KioskPostResolverContract;
use App\Contracts\PostServiceContract;
use App\Contracts\UnitsServiceContract;
use App\Contracts\UnitTranslationGeneratorContract;
use App\Services\Post\KioskPostResolver;
use App\Services\Post\PostService;
use App\Services\Units\UnitsService;
use App\Services\Units\UnitTranslationGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(UnitTranslationGeneratorContract::class, UnitTranslationGenerator::class);
        $this->app->singleton(UnitsServiceContract::class, UnitsService::class);
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
