<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #1e293b; line-height: 1.6; margin: 0; padding: 0; background: #f1f5f9; }
        .container { max-width: 600px; margin: 0 auto; padding: 32px 16px; }
        .card { background: #ffffff; border-radius: 8px; padding: 32px; }
        h1 { font-size: 20px; margin: 0 0 8px; }
        .greeting { color: #64748b; margin: 0 0 24px; font-size: 14px; }
        .stats { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 24px; }
        .stat { background: #f8fafc; border-radius: 6px; padding: 16px; flex: 1 1 120px; text-align: center; }
        .stat-value { font-size: 22px; font-weight: 700; color: #4f46e5; }
        .stat-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; margin-top: 4px; }
        .websites { font-size: 13px; color: #64748b; margin-bottom: 24px; }
        .btn { display: inline-block; background: #4f46e5; color: #ffffff !important; text-decoration: none; border-radius: 6px; padding: 10px 24px; font-size: 14px; font-weight: 500; }
        .footer { text-align: center; color: #94a3b8; font-size: 12px; margin-top: 24px; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>GrowthHub Daily Report</h1>
        <p class="greeting">Hello {{ $user->name }}, here's your performance summary.</p>

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

        @if (count($stats['websites']))
            <p class="websites">Tracking: {{ implode(', ', $stats['websites']) }}</p>
        @endif

        <a href="{{ route('dashboard') }}" class="btn">Open Dashboard</a>
    </div>
    <p class="footer">Sent by GrowthHub &mdash; {{ now()->format('M d, Y') }}</p>
</div>
</body>
</html>
