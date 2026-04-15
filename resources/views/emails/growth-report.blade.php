<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #1e293b; line-height: 1.6; margin: 0; padding: 0; background: #f1f5f9; }
        .container { max-width: 640px; margin: 0 auto; padding: 32px 16px; }
        .card { background: #ffffff; border-radius: 8px; padding: 32px; }
        h1 { font-size: 20px; margin: 0 0 8px; }
        .meta { color: #64748b; margin: 0 0 20px; font-size: 14px; }
        .greeting { color: #64748b; margin: 0 0 24px; font-size: 14px; }
        .stats { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 28px; }
        .stat { background: #f8fafc; border-radius: 6px; padding: 16px; flex: 1 1 120px; text-align: center; }
        .stat-value { font-size: 22px; font-weight: 700; color: #4f46e5; }
        .stat-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; margin-top: 4px; }
        h2 { font-size: 15px; margin: 0 0 12px; color: #0f172a; }
        .bl-table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 8px; }
        .bl-table th { text-align: left; padding: 8px 6px; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 10px; letter-spacing: 0.04em; }
        .bl-table td { padding: 10px 6px; border-bottom: 1px solid #f1f5f9; vertical-align: top; word-break: break-word; }
        .bl-empty { color: #94a3b8; font-size: 13px; margin: 0 0 24px; }
        .btn { display: inline-block; background: #4f46e5; color: #ffffff !important; text-decoration: none; border-radius: 6px; padding: 10px 24px; font-size: 14px; font-weight: 500; }
        .footer { text-align: center; color: #94a3b8; font-size: 12px; margin-top: 24px; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>GrowthHub daily report</h1>
        <p class="meta"><strong>{{ $website->domain }}</strong> &mdash; {{ $reportDate->format('l, F j, Y') }}</p>
        <p class="greeting">Hello {{ $user->name }}, here is the performance summary for this site on the date above.</p>

        <div class="stats">
            <div class="stat">
                <div class="stat-value">{{ number_format($stats['clicks']) }}</div>
                <div class="stat-label">Clicks</div>
            </div>
            <div class="stat">
                <div class="stat-value">{{ number_format($stats['impressions']) }}</div>
                <div class="stat-label">Impressions</div>
            </div>
            <div class="stat">
                <div class="stat-value">{{ number_format($stats['users']) }}</div>
                <div class="stat-label">Users</div>
            </div>
            <div class="stat">
                <div class="stat-value">{{ number_format($stats['sessions']) }}</div>
                <div class="stat-label">Sessions</div>
            </div>
        </div>

        <h2>Backlinks recorded for this date</h2>
        @if ($backlinks->isEmpty())
            <p class="bl-empty">No backlinks were recorded for this date.</p>
        @else
            <table class="bl-table" role="presentation">
                <thead>
                    <tr>
                        <th>Referring page</th>
                        <th>Target</th>
                        <th>Type</th>
                        <th>DA</th>
                        <th>Follow</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($backlinks as $b)
                        <tr>
                            <td><a href="{{ $b->referring_page_url }}">{{ \Illuminate\Support\Str::limit($b->referring_page_url, 48) }}</a></td>
                            <td><a href="{{ $b->target_page_url }}">{{ \Illuminate\Support\Str::limit($b->target_page_url, 40) }}</a></td>
                            <td>{{ $b->type->label() }}</td>
                            <td>{{ $b->domain_authority ?? '—' }}</td>
                            <td>{{ $b->is_dofollow ? 'Do' : 'No' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($backlinks->count() >= 100)
                <p class="bl-empty" style="margin-top: 0;">Showing the first 100 backlinks for this date.</p>
            @endif
        @endif

        <a href="{{ route('dashboard') }}" class="btn">Open dashboard</a>
    </div>
    <p class="footer">Sent by GrowthHub &mdash; {{ now()->format('M d, Y') }}</p>
</div>
</body>
</html>
