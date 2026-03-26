<?php

namespace Exabyssus\LaravelProfiler\Http\Controllers;

use Exabyssus\LaravelProfiler\Support\FlamegraphBuilder;
use Exabyssus\LaravelProfiler\Support\ProfileStore;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProfilerController extends Controller
{
    public function __construct(
        private readonly ProfileStore $store,
        private readonly FlamegraphBuilder $flamegraphBuilder,
    ) {
    }

    public function index(Request $request): View
    {
        $profiles = $this->store->latest((int) config('laravel-profiler.index_limit', 50));
        $pathFilter = $request->query('path', '');

        if ($pathFilter !== '') {
            $profiles = array_values(array_filter(
                $profiles,
                static fn (array $profile): bool => str_contains($profile['path'], $pathFilter),
            ));
        }

        return view('laravel-profiler::index', [
            'profiles' => $profiles,
            'pathFilter' => $pathFilter,
        ]);
    }

    public function show(string $id): View
    {
        $profile = $this->store->find($id);

        abort_if($profile === null, 404);

        return view('laravel-profiler::show', [
            'profile' => $profile,
            'flamegraphRows' => $this->flamegraphBuilder->build((string) data_get($profile, 'excimer.collapsed', '')),
            'flamegraphTree' => $this->flamegraphBuilder->buildTree((string) data_get($profile, 'excimer.collapsed', '')),
        ]);
    }

    public function speedscope(string $id): JsonResponse
    {
        $profile = $this->store->find($id);

        abort_if($profile === null, 404);
        abort_if(! is_array(data_get($profile, 'excimer.speedscope')), 404);

        return response()->json(
            data_get($profile, 'excimer.speedscope'),
            200,
            ['Content-Disposition' => sprintf('inline; filename="laravel-profiler-%s-speedscope.json"', $id)]
        );
    }
}