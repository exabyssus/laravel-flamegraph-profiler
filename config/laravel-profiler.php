<?php

return [
    'enabled' => (bool) env('LARAVEL_PROFILER_ENABLED', true),

    'allowed_environments' => array_values(array_filter(array_map(
        static fn (string $environment): string => trim($environment),
        explode(',', (string) env('LARAVEL_PROFILER_ALLOWED_ENVIRONMENTS', 'local,development'))
    ))),

    'route_prefix' => trim((string) env('LARAVEL_PROFILER_ROUTE_PREFIX', 'profiler'), '/'),

    'storage_path' => (string) env('LARAVEL_PROFILER_STORAGE_PATH', storage_path('app/laravel-profiler')),

    'ttl_minutes' => (int) env('LARAVEL_PROFILER_TTL_MINUTES', 15),

    'max_entries' => (int) env('LARAVEL_PROFILER_MAX_ENTRIES', 200),

    'index_limit' => (int) env('LARAVEL_PROFILER_INDEX_LIMIT', 50),

    'excimer' => [
        'sample_period' => (float) env('LARAVEL_PROFILER_EXCIMER_SAMPLE_PERIOD', 0.001),
        'max_depth' => (int) env('LARAVEL_PROFILER_EXCIMER_MAX_DEPTH', 250),
    ],
];
