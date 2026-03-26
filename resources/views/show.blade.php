@extends('laravel-profiler::layout', ['title' => ($profile['method'] ?? 'Request').' '.($profile['path'] ?? '')])

@section('back', '1')

@section('header-actions')
    @if (data_get($profile, 'excimer.available'))
        <a href="{{ route('laravel-profiler.speedscope', $profile['id']) }}" class="btn">Speedscope export (json)</a>
    @endif
@endsection

@section('content')
    <div class="grid" style="margin-bottom:16px;">
        <div class="card"><div class="muted">Request</div><strong>{{ $profile['method'] }} {{ $profile['path'] }}</strong></div>
        <div class="card"><div class="muted">Status</div><strong>{{ $profile['status'] }}</strong></div>
        <div class="card"><div class="muted">Duration</div><strong>{{ number_format((float) $profile['duration_ms'], 2) }} ms</strong></div>
        <div class="card"><div class="muted">SQL</div><strong>{{ $profile['query_count'] }} queries / {{ number_format((float) $profile['query_time_ms'], 2) }} ms</strong></div>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <div class="muted">Captured at {{ $profile['captured_at'] }}</div>
        <div class="muted">Route {{ $profile['route_name'] ?? 'unnamed route' }}</div>
        @if (!data_get($profile, 'excimer.available'))
            <div style="margin-top:8px;" class="muted">{{ data_get($profile, 'excimer.message') }}</div>
        @endif
    </div>

    <div class="card" style="margin-bottom:16px;">
        <h2 class="pane-toggle" data-pane="timeline">Request timeline</h2>
        <div class="pane-body" id="pane-timeline">
            <div class="timeline">
                @foreach ($profile['spans'] as $span)
                    <div class="timeline-row">
                        <div class="timeline-label" title="">
                            {{ strtoupper($span['type']) }} · {{ $span['label'] }} · {{ number_format((float) $span['duration_ms'], 2) }} ms
                            <div class="timeline-popup">{{ strtoupper($span['type']) }} · {{ $span['label'] }}<br>{{ number_format((float) $span['duration_ms'], 2) }} ms</div>
                        </div>
                        <div class="timeline-track">
                            @php
                                $left = $profile['duration_ms'] > 0 ? (($span['start_ms'] / $profile['duration_ms']) * 100) : 0;
                                $width = $profile['duration_ms'] > 0 ? max(($span['duration_ms'] / $profile['duration_ms']) * 100, 0.8) : 100;
                            @endphp
                            <div class="timeline-bar" style="left:{{ $left }}%;width:{{ $width }}%;"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <h2 class="pane-toggle" data-pane="sql">SQL queries</h2>
        <div class="pane-body" id="pane-sql">
        @php
            $normalizeSql = function (string $sql): string {
                $normalized = preg_replace('/\'[^\']*\'/', '?', $sql);
                $normalized = preg_replace('/\b\d+\b/', '?', $normalized);
                $normalized = preg_replace('/\bin\s*\(\s*\?(?:\s*,\s*\?)*\s*\)/i', 'in (?)', $normalized);
                return trim(preg_replace('/\s+/', ' ', $normalized));
            };

            $dbSpans = collect($profile['spans'])->where('type', 'db');

            $nPlusOnePatterns = $dbSpans->groupBy(function ($query) use ($normalizeSql) {
                return $normalizeSql(data_get($query, 'meta.sql', $query['label']));
            })->filter(fn ($group) => $group->count() >= 3);

            $nPlusOneSqls = $nPlusOnePatterns->keys()->all();

            $grouped = $dbSpans->groupBy(function ($query) {
                $sql = data_get($query, 'meta.sql', $query['label']);
                $op = strtoupper(strtok(trim($sql), ' ') ?: 'OTHER');
                if (preg_match('/\b(?:from|into|update|join)\s+[`"\']?(\w+)[`"\']?/i', $sql, $m)) {
                    $table = $m[1];
                } else {
                    $table = '-';
                }
                return $table . '::' . $op;
            })->map(function ($queries, $key) use ($normalizeSql, $nPlusOneSqls) {
                [$table, $op] = explode('::', $key, 2);
                $slow = $queries->filter(fn ($q) => (float) $q['duration_ms'] > 5)->count();
                $mid = $queries->filter(fn ($q) => (float) $q['duration_ms'] >= 1 && (float) $q['duration_ms'] <= 5)->count();
                $fast = $queries->filter(fn ($q) => (float) $q['duration_ms'] < 1)->count();

                $n1Queries = $queries->filter(function ($q) use ($normalizeSql, $nPlusOneSqls) {
                    return in_array($normalizeSql(data_get($q, 'meta.sql', $q['label'])), $nPlusOneSqls, true);
                });

                $n1Patterns = $n1Queries->groupBy(function ($q) use ($normalizeSql) {
                    return $normalizeSql(data_get($q, 'meta.sql', $q['label']));
                })->map(fn ($g) => $g->count())->all();

                return [
                    'table' => $table,
                    'operation' => $op,
                    'count' => $queries->count(),
                    'total_ms' => $queries->sum(fn ($q) => (float) $q['duration_ms']),
                    'fast' => $fast,
                    'mid' => $mid,
                    'slow' => $slow,
                    'n1_count' => $n1Queries->count(),
                    'n1_patterns' => $n1Patterns,
                    'queries' => $queries->values(),
                ];
            })->sortByDesc('total_ms')->values();

            $totalN1 = $nPlusOnePatterns->count();
            $totalN1Queries = $nPlusOnePatterns->sum(fn ($g) => $g->count());
        @endphp

        @if ($grouped->isEmpty())
            <p class="muted" style="margin-bottom:0;">No database queries were captured for this request.</p>
        @else
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;align-items:center;" id="sql-filters">
                <input type="text" id="sql-filter-table" placeholder="Filter table..." style="padding:5px 10px;border:1px solid #1f2937;border-radius:4px;background:#020617;color:#e2e8f0;font-family:inherit;font-size:12px;width:140px;">
                <span class="muted" style="font-size:11px;">Operation:</span>
                @php
                    $opOrder = ['SELECT', 'INSERT', 'UPDATE', 'DELETE'];
                    $availableOps = $grouped->pluck('operation')->unique();
                    $sortedOps = collect($opOrder)->filter(fn ($o) => $availableOps->contains($o))
                        ->merge($availableOps->diff($opOrder)->sort())->values();
                @endphp
                @foreach ($sortedOps as $op)
                    <button class="sql-op-filter filter-btn" data-op="{{ $op }}">{{ $op }}</button>
                @endforeach
                <span class="muted" style="font-size:11px;">Speed:</span>
                <button class="sql-speed-filter filter-btn" data-speed="fast" style="color:#4ade80;">&lt; 1 ms</button>
                <button class="sql-speed-filter filter-btn" data-speed="mid" style="color:#facc15;">1-5 ms</button>
                <button class="sql-speed-filter filter-btn" data-speed="slow" style="color:#f87171;">&gt; 5 ms</button>
                <button class="sql-speed-filter filter-btn" data-speed="n1" style="color:#fb923c;">N+1</button>
                <button class="filter-btn" id="sql-filter-clear" style="color:#94a3b8;">Clear</button>
            </div>
            @if ($totalN1 > 0)
                <div style="background:#451a03;border:1px solid #92400e;border-radius:8px;padding:8px 12px;margin-bottom:12px;font-size:12px;color:#fbbf24;">
                    <strong>N+1 detected:</strong> {{ $totalN1 }} query {{ $totalN1 === 1 ? 'pattern' : 'patterns' }} repeated 3+ times ({{ $totalN1Queries }} queries total). Look for rows marked <span style="color:#fb923c;">N+1</span> below.
                </div>
            @endif
            <table id="sql-grouped-table">
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Operation</th>
                        <th>Count</th>
                        <th>Total time</th>
                        <th style="color:#4ade80;">&lt; 1 ms</th>
                        <th style="color:#facc15;">1-5 ms</th>
                        <th style="color:#f87171;">&gt; 5 ms</th>
                        <th style="color:#fb923c;">N+1</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($grouped as $index => $group)
                        <tr class="sql-group-row" data-table="{{ strtolower($group['table']) }}" data-op="{{ $group['operation'] }}" data-fast="{{ $group['fast'] }}" data-mid="{{ $group['mid'] }}" data-slow="{{ $group['slow'] }}" data-n1="{{ $group['n1_count'] }}" style="cursor:pointer;" onclick="document.getElementById('sql-group-{{ $index }}').style.display = document.getElementById('sql-group-{{ $index }}').style.display === 'none' ? '' : 'none'">
                            <td><strong>{{ $group['table'] }}</strong></td>
                            <td><span class="badge">{{ $group['operation'] }}</span></td>
                            <td>{{ $group['count'] }}</td>
                            <td>{{ number_format($group['total_ms'], 2) }} ms</td>
                            <td style="color:#4ade80;">{{ $group['fast'] ?: '' }}</td>
                            <td style="color:#facc15;">{{ $group['mid'] ?: '' }}</td>
                            <td style="color:#f87171;">{{ $group['slow'] ?: '' }}</td>
                            <td style="color:#fb923c;">
                                @if ($group['n1_count'] > 0)
                                    @foreach ($group['n1_patterns'] as $pattern => $count)
                                        <span title="{{ $pattern }}" style="cursor:help;">{{ $count }}x</span>
                                    @endforeach
                                @endif
                            </td>
                        </tr>
                        <tr id="sql-group-{{ $index }}" style="display:none;">
                            <td colspan="8" style="padding:0;">
                                <table style="margin:0;background:#020617;">
                                    <thead>
                                        <tr>
                                            <th style="font-size:10px;">Time</th>
                                            <th style="font-size:10px;">Connection</th>
                                            <th style="font-size:10px;">SQL</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($group['queries'] as $query)
                                            @php $ms = (float) $query['duration_ms']; @endphp
                                            <tr>
                                                <td style="font-size:11px;white-space:nowrap;color:{{ $ms > 5 ? '#f87171' : ($ms >= 1 ? '#facc15' : '#4ade80') }};">{{ number_format($ms, 2) }} ms</td>
                                                <td style="font-size:11px;">{{ data_get($query, 'meta.connection', 'default') }}</td>
                                                <td style="font-size:11px;"><code>{{ data_get($query, 'meta.sql', $query['label']) }}</code></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <script>
                (function () {
                    var tableFilter = document.getElementById('sql-filter-table');
                    var opButtons = document.querySelectorAll('.sql-op-filter');
                    var speedButtons = document.querySelectorAll('.sql-speed-filter');
                    var clearBtn = document.getElementById('sql-filter-clear');
                    var rows = document.querySelectorAll('.sql-group-row');
                    var activeOp = null;
                    var activeSpeed = null;

                    function applyFilters() {
                        var text = tableFilter.value.toLowerCase();
                        rows.forEach(function (row) {
                            var detailRow = row.nextElementSibling;
                            var matchTable = !text || row.dataset.table.indexOf(text) !== -1;
                            var matchOp = !activeOp || row.dataset.op === activeOp;
                            var matchSpeed = !activeSpeed || parseInt(row.dataset[activeSpeed]) > 0;
                            var visible = matchTable && matchOp && matchSpeed;
                            row.style.display = visible ? '' : 'none';
                            if (detailRow && detailRow.id && detailRow.id.startsWith('sql-group-')) {
                                detailRow.style.display = 'none';
                            }
                        });
                    }

                    tableFilter.addEventListener('input', applyFilters);

                    opButtons.forEach(function (btn) {
                        btn.addEventListener('click', function (e) {
                            e.stopPropagation();
                            if (activeOp === btn.dataset.op) {
                                activeOp = null;
                                btn.style.background = '#1e293b';
                            } else {
                                activeOp = btn.dataset.op;
                                opButtons.forEach(function (b) { b.style.background = '#1e293b'; });
                                btn.style.background = '#334155';
                            }
                            applyFilters();
                        });
                    });

                    speedButtons.forEach(function (btn) {
                        btn.addEventListener('click', function (e) {
                            e.stopPropagation();
                            if (activeSpeed === btn.dataset.speed) {
                                activeSpeed = null;
                                btn.style.background = '#1e293b';
                            } else {
                                activeSpeed = btn.dataset.speed;
                                speedButtons.forEach(function (b) { b.style.background = '#1e293b'; });
                                btn.style.background = '#334155';
                            }
                            applyFilters();
                        });
                    });

                    clearBtn.addEventListener('click', function () {
                        tableFilter.value = '';
                        activeOp = null;
                        activeSpeed = null;
                        opButtons.forEach(function (b) { b.style.background = '#1e293b'; });
                        speedButtons.forEach(function (b) { b.style.background = '#1e293b'; });
                        applyFilters();
                    });
                })();
            </script>
        @endif
        </div>
    </div>

    <div class="card">
        <h2 class="pane-toggle" data-pane="flamegraph">Excimer flamegraph</h2>
        <div class="pane-body" id="pane-flamegraph">
        @if ($flamegraphTree === [])
            <p class="muted" style="margin-bottom:0;">No Excimer sample data is available for this request yet.</p>
        @else
            <div style="margin-bottom:8px;display:flex;gap:8px;align-items:center;">
                <input type="text" id="flamegraph-search" placeholder="Search frames..." style="flex:1;padding:6px 10px;border:1px solid #1f2937;border-radius:4px;font-family:inherit;font-size:inherit;background:#020617;color:#e2e8f0;">
                <button id="flamegraph-reset" style="padding:6px 14px;border:1px solid #1f2937;border-radius:4px;background:#1e293b;color:#e2e8f0;cursor:pointer;font-family:inherit;">Reset zoom</button>
            </div>
            <div id="flamegraph-chart"></div>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/d3-flame-graph@4/dist/d3-flamegraph.css">
            <script type="text/javascript" src="https://d3js.org/d3.v7.min.js"></script>
            <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/d3-flame-graph@4/dist/d3-flamegraph.min.js"></script>
            <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/d3-flame-graph@4/dist/d3-flamegraph-tooltip.min.js"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var data = @json($flamegraphTree);
                    var tip = flamegraph.tooltip.defaultFlamegraphTooltip()
                        .text(function (d) { return d.data.name + ' (' + d.data.value + ' samples)'; });
                    var chart = flamegraph()
                        .width(document.getElementById('flamegraph-chart').offsetWidth)
                        .cellHeight(24)
                        .tooltip(tip)
                        .setColorMapper(function (d, originalColor) {
                            return 'hsl(' + (Math.abs(hashCode(d.data.name)) % 360) + ', 70%, 55%)';
                        });

                    d3.select('#flamegraph-chart').datum(data).call(chart);

                    function dimNonMatching(term) {
                        var el = document.getElementById('flamegraph-chart');
                        var groups = el.querySelectorAll('g[name]');
                        var escaped = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                        var re = new RegExp(escaped, 'i');
                        groups.forEach(function (g) {
                            var name = g.getAttribute('name') || '';
                            var rect = g.querySelector('rect');
                            if (!rect) return;
                            if (re.test(name)) {
                                rect.style.fill = '#E600E6';
                                rect.style.opacity = '1';
                            } else {
                                rect.style.fill = '';
                                rect.style.opacity = '0.2';
                            }
                        });
                    }

                    function resetDimming() {
                        var el = document.getElementById('flamegraph-chart');
                        var rects = el.querySelectorAll('g[name] rect');
                        rects.forEach(function (rect) {
                            rect.style.fill = '';
                            rect.style.opacity = '';
                        });
                    }

                    document.getElementById('flamegraph-search').addEventListener('input', function () {
                        var term = this.value;
                        if (term) {
                            dimNonMatching(term);
                        } else {
                            resetDimming();
                        }
                    });

                    document.getElementById('flamegraph-reset').addEventListener('click', function () {
                        chart.resetZoom();
                        document.getElementById('flamegraph-search').value = '';
                        resetDimming();
                    });

                    function hashCode(str) {
                        var hash = 0;
                        for (var i = 0; i < str.length; i++) {
                            hash = ((hash << 5) - hash) + str.charCodeAt(i);
                            hash |= 0;
                        }
                        return hash;
                    }
                });
            </script>
        @endif
        </div>
    </div>

    <script>
        document.querySelectorAll('.pane-toggle').forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                var pane = document.getElementById('pane-' + toggle.dataset.pane);
                var collapsed = !pane.classList.contains('collapsed');
                pane.classList.toggle('collapsed', collapsed);
                toggle.classList.toggle('collapsed', collapsed);
                localStorage.setItem('profiler-pane-' + toggle.dataset.pane, collapsed ? '0' : '1');
            });
            var saved = localStorage.getItem('profiler-pane-' + toggle.dataset.pane);
            if (saved === '0') {
                document.getElementById('pane-' + toggle.dataset.pane).classList.add('collapsed');
                toggle.classList.add('collapsed');
            }
        });
    </script>
@endsection