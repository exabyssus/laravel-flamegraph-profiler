<?php

namespace Exabyssus\LaravelProfiler\Support;

interface ProfileStore
{
    public function save(array $profile): void;

    public function latest(int $limit = 50): array;

    public function find(string $id): ?array;

    public function clear(): void;

    public function directory(): string;
}