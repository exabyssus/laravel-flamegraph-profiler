<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Profiler' }}</title>
    <style>
        :root { color-scheme: light dark; }
        body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; margin: 0; background: #0f172a; color: #e2e8f0; }
        a { color: #7dd3fc; text-decoration: none; }
        .page { max-width: 1200px; margin: 0 auto; padding: 32px 24px 48px; }
        .muted { color: #94a3b8; }
        .grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
        .card { background: #111827; border: 1px solid #1f2937; border-radius: 14px; padding: 16px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.25); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border-bottom: 1px solid #1f2937; text-align: left; vertical-align: top; }
        button { appearance: none; -webkit-appearance: none; }
        .badge { display: inline-block; border-radius: 999px; padding: 3px 10px; font-size: 12px; background: #1e293b; }
        .btn { display: inline-block; padding: 8px 18px; background: #1e293b; color: #e2e8f0; border: 1px solid #334155; border-radius: 8px; font-size: 13px; font-family: inherit; text-decoration: none; }
        .btn:hover { background: #334155; }
        .filter-btn { cursor: pointer; border: 1px solid #334155; background: #1e293b; color: #e2e8f0; font-size: 11px; padding: 3px 10px; border-radius: 999px; font-family: inherit; }
        .timeline { position: relative; border: 1px solid #1f2937; background: #020617; border-radius: 14px; }
        .timeline-row { position: relative; height: 22px; border-bottom: 1px solid #111827; }
        .timeline-row:last-child { border-bottom: none; }
        .timeline-label { width: 220px; padding: 2px 8px; position: relative; z-index: 2; font-size: 10px; line-height: 18px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: default; }
        .timeline-row:hover { z-index: 10; }
        .timeline-label:hover .timeline-popup { display: block; }
        .timeline-popup { display: none; position: absolute; left: 0; top: 100%; z-index: 20; background: #1e293b; border: 1px solid #334155; border-radius: 6px; padding: 6px 10px; font-size: 11px; white-space: pre-wrap; word-break: break-all; max-width: 500px; min-width: 240px; box-shadow: 0 8px 24px rgba(0,0,0,0.4); }
        .timeline-track { position: absolute; inset: 2px 8px 2px 230px; }
        .timeline-bar, .flame-bar { position: absolute; top: 2px; bottom: 2px; border-radius: 4px; overflow: hidden; }
        .timeline-bar { background: linear-gradient(90deg, #38bdf8, #2563eb); }
        .flamegraph { border: 1px solid #1f2937; border-radius: 14px; overflow: hidden; background: #020617; }
        .flame-row { position: relative; height: 34px; border-bottom: 1px solid #111827; }
        .flame-row:last-child { border-bottom: none; }
        .flame-bar { color: #0f172a; font-size: 11px; white-space: nowrap; text-overflow: ellipsis; padding: 6px 8px; }
        code { font-family: ui-monospace, SFMono-Regular, monospace; font-size: 12px; }
        .pane-toggle { cursor: pointer; display: flex; align-items: center; gap: 8px; user-select: none; margin-top: 0; }
        .pane-toggle::before { content: '\25BC'; font-size: 10px; transition: transform 0.15s; }
        .pane-toggle.collapsed::before { transform: rotate(-90deg); }
        .pane-body { overflow: hidden; }
        .pane-body.collapsed { display: none; }
        #flamegraph-chart .d3-flame-graph rect { stroke: #0f172a; }
        #flamegraph-chart .d3-flame-graph .d3-flame-graph-label { font-size: 11px; fill: #0f172a; }
    </style>
</head>
<body>
    <div class="page">
        <div style="display:flex;gap:16px;align-items:center;margin-bottom:24px;flex-wrap:wrap;">
            @hasSection('back')
                <a href="{{ route('laravel-profiler.index') }}" class="btn">&larr; Recent requests</a>
            @endif
            <h1 style="margin:0;font-size:20px;flex:1;">{{ $title ?? 'Profiler' }}</h1>
            @yield('header-actions')
        </div>

        @yield('content')
    </div>
</body>
</html>
