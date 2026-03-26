<?php

namespace Exabyssus\LaravelProfiler\Support;

class ProfileSession
{
    public function __construct(
        public readonly string $id,
        public readonly float $startedAt,
        public readonly string $capturedAt,
        public array $spans = [],
        public int $queryCount = 0,
        public float $queryTimeMs = 0.0,
        public mixed $excimerProfiler = null,
        public array $syncJobStack = [],
    ) {
    }
}