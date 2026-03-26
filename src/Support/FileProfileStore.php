<?php

namespace Exabyssus\LaravelProfiler\Support;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\SplFileInfo;

class FileProfileStore implements ProfileStore
{
    private readonly string $directory;

    public function __construct(
        private readonly Filesystem $files,
        string $directory,
        private readonly int $ttlMinutes,
        private readonly int $maxEntries,
    ) {
        $this->directory = str_starts_with($directory, DIRECTORY_SEPARATOR)
            ? $directory
            : base_path($directory);
    }

    public function save(array $profile): void
    {
        $this->ensureDirectory();
        $this->cleanupExpired();

        $this->files->put(
            $this->pathFor((string) $profile['id']),
            json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
        );

        $this->trimToMaxEntries();
    }

    public function latest(int $limit = 50): array
    {
        $this->cleanupExpired();

        return collect($this->jsonFiles())
            ->sortByDesc(fn (SplFileInfo $file): int => $file->getMTime())
            ->take($limit)
            ->map(fn (SplFileInfo $file): ?array => $this->decode($file->getPathname()))
            ->filter()
            ->values()
            ->all();
    }

    public function find(string $id): ?array
    {
        $path = $this->pathFor($id);

        if (! $this->files->exists($path)) {
            return null;
        }

        if ($this->isExpired($path)) {
            $this->files->delete($path);

            return null;
        }

        return $this->decode($path);
    }

    public function clear(): void
    {
        if ($this->files->isDirectory($this->directory)) {
            $this->files->deleteDirectory($this->directory);
        }
    }

    public function directory(): string
    {
        return $this->directory;
    }

    private function decode(string $path): ?array
    {
        $decoded = json_decode($this->files->get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function trimToMaxEntries(): void
    {
        $files = collect($this->jsonFiles())
            ->sortByDesc(fn (SplFileInfo $file): int => $file->getMTime())
            ->values();

        if ($files->count() <= $this->maxEntries) {
            return;
        }

        $files->slice($this->maxEntries)
            ->each(fn (SplFileInfo $file): bool => $this->files->delete($file->getPathname()));
    }

    private function cleanupExpired(): void
    {
        collect($this->jsonFiles())
            ->filter(fn (SplFileInfo $file): bool => $this->isExpired($file->getPathname()))
            ->each(fn (SplFileInfo $file): bool => $this->files->delete($file->getPathname()));
    }

    private function isExpired(string $path): bool
    {
        return $this->ttlMinutes > 0
            && ($this->files->lastModified($path) + ($this->ttlMinutes * 60)) < time();
    }

    private function jsonFiles(): array
    {
        if (! $this->files->isDirectory($this->directory)) {
            return [];
        }

        return array_values(array_filter(
            $this->files->files($this->directory),
            static fn (SplFileInfo $file): bool => $file->getExtension() === 'json'
        ));
    }

    private function pathFor(string $id): string
    {
        return $this->directory.DIRECTORY_SEPARATOR.$id.'.json';
    }

    private function ensureDirectory(): void
    {
        if (! $this->files->isDirectory($this->directory)) {
            $this->files->ensureDirectoryExists($this->directory);
        }
    }
}