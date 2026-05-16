<?php

namespace App\Services;

use App\Models\RankTrackingKeyword;
use App\Models\RankTrackingSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RankTrackingService
{
    public function __construct(private readonly SerperSearchClient $serper) {}

    public function check(RankTrackingKeyword $keyword, bool $forced = false): RankTrackingSnapshot
    {
        $params = [
            'q' => $keyword->keyword,
            'type' => $keyword->search_type ?: 'organic',
            'gl' => $keyword->country,
            'hl' => $keyword->language,
            'location' => $keyword->location,
            'num' => max(10, min(100, (int) $keyword->depth)),
            'device' => $keyword->device,
            'autocorrect' => (bool) $keyword->autocorrect,
            'safe' => (bool) $keyword->safe_search,
            'tbs' => $keyword->tbs,
            // Billing attribution — picked up by SerperSearchClient::query
            // and written into client_activities.{user_id, website_id}.
            '__website_id'    => $keyword->website_id,
            '__owner_user_id' => $keyword->user_id,
            '__source'        => 'tracker',
        ];

        $json = $this->serper->query($params);
        $now = Carbon::now();

        if (! is_array($json)) {
            $snapshot = RankTrackingSnapshot::create([
                'rank_tracking_keyword_id' => $keyword->id,
                'checked_at' => $now,
                'status' => 'failed',
                'error' => 'Serper API returned no data (check SERPER_API_KEY).',
                'forced' => $forced,
            ]);

            $keyword->forceFill([
                'last_checked_at' => $now,
                'next_check_at' => $now->copy()->addHours(max(1, (int) $keyword->check_interval_hours)),
                'last_status' => 'failed',
                'last_error' => $snapshot->error,
            ])->save();

            return $snapshot;
        }

        $resultsKey = $this->resultsKeyFor($keyword->search_type);
        $results = is_array($json[$resultsKey] ?? null) ? $json[$resultsKey] : [];

        $targetDomain = $this->normalizeDomain($keyword->target_domain);
        $targetUrl = $keyword->target_url ? $this->normalizeUrl($keyword->target_url) : null;

        $position = null;
        $foundUrl = null;
        $foundTitle = null;
        $foundSnippet = null;
        $topResults = [];

        foreach ($results as $idx => $row) {
            if (! is_array($row)) {
                continue;
            }
            $link = (string) ($row['link'] ?? $row['url'] ?? '');
            $title = (string) ($row['title'] ?? '');
            $snippet = (string) ($row['snippet'] ?? $row['description'] ?? '');
            $rank = (int) ($row['position'] ?? ($idx + 1));

            if (count($topResults) < 20) {
                $topResults[] = [
                    'position' => $rank,
                    'title' => mb_substr($title, 0, 300),
                    'link' => $link,
                    'snippet' => mb_substr($snippet, 0, 300),
                ];
            }

            if ($position !== null) {
                continue;
            }

            $linkDomain = $this->normalizeDomain($link);
            $matches = $targetUrl
                ? ($this->normalizeUrl($link) === $targetUrl)
                : ($linkDomain !== '' && $linkDomain === $targetDomain);

            if ($matches) {
                $position = $rank;
                $foundUrl = $link;
                $foundTitle = $title;
                $foundSnippet = $snippet;
            }
        }

        $competitorPositions = [];
        foreach ((array) ($keyword->competitors ?? []) as $competitor) {
            $cDomain = $this->normalizeDomain((string) $competitor);
            if ($cDomain === '') {
                continue;
            }
            $cPos = null;
            $cUrl = null;
            foreach ($results as $idx => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $link = (string) ($row['link'] ?? $row['url'] ?? '');
                if ($this->normalizeDomain($link) === $cDomain) {
                    $cPos = (int) ($row['position'] ?? ($idx + 1));
                    $cUrl = $link;
                    break;
                }
            }
            $competitorPositions[] = [
                'domain' => $cDomain,
                'position' => $cPos,
                'url' => $cUrl,
            ];
        }

        $serpFeatures = $this->extractSerpFeatures($json);
        $relatedSearches = $this->extractList($json['relatedSearches'] ?? [], ['query']);
        $peopleAlsoAsk = $this->extractList($json['peopleAlsoAsk'] ?? [], ['question', 'snippet', 'title', 'link']);

        $snapshot = RankTrackingSnapshot::create([
            'rank_tracking_keyword_id' => $keyword->id,
            'checked_at' => $now,
            'position' => $position,
            'url' => $foundUrl,
            'title' => $foundTitle ? mb_substr($foundTitle, 0, 500) : null,
            'snippet' => $foundSnippet,
            'total_results' => isset($json['searchInformation']['totalResults'])
                ? (int) $json['searchInformation']['totalResults']
                : null,
            'search_time' => isset($json['searchInformation']['timeTaken'])
                ? (float) $json['searchInformation']['timeTaken']
                : null,
            'serp_features' => $serpFeatures,
            'competitor_positions' => $competitorPositions,
            'top_results' => $topResults,
            'related_searches' => $relatedSearches,
            'people_also_ask' => $peopleAlsoAsk,
            'status' => 'ok',
            'forced' => $forced,
        ]);

        $previous = $keyword->current_position;
        $change = null;
        if ($position !== null && $previous !== null) {
            $change = ((int) $previous) - $position;
        }

        $updates = [
            'last_checked_at' => $now,
            'next_check_at' => $now->copy()->addHours(max(1, (int) $keyword->check_interval_hours)),
            'last_status' => 'ok',
            'last_error' => null,
            'current_position' => $position,
            'current_url' => $foundUrl,
            'position_change' => $change,
        ];

        if ($position !== null) {
            $updates['best_position'] = $keyword->best_position
                ? min((int) $keyword->best_position, $position)
                : $position;

            if ($keyword->initial_position === null) {
                $updates['initial_position'] = $position;
            }
        }

        $keyword->forceFill($updates)->save();

        return $snapshot;
    }

    private function resultsKeyFor(string $type): string
    {
        return match (strtolower($type)) {
            'news' => 'news',
            'images' => 'images',
            'videos' => 'videos',
            'shopping' => 'shopping',
            'maps', 'places' => 'places',
            'scholar' => 'organic',
            default => 'organic',
        };
    }

    private function extractSerpFeatures(array $json): array
    {
        $features = [];
        foreach (['answerBox', 'knowledgeGraph', 'topStories', 'peopleAlsoAsk', 'relatedSearches', 'siteLinks', 'shopping', 'images', 'videos', 'twitter'] as $feature) {
            if (! empty($json[$feature])) {
                $features[] = $feature;
            }
        }

        return $features;
    }

    private function extractList(mixed $list, array $keys): array
    {
        if (! is_array($list)) {
            return [];
        }
        $out = [];
        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }
            $entry = [];
            foreach ($keys as $k) {
                if (isset($row[$k])) {
                    $entry[$k] = is_scalar($row[$k]) ? (string) $row[$k] : null;
                }
            }
            if ($entry !== []) {
                $out[] = $entry;
            }
            if (count($out) >= 10) {
                break;
            }
        }

        return $out;
    }

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

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (! str_contains($url, '://')) {
            $url = 'http://'.$url;
        }
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['host'])) {
            return strtolower($url);
        }
        $host = strtolower($parts['host']);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }
        $path = rtrim($parts['path'] ?? '', '/');

        return $host.$path;
    }
}
