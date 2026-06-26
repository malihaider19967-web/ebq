{{-- One Errors/Warnings/Notices section of the Site Audit PDF. Expects
     $key, $heading, $lede, $cls, $items (each an auditExport() row). --}}
<div class="section-divider sec-{{ $key }}">
    <p class="section-title">{{ $heading }} ({{ array_sum(array_column($items, 'count')) }})</p>
    <p class="lede">{{ $lede }}</p>
</div>

@if ($items === [])
    <p class="empty-state">No {{ strtolower($heading) }} found — nice work.</p>
@else
    @foreach ($items as $item)
        <div class="issue-block">
            <div class="issue-head">
                {{ $item['label'] }}
                <span class="issue-count {{ $cls }}">{{ $item['count'] }}</span>
                @if ($item['new_count'] > 0)
                    <span class="new-badge">+{{ $item['new_count'] }} new</span>
                @endif
                @if ($item['gsc_sourced'])
                    <span class="gsc-badge">From Search Console</span>
                @endif
            </div>

            @if ($item['gsc_sourced'])
                <p class="gsc-note">Based on Search Console history, not our own crawl — this can lag the live site by a few days.</p>
            @endif

            <p class="issue-label">About this issue</p>
            <p class="issue-body">{{ $item['about'] }}</p>
            <p class="issue-label">How to fix</p>
            <p class="issue-fix">{{ $item['fix'] }}</p>

            @if ($item['sample_urls'] !== [])
                <p class="issue-label">Affected pages</p>
                <ul class="url-list">
                    @foreach (array_slice($item['sample_urls'], 0, 10) as $u)
                        <li>{{ $u }}</li>
                    @endforeach
                    @if ($item['count'] > 10)
                        <li>+ {{ $item['count'] - 10 }} more (see the dashboard for the full list)</li>
                    @endif
                </ul>
            @endif
        </div>
    @endforeach
@endif
