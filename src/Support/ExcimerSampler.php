<?php

namespace Exabyssus\LaravelProfiler\Support;

class ExcimerSampler
{
    public function __construct(
        private readonly float $samplePeriod,
        private readonly int $maxDepth,
    ) {
    }

    public function start(): mixed
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $profiler = new \ExcimerProfiler();
        $profiler->setPeriod($this->samplePeriod);
        $profiler->setEventType(EXCIMER_REAL);

        if (method_exists($profiler, 'setMaxDepth')) {
            $profiler->setMaxDepth($this->maxDepth);
        }

        $profiler->start();

        return $profiler;
    }

    public function stop(mixed $profiler): array
    {
        if (! $this->isAvailable() || $profiler === null) {
            return [
                'available' => false,
                'message' => 'Excimer extension is not installed in the active PHP runtime.',
                'sample_count' => 0,
                'collapsed' => null,
                'speedscope' => null,
            ];
        }

        $profiler->stop();
        $log = $profiler->getLog();
        $collapsed = method_exists($log, 'formatCollapsed') ? $log->formatCollapsed() : null;
        $speedscope = method_exists($log, 'getSpeedscopeData') ? $log->getSpeedscopeData() : null;

        return [
            'available' => true,
            'message' => null,
            'sample_count' => $this->countSamples($collapsed, $speedscope),
            'collapsed' => $collapsed,
            'speedscope' => $speedscope,
        ];
    }

    public function isAvailable(): bool
    {
        return extension_loaded('excimer')
            && class_exists('ExcimerProfiler')
            && defined('EXCIMER_REAL');
    }

    private function countSamples(?string $collapsed, mixed $speedscope): int
    {
        if (is_array($speedscope) && isset($speedscope['profiles'][0]['samples']) && is_array($speedscope['profiles'][0]['samples'])) {
            return count($speedscope['profiles'][0]['samples']);
        }

        if (! is_string($collapsed) || trim($collapsed) === '') {
            return 0;
        }

        return collect(preg_split('/\r\n|\r|\n/', trim($collapsed)) ?: [])
            ->sum(function (string $line): int {
                $parts = preg_split('/\s+/', trim($line));

                return isset($parts[1]) ? (int) $parts[1] : 0;
            });
    }
}