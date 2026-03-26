<?php

namespace Exabyssus\LaravelProfiler\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfilerAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            config('laravel-profiler.enabled', true)
            && app()->environment(config('laravel-profiler.allowed_environments', ['local', 'development'])),
            403,
            'Profiler is only available in configured local/development environments.'
        );

        return $next($request);
    }
}
