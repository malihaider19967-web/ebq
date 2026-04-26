<?php

namespace App\Services;

use App\Models\Backlink;
use App\Models\CompetitorBacklink;
use App\Models\OutreachProspect;
use App\Models\Website;
use App\Services\Llm\LlmClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Phase 3 #10 — Backlink prospecting.
 *
 * Finds referring DOMAINS that link to competitor pages but NOT to the
 * user's site. These are the highest-quality outreach targets: someone
 * already considered the competitor a citable source for content in your
 * niche, so the door is open. The service ranks them by domain authority
 * and link velocity, and (Pro tier) drafts an outreach email per domain
 * via the LLM.
 *
 * Inputs:
 *   - `competitor_backlinks` table — populated by `CompetitorBacklinkService`
 *     across the network whenever any user audits a page (data gravity:
 *     the more sites on EBQ, the more competitor coverage we already have).
 *   - `backlinks` table — the user's OWN backlinks, populated by
 *     `OwnBacklinkSyncService` from KE.
 *
 * Output: ranked prospect list, with reach signal (DA), context (which
 * competitor they linked to), and an optional AI-drafted outreach email.
 *
 * Caching: 6h per (website × competitors hash). Keeps the prospect list
 * stable enough that the user can work through it in a session without
 * the underlying ranking jumping mid-review.
 *
 * MOAT
 * ────
 * Network-effect feature: every other EBQ user's audits enrich the
 * `competitor_backlinks` set. A first-day user already gets prospects
 * for any competitor that's been audited by anyone on the platform.
 */
class BacklinkProspectingService
{
    private const CACHE_TTL_HOURS = 6;
    private const MAX_PROSPECTS = 100;

    public function __construct(private readonly LlmClient $llm) {}

    /**
     * @param  list<string>  $competitorDomains
     * @return array{
     *   prospects: list<array{
     *     domain: string,
     *     domain_authority: int|null,
     *     linked_to: list<string>,
     *     anchor_examples: list<string>,
     *     last_seen_at: string|null,
     *   }>,
     *   summary: array{competitors_analyzed: int, prospect_count: int, your_existing_links: int},
     *   cached: bool,
     * }
     */
    public function prospect(Website $website, array $competitorDomains): array
    {
        $competitorDomains = array_values(array_unique(array_filter(array_map(
            fn ($d) => CompetitorBacklink::extractDomain((string) $d),
            $competitorDomains,
        ))));
        if ($competitorDomains === []) {
            return ['prospects' => [], 'summary' => ['competitors_analyzed' => 0, 'prospect_count' => 0, 'your_existing_links' => 0], 'cached' => false];
        }
        sort($competitorDomains);

        $cacheKey = sprintf('ebq_backlink_prospects:%d:%s', $website->id, hash('xxh3', implode('|', $competitorDomains)));
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            $cached['cached'] = true;
            return $cached;
        }

        // 1) Domains we already have backlinks from — exclude these.
        $ownedDomains = $this->ownedReferringDomains($website->id);

        // 2) Pull every cached competitor backlink for the supplied
        //    competitor list. CompetitorBacklink has DA on each row.
        $rows = CompetitorBacklink::query()
            ->whereIn('competitor_domain', $competitorDomains)
            ->get();

        // 3) Group by referring DOMAIN, dedupe across competitors, attach
        //    the highest DA seen + the list of competitors it linked to.
        $prospects = [];
        foreach ($rows as $row) {
            $refDomain = $this->extractRefDomain($row);
            if ($refDomain === '' || isset($ownedDomains[$refDomain])) continue;

            if (! isset($prospects[$refDomain])) {
                $prospects[$refDomain] = [
                    'domain' => $refDomain,
                    'domain_authority' => is_numeric($row->domain_authority) ? (int) $row->domain_authority : null,
                    'linked_to' => [],
                    'anchor_examples' => [],
                    'last_seen_at' => null,
                ];
            }
            $prospects[$refDomain]['linked_to'][$row->competitor_domain] = true;
            if ($row->anchor_text && count($prospects[$refDomain]['anchor_examples']) < 3) {
                $prospects[$refDomain]['anchor_examples'][] = mb_substr((string) $row->anchor_text, 0, 80);
            }
            // DA: keep the highest reported value across rows.
            if (is_numeric($row->domain_authority) && (int) $row->domain_authority > ($prospects[$refDomain]['domain_authority'] ?? 0)) {
                $prospects[$refDomain]['domain_authority'] = (int) $row->domain_authority;
            }
            $seen = $row->fetched_at?->toIso8601String();
            if ($seen !== null && ($prospects[$refDomain]['last_seen_at'] === null || $seen > $prospects[$refDomain]['last_seen_at'])) {
                $prospects[$refDomain]['last_seen_at'] = $seen;
            }
        }

        // Flatten linked_to maps + sort by DA desc, then by competitor
        // overlap (more competitors linking = stronger signal).
        $prospects = array_map(function ($p) {
            $p['linked_to'] = array_values(array_keys($p['linked_to']));
            return $p;
        }, $prospects);

        usort($prospects, function ($a, $b) {
            $da = ($b['domain_authority'] ?? 0) <=> ($a['domain_authority'] ?? 0);
            if ($da !== 0) return $da;
            return count($b['linked_to']) <=> count($a['linked_to']);
        });

        $prospects = array_slice(array_values($prospects), 0, self::MAX_PROSPECTS);

        // Persist into outreach_prospects so the user can come back to a
        // working list — status / notes / drafts survive across sessions
        // and the next prospect() call enriches existing rows instead of
        // wiping them. This is what turns the feature from "compute on
        // demand" into a real outreach workflow.
        $this->upsertProspects($website, $prospects);

        $result = [
            'prospects' => $prospects,
            'summary' => [
                'competitors_analyzed' => count($competitorDomains),
                'prospect_count' => count($prospects),
                'your_existing_links' => count($ownedDomains),
            ],
            'cached' => false,
        ];
        Cache::put($cacheKey, $result, Carbon::now()->addHours(self::CACHE_TTL_HOURS));
        return $result;
    }

    /**
     * The persisted-prospects view used by the HQ tab on initial load.
     * Returns saved prospects with status + notes + latest draft,
     * filtered by status if requested. This is the call the tab fires
     * before the user has typed any competitor — they should see their
     * working list immediately, not an empty form.
     *
     * @return array{
     *   prospects: list<array<string, mixed>>,
     *   counts: array<string, int>,
     * }
     */
    public function listSaved(Website $website, ?string $status = null): array
    {
        $query = OutreachProspect::query()
            ->where('website_id', $website->id)
            ->orderByRaw("CASE status
                WHEN 'replied' THEN 1
                WHEN 'drafted' THEN 2
                WHEN 'new' THEN 3
                WHEN 'contacted' THEN 4
                WHEN 'snoozed' THEN 5
                WHEN 'converted' THEN 6
                WHEN 'declined' THEN 7
                ELSE 8 END")
            ->orderByDesc('domain_authority');

        if ($status !== null && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $rows = $query->limit(500)->get();

        // Always compute the full status histogram so the toolbar pills
        // can show counts even when the current filter is restrictive.
        $counts = OutreachProspect::query()
            ->where('website_id', $website->id)
            ->selectRaw('status, COUNT(*) AS c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        return [
            'prospects' => $rows->map(fn (OutreachProspect $p) => $this->serializeProspect($p))->all(),
            'counts' => array_merge(array_fill_keys(OutreachProspect::STATUSES, 0), array_map('intval', $counts)),
        ];
    }

    /**
     * Update one persisted prospect's status / notes from the HQ tab.
     */
    public function updateProspect(Website $website, int $id, array $patch): ?OutreachProspect
    {
        $prospect = OutreachProspect::query()
            ->where('website_id', $website->id)
            ->where('id', $id)
            ->first();
        if (! $prospect) return null;

        if (array_key_exists('status', $patch) && in_array($patch['status'], OutreachProspect::STATUSES, true)) {
            $prospect->status = (string) $patch['status'];
            // Auto-stamp contacted_at the first time a prospect crosses
            // into a "we reached out" state — useful for outreach SLA reports.
            if (in_array($prospect->status, [OutreachProspect::STATUS_CONTACTED, OutreachProspect::STATUS_REPLIED, OutreachProspect::STATUS_CONVERTED], true)
                && $prospect->contacted_at === null) {
                $prospect->contacted_at = Carbon::now();
            }
        }
        if (array_key_exists('notes', $patch)) {
            $prospect->notes = mb_substr((string) $patch['notes'], 0, 4000);
        }
        $prospect->save();

        return $prospect;
    }

    /**
     * @param  list<array{domain: string, domain_authority: int|null, linked_to: list<string>, anchor_examples: list<string>, last_seen_at: string|null}>  $prospects
     */
    private function upsertProspects(Website $website, array $prospects): void
    {
        $now = Carbon::now();
        foreach ($prospects as $p) {
            $domain = (string) ($p['domain'] ?? '');
            if ($domain === '') continue;

            $existing = OutreachProspect::query()
                ->where('website_id', $website->id)
                ->where('referring_domain', $domain)
                ->first();

            $payload = [
                'domain_authority' => $p['domain_authority'] ?? null,
                'linked_to_competitors' => array_values($p['linked_to'] ?? []),
                'anchor_examples' => array_slice(array_values($p['anchor_examples'] ?? []), 0, 3),
                'last_seen_at' => $now,
            ];

            if ($existing) {
                // Merge competitor list — re-runs against new competitors
                // should expand, not replace. Status + notes untouched.
                $merged = array_values(array_unique(array_merge(
                    $existing->linked_to_competitors ?? [],
                    $payload['linked_to_competitors'],
                )));
                $payload['linked_to_competitors'] = $merged;
                // Keep the highest DA seen across runs (KE may revise it).
                if (($existing->domain_authority ?? 0) > ($payload['domain_authority'] ?? 0)) {
                    $payload['domain_authority'] = $existing->domain_authority;
                }
                $existing->fill($payload)->save();
            } else {
                OutreachProspect::create(array_merge($payload, [
                    'website_id' => $website->id,
                    'referring_domain' => $domain,
                    'status' => OutreachProspect::STATUS_NEW,
                    'first_seen_at' => $now,
                ]));
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProspect(OutreachProspect $p): array
    {
        return [
            'id' => $p->id,
            'domain' => $p->referring_domain,
            'domain_authority' => $p->domain_authority,
            'linked_to' => $p->linked_to_competitors ?? [],
            'anchor_examples' => $p->anchor_examples ?? [],
            'status' => $p->status,
            'notes' => $p->notes,
            'latest_draft' => $p->latest_draft,
            'first_seen_at' => $p->first_seen_at?->toIso8601String(),
            'last_seen_at' => $p->last_seen_at?->toIso8601String(),
            'contacted_at' => $p->contacted_at?->toIso8601String(),
        ];
    }

    /**
     * Generate a personalized outreach email for one prospect. Pro tier
     * only at the controller layer.
     *
     * @param  array<string, mixed>  $prospect
     * @param  array<string, mixed>  $context  { our_page_url, our_page_title, our_page_summary, ... }
     */
    public function draftOutreach(array $prospect, array $context): array
    {
        if (! $this->llm->isAvailable()) {
            return ['ok' => false, 'error' => 'llm_not_configured'];
        }

        $domain = (string) ($prospect['domain'] ?? '');
        $linkedTo = is_array($prospect['linked_to'] ?? null) ? $prospect['linked_to'] : [];
        $ourUrl = (string) ($context['our_page_url'] ?? '');
        $ourTitle = (string) ($context['our_page_title'] ?? '');
        $ourSummary = mb_substr((string) ($context['our_page_summary'] ?? ''), 0, 800);

        if ($domain === '' || $ourUrl === '') {
            return ['ok' => false, 'error' => 'missing_inputs'];
        }

        $cacheKey = sprintf('ebq_outreach_draft:%s:%s', hash('xxh3', $domain . '|' . $ourUrl), hash('xxh3', $ourSummary));
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) return $cached + ['cached' => true];

        $competitors = implode(', ', array_slice($linkedTo, 0, 3));
        $messages = [
            ['role' => 'system', 'content' => 'You write short, polite, specific outreach emails for SEO link-building. Never lie. Never use marketing-speak. Sound like a human who read the recipient site.'],
            ['role' => 'user', 'content' => sprintf(
                "Recipient site: %s\nThey've linked to: %s\nMy page they should also link to: %s\nMy page title: %s\nMy page summary: %s\n\nWrite a 90-word outreach email. Lead with WHY they linked to the competitor (you don't actually know — make a plausible guess based on the domain). Reference my page as a complementary resource, not a replacement. End with a low-pressure ask. Return strict JSON: { \"subject\": \"...\", \"body\": \"...\" }",
                $domain,
                $competitors,
                $ourUrl,
                $ourTitle,
                $ourSummary,
            )],
        ];

        $decoded = $this->llm->completeJson($messages, [
            'temperature' => 0.7,
            'max_tokens' => 500,
            'timeout' => 25,
        ]);

        if (! is_array($decoded) || empty($decoded['subject']) || empty($decoded['body'])) {
            Log::warning('BacklinkProspectingService: outreach draft parse failed', ['domain' => $domain]);
            return ['ok' => false, 'error' => 'llm_parse_failed'];
        }

        $result = [
            'ok' => true,
            'subject' => mb_substr((string) $decoded['subject'], 0, 140),
            'body' => mb_substr((string) $decoded['body'], 0, 1800),
            'cached' => false,
        ];
        Cache::put($cacheKey, $result, Carbon::now()->addDays(7));

        // Persist the draft on the matching prospect row so it survives
        // page reloads + appears next to the row. If the prospect record
        // doesn't exist yet (e.g. first-ever search), we don't create one
        // — drafts are only persisted for prospects already in the table.
        if (! empty($context['website_id'])) {
            $prospect = OutreachProspect::query()
                ->where('website_id', (int) $context['website_id'])
                ->where('referring_domain', $domain)
                ->first();
            if ($prospect) {
                $prospect->latest_draft = [
                    'subject' => $result['subject'],
                    'body' => $result['body'],
                    'generated_at' => Carbon::now()->toIso8601String(),
                ];
                if ($prospect->status === OutreachProspect::STATUS_NEW) {
                    $prospect->status = OutreachProspect::STATUS_DRAFTED;
                }
                $prospect->save();
            }
        }

        return $result;
    }

    /**
     * Set of normalized referring DOMAINS we already have at least one
     * backlink from. Excluded from prospect lists by definition (the
     * point is finding NEW outreach targets).
     *
     * @return array<string, true>
     */
    private function ownedReferringDomains(int $websiteId): array
    {
        $urls = Backlink::query()
            ->where('website_id', $websiteId)
            ->pluck('referring_page_url')
            ->all();

        $out = [];
        foreach ($urls as $u) {
            $host = parse_url((string) $u, PHP_URL_HOST);
            if (! is_string($host) || $host === '') continue;
            $h = strtolower(preg_replace('/^www\./', '', $host) ?: $host);
            $out[$h] = true;
        }
        return $out;
    }

    private function extractRefDomain(CompetitorBacklink $row): string
    {
        if ($row->referring_domain) {
            return strtolower(preg_replace('/^www\./', '', (string) $row->referring_domain) ?: '');
        }
        $host = parse_url((string) $row->referring_page_url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') return '';
        return strtolower(preg_replace('/^www\./', '', $host) ?: $host);
    }
}
