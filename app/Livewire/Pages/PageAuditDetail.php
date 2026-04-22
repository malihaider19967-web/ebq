<?php

namespace App\Livewire\Pages;

use App\Mail\PageAuditReportMail;
use App\Models\PageAuditReport;
use App\Models\RankTrackingKeyword;
use App\Services\PluginInsightResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

class PageAuditDetail extends Component
{
    public PageAuditReport $pageAuditReport;

    public string $auditEmail = '';

    public ?string $auditMessage = null;

    public string $auditMessageKind = 'info';

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user && $user->canViewWebsiteId($this->pageAuditReport->website_id), 403);
    }

    public function emailAuditReport(): void
    {
        $this->auditMessage = null;

        $user = Auth::user();
        if (! $user || ! $user->canViewWebsiteId($this->pageAuditReport->website_id)) {
            $this->setAuditMessage('You do not have permission to email this report.', 'error');

            return;
        }

        $rateKey = 'email-audit:'.$user->id;
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);
            $this->setAuditMessage("Too many emails. Try again in {$seconds}s.", 'error');

            return;
        }

        $validator = Validator::make(['email' => $this->auditEmail], ['email' => ['required', 'email']]);
        if ($validator->fails()) {
            $this->setAuditMessage('Please enter a valid email address.', 'error');

            return;
        }

        try {
            RateLimiter::hit($rateKey, 300);
            Mail::to($this->auditEmail)->send(new PageAuditReportMail($this->pageAuditReport, $user));
            $this->setAuditMessage("Audit report emailed to {$this->auditEmail}.", 'success');
            $this->auditEmail = '';
        } catch (\Throwable $e) {
            $this->setAuditMessage('Failed to send email: '.$e->getMessage(), 'error');
        }
    }

    public function render()
    {
        $breakdown = app(PluginInsightResolver::class)->countryBreakdown(
            $this->pageAuditReport->website,
            (string) $this->pageAuditReport->page,
            null,
            10,
        );

        $rows = $breakdown['by_country'];
        $totalClicks = array_sum(array_map(fn ($r) => (int) $r['clicks'], $rows));
        $totalImpr = array_sum(array_map(fn ($r) => (int) $r['impressions'], $rows));
        $maxClicks = max(1, (int) (collect($rows)->max('clicks') ?? 0));

        $rows = array_map(function ($r) use ($totalClicks, $maxClicks) {
            $name = \App\Support\Countries::name((string) $r['country']);
            $title = $name.' · '.number_format((int) $r['impressions']).' impressions';
            if ($r['position'] !== null) {
                $title .= ' · avg position '.$r['position'];
            }

            return $r + [
                'name' => $name,
                'flag' => \App\Support\Countries::flag((string) $r['country']),
                'width_pct' => max(2, (int) round(((int) $r['clicks'] / $maxClicks) * 100)),
                'share_pct' => $totalClicks > 0 ? round(((int) $r['clicks'] / $totalClicks) * 100, 1) : 0.0,
                'hover_title' => $title,
            ];
        }, $rows);

        return view('livewire.pages.page-audit-detail', [
            'trackedRankings' => $this->trackedRankingsForAudit(),
            'countryBreakdown' => $rows,
            'countryTotals' => [
                'clicks' => $totalClicks,
                'impressions' => $totalImpr,
                'markets' => count($rows),
            ],
        ]);
    }

    /**
     * Gather tracked rank-tracker keywords that match this audited page.
     *
     * Primary bucket: keywords whose latest-checked `current_url` resolves to
     * the same page (host+path). Secondary bucket: other active keywords on
     * the same site that are currently ranked.
     *
     * @return array{on_this_page: list<array<string, mixed>>, on_site: list<array<string, mixed>>}
     */
    private function trackedRankingsForAudit(): array
    {
        $auditedUrl = (string) $this->pageAuditReport->page;
        $auditedHost = $this->hostFor($auditedUrl);
        $auditedPath = $this->pathFor($auditedUrl);

        if ($auditedHost === '') {
            return ['on_this_page' => [], 'on_site' => []];
        }

        $keywords = RankTrackingKeyword::query()
            ->where('website_id', $this->pageAuditReport->website_id)
            ->where(function ($q) use ($auditedHost) {
                $q->whereRaw('LOWER(target_domain) = ?', [$auditedHost])
                    ->orWhereRaw('LOWER(target_domain) = ?', ['www.'.$auditedHost])
                    ->orWhereRaw('LOWER(target_domain) LIKE ?', ['%'.$auditedHost]);
            })
            ->orderByRaw('CASE WHEN current_position IS NULL THEN 1 ELSE 0 END')
            ->orderBy('current_position')
            ->limit(100)
            ->get();

        $onThisPage = [];
        $onSite = [];

        foreach ($keywords as $kw) {
            $rankedUrl = (string) ($kw->current_url ?? '');
            $rankedHost = $this->hostFor($rankedUrl);
            $rankedPath = $this->pathFor($rankedUrl);

            $entry = [
                'id' => $kw->id,
                'keyword' => $kw->keyword,
                'country' => $kw->country,
                'language' => $kw->language,
                'device' => $kw->device,
                'search_type' => $kw->search_type,
                'position' => $kw->current_position,
                'best' => $kw->best_position,
                'change' => $kw->position_change,
                'url' => $rankedUrl ?: null,
                'last_checked_at' => $kw->last_checked_at,
                'is_active' => (bool) $kw->is_active,
            ];

            if ($kw->current_position !== null
                && $rankedHost !== ''
                && $rankedHost === $auditedHost
                && $rankedPath === $auditedPath) {
                $onThisPage[] = $entry;
            } elseif ($kw->current_position !== null) {
                $onSite[] = $entry;
            }
        }

        return ['on_this_page' => $onThisPage, 'on_site' => $onSite];
    }

    private function hostFor(string $url): string
    {
        $u = trim($url);
        if ($u === '') {
            return '';
        }
        if (! str_contains($u, '://')) {
            $u = 'http://'.$u;
        }
        $host = parse_url($u, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return '';
        }
        $host = strtolower($host);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host;
    }

    private function pathFor(string $url): string
    {
        $u = trim($url);
        if ($u === '') {
            return '';
        }
        if (! str_contains($u, '://')) {
            $u = 'http://'.$u;
        }
        $path = parse_url($u, PHP_URL_PATH);
        $path = is_string($path) ? rtrim($path, '/') : '';

        return $path === '' ? '/' : $path;
    }

    private function setAuditMessage(string $message, string $kind = 'info'): void
    {
        $this->auditMessage = $message;
        $this->auditMessageKind = in_array($kind, ['success', 'info', 'error'], true) ? $kind : 'info';
    }
}
