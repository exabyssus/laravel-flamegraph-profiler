<?php

namespace Exabyssus\LaravelProfiler\Http\Middleware;

use Closure;
use Exabyssus\LaravelProfiler\Support\ProfileRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CaptureProfiler
{
    public function __construct(private readonly ProfileRecorder $recorder)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        $session = $this->recorder->start($request);

        try {
            $response = $next($request);
            $this->recorder->finish($session, $request, $response->getStatusCode());

            return $response;
        } catch (Throwable $throwable) {
            $this->recorder->finish($session, $request, 500, $throwable);

            throw $throwable;
        }
    }

    private function shouldSkip(Request $request): bool
    {
        if (! config('laravel-profiler.enabled', true)) {
            return true;
        }

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return true;
        }

        if (! app()->environment(config('laravel-profiler.allowed_environments', ['local', 'development']))) {
            return true;
        }

        $prefix = trim((string) config('laravel-profiler.route_prefix', 'laravel-profiler'), '/');

        if ($prefix !== '' && Str::startsWith(trim($request->path(), '/'), $prefix)) {
            return true;
        }

        return $request->isMethod('OPTIONS');
    }
}