<?php

namespace Exabyssus\LaravelProfiler\Support;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Throwable;

class ProfileRecorder
{
    private ?ProfileSession $currentSession = null;

    public function __construct(
        private readonly ProfileStore $store,
        private readonly ExcimerSampler $excimerSampler,
    ) {
        DB::listen(fn (QueryExecuted $query): bool => $this->recordQuery($query));
        Event::listen(JobQueued::class, fn (JobQueued $event) => $this->recordJobQueued($event));
        Event::listen(JobProcessing::class, fn (JobProcessing $event) => $this->recordJobProcessing($event));
        Event::listen(JobProcessed::class, fn (JobProcessed $event) => $this->recordJobProcessed($event));
    }

    public function start(Request $request): ProfileSession
    {
        $this->currentSession = new ProfileSession(
            id: (string) Str::uuid(),
            startedAt: microtime(true),
            capturedAt: now()->toIso8601String(),
            excimerProfiler: $this->excimerSampler->start(),
        );

        return $this->currentSession;
    }

    public function finish(ProfileSession $session, Request $request, int $status, ?Throwable $throwable = null): void
    {
        $durationMs = round((microtime(true) - $session->startedAt) * 1000, 3);
        $route = $request->route();

        $profile = [
            'id' => $session->id,
            'captured_at' => $session->capturedAt,
            'method' => $request->method(),
            'path' => $request->getPathInfo(),
            'route_name' => $route?->getName(),
            'status' => $status,
            'duration_ms' => $durationMs,
            'query_count' => $session->queryCount,
            'query_time_ms' => round($session->queryTimeMs, 3),
            'spans' => array_merge([
                [
                    'type' => 'request',
                    'label' => sprintf('%s %s', $request->method(), $request->getPathInfo()),
                    'start_ms' => 0,
                    'duration_ms' => $durationMs,
                    'meta' => [
                        'route' => $route?->getName(),
                    ],
                ],
            ], $session->spans),
            'excimer' => $this->excimerSampler->stop($session->excimerProfiler),
            'exception' => $throwable ? [
                'class' => $throwable::class,
                'message' => $throwable->getMessage(),
            ] : null,
        ];

        $this->store->save($profile);
        $this->currentSession = null;
    }

    private function resolveJobClass(object|string $job): string
    {
        if (is_string($job)) {
            return $job;
        }

        return $job::class;
    }

    private function recordJobQueued(JobQueued $event): void
    {
        if (! $this->currentSession instanceof ProfileSession) {
            return;
        }

        $jobClass = $this->resolveJobClass($event->job);
        $offsetMs = round((microtime(true) - $this->currentSession->startedAt) * 1000, 3);

        $this->currentSession->spans[] = [
            'type' => 'job:queued',
            'label' => class_basename($jobClass) . ' → ' . ($event->queue ?? 'default'),
            'start_ms' => $offsetMs,
            'duration_ms' => 0.001,
            'meta' => [
                'class' => $jobClass,
                'connection' => $event->connectionName,
                'queue' => $event->queue,
                'delay' => $event->delay,
            ],
        ];
    }

    private function recordJobProcessing(JobProcessing $event): void
    {
        if (! $this->currentSession instanceof ProfileSession) {
            return;
        }

        $this->currentSession->syncJobStack[$event->job->getJobId() ?? spl_object_id($event->job)] = microtime(true);
    }

    private function recordJobProcessed(JobProcessed $event): void
    {
        if (! $this->currentSession instanceof ProfileSession) {
            return;
        }

        $jobKey = $event->job->getJobId() ?? spl_object_id($event->job);
        $startedAt = $this->currentSession->syncJobStack[$jobKey] ?? null;
        unset($this->currentSession->syncJobStack[$jobKey]);

        if ($startedAt === null) {
            return;
        }

        $durationMs = round((microtime(true) - $startedAt) * 1000, 3);
        $offsetMs = max(0, round(($startedAt - $this->currentSession->startedAt) * 1000, 3));
        $jobName = $event->job->resolveName();

        $this->currentSession->spans[] = [
            'type' => 'job:sync',
            'label' => class_basename($jobName),
            'start_ms' => $offsetMs,
            'duration_ms' => max($durationMs, 0.001),
            'meta' => [
                'class' => $jobName,
                'connection' => $event->connectionName,
            ],
        ];
    }

    private function recordQuery(QueryExecuted $query): bool
    {
        if (! $this->currentSession instanceof ProfileSession) {
            return false;
        }

        $durationMs = round((float) ($query->time ?? 0), 3);
        $offsetMs = max(0, round(((microtime(true) - $this->currentSession->startedAt) * 1000) - $durationMs, 3));

        try {
            $rawSql = $query->toRawSql();
        } catch (Throwable) {
            $rawSql = $query->sql;
        }

        $this->currentSession->spans[] = [
            'type' => 'db',
            'label' => Str::limit($rawSql, 140),
            'start_ms' => $offsetMs,
            'duration_ms' => max($durationMs, 0.001),
            'meta' => [
                'connection' => $query->connectionName,
                'sql' => $rawSql,
                'bindings' => $query->bindings,
            ],
        ];

        $this->currentSession->queryCount++;
        $this->currentSession->queryTimeMs += $durationMs;

        return true;
    }
}