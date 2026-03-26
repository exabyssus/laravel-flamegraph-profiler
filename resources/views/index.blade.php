@extends('laravel-profiler::layout', ['title' => 'Profiler'])

@section('content')
    <div class="card" style="margin-bottom:16px;">
        <form method="GET" action="{{ route('laravel-profiler.index') }}" style="display:flex;gap:8px;align-items:center;">
            <input type="text" name="path" value="{{ $pathFilter }}" placeholder="Filter by path (e.g. /api/v1/orders)" style="flex:1;padding:6px 10px;border:1px solid #334155;border-radius:4px;background:#020617;color:#e2e8f0;font-family:inherit;font-size:inherit;">
            <button type="submit" class="filter-btn">Filter</button>
            @if ($pathFilter !== '')
                <a href="{{ route('laravel-profiler.index') }}" class="filter-btn" style="text-decoration:none;">Clear</a>
            @endif
        </form>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <table>
            <thead>
                <tr>
                    <th>When</th>
                    <th>Request</th>
                    <th>Status</th>
                    <th>Duration</th>
                    <th>SQL</th>
                    <th>Excimer</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($profiles as $profile)
                    <tr>
                        <td>
                            <div>{{ \Illuminate\Support\Carbon::parse($profile['captured_at'])->format('H:i:s') }}</div>
                            <div class="muted">{{ \Illuminate\Support\Carbon::parse($profile['captured_at'])->diffForHumans() }}</div>
                        </td>
                        <td>
                            <a href="{{ route('laravel-profiler.show', $profile['id']) }}">
                                <span class="badge">{{ $profile['method'] }}</span>
                                {{ $profile['path'] }}
                            </a>
                            <div class="muted">{{ $profile['route_name'] ?? 'unnamed route' }}</div>
                        </td>
                        <td>{{ $profile['status'] }}</td>
                        <td>{{ number_format((float) $profile['duration_ms'], 2) }} ms</td>
                        <td>{{ $profile['query_count'] }} queries / {{ number_format((float) $profile['query_time_ms'], 2) }} ms</td>
                        <td>
                            @if (data_get($profile, 'excimer.available'))
                                {{ data_get($profile, 'excimer.sample_count', 0) }} samples
                            @else
                                <span class="muted">Unavailable</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="muted">No recent requests captured yet. Hit a web or API route in the local app and refresh.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="muted" style="font-size:11px;text-align:center;">
        {{ config('laravel-profiler.storage_path') }} · TTL {{ config('laravel-profiler.ttl_minutes') }} min · Max {{ config('laravel-profiler.max_entries') }} requests
    </div>
@endsection
