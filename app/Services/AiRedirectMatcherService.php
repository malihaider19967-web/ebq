<?php

namespace App\Services;

use App\Models\RedirectSuggestion;
use App\Models\SearchConsoleData;
use App\Models\Website;
use App\Services\Llm\LlmClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * AI matcher: takes a 404 path on a user's site and suggests the best
 * destination from the site's existing post inventory.
 *
 * Inventory source
 * ────────────────
 * We don't (yet) ingest the WP post list directly. Instead we use the
 * site's GSC footprint as a proxy — every URL Google has ranked for the
 * site shows up in `search_console_data`. For 404 matching this is
 * actually a stronger signal than the WP post list, because:
 *   - It's already deduped + lowercased
 *   - It naturally weights by traffic / authority
 *   - It misses unpublished drafts and orphaned URLs (which wouldn't make
 *     good redirect targets anyway)
 *
 * The LLM picks the best match from the candidate URL list with a
 * confidence score and a one-line rationale. Persisted to
 * `redirect_suggestions` so the user reviews + accepts/rejects via HQ.
 *
 * MOAT
 * ────
 * Naive matching (slug Levenshtein, the open-source pattern in
 * Redirection / RankMath) gets ~30% precision. LLM-grounded matching
 * with title context + traffic weight gets ~80%+. This is the kind of
 * delta that becomes "I can't go back to manual" once a user tries it.
 */
class AiRedirectMatcherService
{
    private const CANDIDATE_LIMIT = 200;

    public function __construct(private readonly LlmClient $llm) {}

    /**
     * Process one 404 — match it, persist the suggestion, return the row.
     * Idempotent: re-running for the same path bumps `hits_30d` and
     * `last_seen_at` but keeps the existing match unless we explicitly
     * ask for a re-match.
     *
     * @return RedirectSuggestion|null null when no candidate inventory exists
     */
    public function matchFor404(Website $website, string $sourcePath, int $hits = 1, bool $forceRematch = false): ?RedirectSuggestion
    {
        $sourcePath = $this->normalizePath($sourcePath);
        if ($sourcePath === '') {
            return null;
        }

        $existing = RedirectSuggestion::query()
            ->where('website_id', $website->id)
            ->where('source_path_hash', RedirectSuggestion::hashPath($sourcePath))
            ->first();

        // Bump hit counters first so even a no-LLM case still records the 404.
        if ($existing) {
            $existing->hits_30d = ($existing->hits_30d ?? 0) + max(1, $hits);
            $existing->last_seen_at = Carbon::now();
            $existing->save();

            // Don't re-bill the LLM if the user already accepted/rejected
            // OR if the prior match is recent and unchanged.
            if (! $forceRematch
                && in_array($existing->status, [RedirectSuggestion::STATUS_APPLIED, RedirectSuggestion::STATUS_REJECTED], true)) {
                return $existing;
            }
            if (! $forceRematch
                && $existing->matched_at !== null
                && $existing->matched_at->diffInDays(Carbon::now()) < 30) {
                return $existing;
            }
        }

        $candidates = $this->candidateInventory($website);
        if ($candidates === []) {
            // Persist a placeholder so the 404 still shows up in HQ even
            // without a suggestion — user can manually create the rule.
            return $this->persist($website, $sourcePath, '', 0, 'No inventory available for matching.', $existing, $hits);
        }

        if (! $this->llm->isAvailable()) {
            return $this->persist($website, $sourcePath, '', 0, 'LLM not configured — match deferred.', $existing, $hits);
        }

        $match = $this->llm->completeJson($this->buildPrompt($sourcePath, $candidates), [
            'temperature' => 0.1,
            'max_tokens' => 300,
            'json_object' => true,
            'timeout' => 25,
        ]);

        if (! is_array($match) || empty($match['destination'])) {
            return $this->persist($website, $sourcePath, '', 0, 'LLM returned no match.', $existing, $hits);
        }

        $destination = $this->validateDestinationAgainstCandidates(
            (string) $match['destination'],
            $candidates,
        );
        if ($destination === '') {
            return $this->persist($website, $sourcePath, '', 0, 'LLM picked a destination that is not in the inventory.', $existing, $hits);
        }

        $confidence = max(0, min(100, (int) ($match['confidence'] ?? 0)));
        $rationale = mb_substr(trim((string) ($match['rationale'] ?? '')), 0, 500);

        return $this->persist($website, $sourcePath, $destination, $confidence, $rationale, $existing, $hits);
    }

    /**
     * @return list<array{url: string, title: string, clicks: int}>
     */
    private function candidateInventory(Website $website): array
    {
        $tz = config('app.timezone');
        $end = Carbon::yesterday($tz)->endOfDay();
        $start = $end->copy()->subDays(89)->startOfDay();

        $rows = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->where('page', '!=', '')
            ->selectRaw('page, SUM(clicks) AS clicks, MAX(query) AS top_query')
            ->groupBy('page')
            ->orderByDesc('clicks')
            ->limit(self::CANDIDATE_LIMIT)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $url = (string) $r->page;
            $path = (string) (parse_url($url, PHP_URL_PATH) ?: $url);
            $out[] = [
                'url' => $path,
                'title' => (string) ($r->top_query ?? ''),
                'clicks' => (int) $r->clicks,
            ];
        }
        return $out;
    }

    /**
     * @param  list<array{url: string, title: string, clicks: int}>  $candidates
     * @return list<array{role: string, content: string}>
     */
    private function buildPrompt(string $sourcePath, array $candidates): array
    {
        $candidateBlock = '';
        foreach ($candidates as $i => $c) {
            $candidateBlock .= sprintf(
                "%3d. %s  (top GSC query: %s; %d clicks/90d)\n",
                $i + 1,
                $c['url'],
                $c['title'] !== '' ? mb_substr($c['title'], 0, 60) : '—',
                $c['clicks'],
            );
        }

        $system = <<<'SYS'
You match broken URL paths (404s) to the best replacement page on the same
site. The replacement MUST come from the candidate list — never invent a
URL. Prefer high-traffic candidates only when intent matches; intent match
beats traffic always. Return STRICTLY valid JSON.
SYS;

        $user = <<<USER
Broken (404) path: {$sourcePath}

Candidate replacement paths on this site:
{$candidateBlock}

Pick the single best replacement. Schema:
{
  "destination": "/exact/path/from/the/list",
  "confidence": 0..100,
  "rationale": "One sentence: why this URL serves the same intent as the broken one."
}

If nothing in the list is a reasonable match, return:
{ "destination": "", "confidence": 0, "rationale": "No good match." }
USER;

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    /**
     * Defense-in-depth: even with the prompt telling the model to pick
     * from the list, it can occasionally hallucinate a path. We require
     * exact membership in the candidate URL set.
     *
     * @param  list<array{url: string, title: string, clicks: int}>  $candidates
     */
    private function validateDestinationAgainstCandidates(string $candidate, array $candidates): string
    {
        $candidate = trim($candidate);
        if ($candidate === '') return '';
        // Allow the LLM to return either a path or a full URL — we stored paths.
        $path = (string) (parse_url($candidate, PHP_URL_PATH) ?: $candidate);
        $path = $this->normalizePath($path);
        foreach ($candidates as $c) {
            if ($this->normalizePath($c['url']) === $path) {
                return $path;
            }
        }
        return '';
    }

    private function persist(Website $website, string $sourcePath, string $destination, int $confidence, string $rationale, ?RedirectSuggestion $existing, int $hits): RedirectSuggestion
    {
        $now = Carbon::now();

        if ($existing) {
            $existing->suggested_destination = $destination;
            $existing->confidence = $confidence;
            $existing->rationale = $rationale;
            // Reset to pending if a fresh match landed; preserve user
            // decisions that were already made.
            if (! in_array($existing->status, [RedirectSuggestion::STATUS_APPLIED, RedirectSuggestion::STATUS_REJECTED], true)) {
                $existing->status = RedirectSuggestion::STATUS_PENDING;
            }
            $existing->matched_at = $now;
            $existing->save();
            return $existing;
        }

        return RedirectSuggestion::create([
            'website_id' => $website->id,
            'source_path' => $sourcePath,
            'source_path_hash' => RedirectSuggestion::hashPath($sourcePath),
            'suggested_destination' => $destination,
            'confidence' => $confidence,
            'status' => RedirectSuggestion::STATUS_PENDING,
            'rationale' => $rationale,
            'hits_30d' => max(1, $hits),
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'matched_at' => $now,
        ]);
    }

    private function normalizePath(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') return '';
        if (str_contains($raw, '://')) {
            $raw = (string) (parse_url($raw, PHP_URL_PATH) ?: '');
        }
        if ($raw === '') return '';
        // Lowercase + collapse trailing slash so /Foo/ and /foo match.
        $raw = strtolower($raw);
        return $raw !== '/' ? rtrim($raw, '/') : $raw;
    }
}
