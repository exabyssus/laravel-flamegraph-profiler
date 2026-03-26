<?php

namespace Exabyssus\LaravelProfiler;

use Exabyssus\LaravelProfiler\Http\Middleware\CaptureProfiler;
use Exabyssus\LaravelProfiler\Http\Middleware\EnsureProfilerAccess;
use Exabyssus\LaravelProfiler\Support\ExcimerSampler;
use Exabyssus\LaravelProfiler\Support\FileProfileStore;
use Exabyssus\LaravelProfiler\Support\FlamegraphBuilder;
use Exabyssus\LaravelProfiler\Support\ProfileRecorder;
use Exabyssus\LaravelProfiler\Support\ProfileStore;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LaravelProfilerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-profiler.php', 'laravel-profiler');

        $this->app->singleton(ProfileStore::class, function ($app): FileProfileStore {
            return new FileProfileStore(
                $app['files'],
                (string) config('laravel-profiler.storage_path'),
                (int) config('laravel-profiler.ttl_minutes', 15),
                (int) config('laravel-profiler.max_entries', 200),
            );
        });

        $this->app->singleton(ExcimerSampler::class, function (): ExcimerSampler {
            return new ExcimerSampler(
                (float) config('laravel-profiler.excimer.sample_period', 0.001),
                (int) config('laravel-profiler.excimer.max_depth', 250),
            );
        });

        $this->app->singleton(ProfileRecorder::class, function ($app): ProfileRecorder {
            return new ProfileRecorder(
                $app->make(ProfileStore::class),
                $app->make(ExcimerSampler::class),
            );
        });

        $this->app->singleton(FlamegraphBuilder::class, fn (): FlamegraphBuilder => new FlamegraphBuilder());
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laravel-profiler');

        $this->publishes([
            __DIR__.'/../config/laravel-profiler.php' => config_path('laravel-profiler.php'),
        ], 'laravel-profiler-config');

        Route::middleware(['web', EnsureProfilerAccess::class])
            ->prefix(config('laravel-profiler.route_prefix', 'laravel-profiler'))
            ->as('laravel-profiler.')
            ->group(function (): void {
                require __DIR__.'/../routes/web.php';
            });

        /** @var Router $router */
        $router = $this->app->make('router');

        $router->pushMiddlewareToGroup('web', CaptureProfiler::class);
        $router->pushMiddlewareToGroup('api', CaptureProfiler::class);
    }
}