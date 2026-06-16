<?php

namespace App\Jobs;

use App\Mail\GuestRankCheckLinkMail;
use App\Models\GuestRankCheck;
use App\Services\SerperSearchClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Runs ONE public, no-signup keyword rank check.
 *
 * A single Serper organic query (up to 100 results) is enough to locate the
 * target domain's position, so — unlike the PageSpeed test — this is one job,
 * not a mobile/desktop pair. On the email-gated 2nd check the parsed result
 * link is delivered by {@see GuestRankCheckLinkMail} once the lookup lands.
 */
class RunGuestRankCheck implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** How deep into the SERP we scan for the target domain. */
    private const DEPTH = 100;

    /** How many top results we keep for the on-screen / emailed report. */
    private const TOP_KEEP = 20;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 1800;

    public function __construct(public readonly int $id)
    {
        $this->onQueue(\App\Support\Queues::INTERACTIVE);
    }

    public function uniqueId(): string
    {
        return 'guest-rank-check:'.$this->id;
    }

    public function handle(SerperSearchClient $serper): void
    {
        $row = GuestRankCheck::query()->find($this->id);
        if (! $row instanceof GuestRankCheck || $row->isFinished()) {
            return;
        }
        if ($row->status === GuestRankCheck::STATUS_QUEUED) {
            $row->markRunning();
        }

        $json = $serper->search(
            query: $row->keyword,
            num: self::DEPTH,
            gl: $row->country,
            hl: null,
            websiteId: null,
            ownerUserId: null,
            source: 'guest_rank_tracker',
        );

        if (! is_array($json)) {
            $row->markFailed('We couldn’t fetch search results for that keyword. Please try again in a moment.');

            return;
        }

        $row->markCompleted($this->parse($json, $row));

        if ($row->email) {
            try {
                Mail::to($row->email)->send(new GuestRankCheckLinkMail($row));
            } catch (\Throwable $e) {
                Log::warning('RunGuestRankCheck: email failed: '.$e->getMessage());
            }
        }
    }

    /** Timeout / exception: still record a failure so the poller stops waiting. */
    public function failed(?\Throwable $e): void
    {
        $row = GuestRankCheck::query()->find($this->id);
        if ($row instanceof GuestRankCheck && ! $row->isFinished()) {
            $row->markFailed('The rank check timed out. Please try again.');
        }
    }

    /**
     * Locate the target domain in the organic results and keep the top slice
     * for display.
     *
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function parse(array $json, GuestRankCheck $row): array
    {
        $results = is_array($json['organic'] ?? null) ? $json['organic'] : [];
        $targetDomain = $this->normalizeDomain($row->domain);

        $position = null;
        $foundUrl = null;
        $foundTitle = null;
        $top = [];

        foreach ($results as $idx => $result) {
            if (! is_array($result)) {
                continue;
            }
            $link = (string) ($result['link'] ?? $result['url'] ?? '');
            $rank = (int) ($result['position'] ?? ($idx + 1));
            $linkDomain = $this->normalizeDomain($link);
            $isTarget = $linkDomain !== '' && $linkDomain === $targetDomain;

            if ($isTarget && $position === null) {
                $position = $rank;
                $foundUrl = $link;
                $foundTitle = (string) ($result['title'] ?? '');
            }

            if (count($top) < self::TOP_KEEP) {
                $top[] = [
                    'position' => $rank,
                    'title' => mb_substr((string) ($result['title'] ?? ''), 0, 300),
                    'link' => $link,
                    'domain' => $linkDomain,
                    'snippet' => mb_substr((string) ($result['snippet'] ?? $result['description'] ?? ''), 0, 300),
                    'is_target' => $isTarget,
                ];
            }
        }

        return [
            'keyword' => $row->keyword,
            'domain' => $targetDomain,
            'country' => $row->country,
            'position' => $position,
            'found_url' => $foundUrl,
            'found_title' => $foundTitle,
            'depth' => self::DEPTH,
            'scanned' => count($results),
            'results' => $top,
            'checked_at' => now()->toIso8601String(),
            'source' => 'serper',
        ];
    }

    /** Reduce a URL or bare host to a comparable registrable host (no scheme/www). */
    private function normalizeDomain(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (! str_contains($value, '://')) {
            $value = 'http://'.$value;
        }
        $host = parse_url($value, PHP_URL_HOST);
        if (! is_string($host)) {
            return '';
        }
        $host = strtolower($host);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host;
    }
}
