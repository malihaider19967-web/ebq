<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Research\ContentBrief;
use App\Models\Research\Keyword;
use App\Models\Research\KeywordIntelligence;
use App\Models\Research\SerpSnapshot;
use App\Models\Website;
use App\Services\Research\Intelligence\OpportunityEngine;
use App\Services\Research\KeywordExpansionService;
use App\Services\Research\ResearchAggregateService;
use App\Services\Research\SerpIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase-3 portal API. Same auth chain as the plugin endpoints
 * (`website.api:read:insights`), so the WordPress plugin and the portal
 * can share these endpoints. Reuses the services built in Phase 2 — no
 * controller-side business logic.
 */
class ResearchApiController extends Controller
{
    public function expandKeywords(Request $request, KeywordExpansionService $expansion): JsonResponse
    {
        $data = $request->validate([
            'seed' => 'required|string|min:2|max:200',
            'country' => 'nullable|string|size:2',
        ]);

        $website = $this->resolveWebsite($request);
        $country = strtolower((string) ($data['country'] ?? 'us')) ?: 'us';

        $keywords = $expansion->expand((string) $data['seed'], $country, $website);

        return response()->json([
            'ok' => true,
            'count' => $keywords->count(),
            'keywords' => $keywords->map(fn (Keyword $k) => [
                'id' => $k->id,
                'query' => $k->query,
                'country' => $k->country,
            ])->all(),
        ]);
    }

    public function serp(Request $request, ResearchAggregateService $aggregate): JsonResponse
    {
        $data = $request->validate([
            'focus_keyword' => 'required|string|min:2|max:200',
            'country' => 'nullable|string|size:2',
            'language' => 'nullable|string|min:2|max:10',
            'url' => 'nullable|string|max:2048',
        ]);

        $website = $this->resolveWebsite($request);
        $country = strtolower((string) ($data['country'] ?? 'us')) ?: 'us';
        $language = strtolower((string) ($data['language'] ?? 'en')) ?: 'en';
        $url = trim((string) ($data['url'] ?? ''));

        $bundle = $aggregate->bundle($website, (string) $data['focus_keyword'], $country, $language, $url);

        return response()->json($bundle + ['ok' => true]);
    }

    public function briefs(Request $request): JsonResponse
    {
        $website = $this->resolveWebsite($request);

        $briefs = ContentBrief::query()
            ->where('website_id', $website->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'keyword_id', 'created_by', 'created_at', 'payload']);

        return response()->json([
            'ok' => true,
            'count' => $briefs->count(),
            'briefs' => $briefs,
        ]);
    }

    public function opportunities(Request $request, OpportunityEngine $engine): JsonResponse
    {
        $website = $this->resolveWebsite($request);

        $rows = \DB::table('search_console_data')
            ->select('keyword_id', 'page')
            ->selectRaw('SUM(impressions) as imps, SUM(clicks) as clicks, AVG(position) as avg_pos')
            ->where('website_id', $website->id)
            ->whereNotNull('keyword_id')
            ->whereDate('date', '>=', now()->subDays(30)->toDateString())
            ->groupBy('keyword_id', 'page')
            ->orderByDesc('imps')
            ->limit(100)
            ->get();

        $intelById = KeywordIntelligence::query()
            ->whereIn('keyword_id', $rows->pluck('keyword_id')->filter())
            ->get()
            ->keyBy('keyword_id');

        $out = [];
        foreach ($rows as $r) {
            $intel = $intelById->get($r->keyword_id);
            $score = $engine->score(
                impressions30d: (int) $r->imps,
                currentCtr: $r->imps > 0 ? (float) $r->clicks / (float) $r->imps : 0.0,
                currentPosition: (float) $r->avg_pos,
                searchVolume: $intel?->search_volume,
                difficulty: $intel?->difficulty_score,
                nicheCtrByPosition: [1 => 0.30, 2 => 0.18, 3 => 0.11, 4 => 0.08, 5 => 0.06],
                targetPosition: 3,
            );
            $out[] = [
                'keyword_id' => (int) $r->keyword_id,
                'page' => (string) $r->page,
                'impressions_30d' => (int) $r->imps,
                'clicks_30d' => (int) $r->clicks,
                'avg_position' => round((float) $r->avg_pos, 2),
            ] + $score;
        }

        usort($out, fn ($a, $b) => $b['score'] <=> $a['score']);

        return response()->json([
            'ok' => true,
            'count' => count($out),
            'opportunities' => $out,
        ]);
    }

    private function resolveWebsite(Request $request): Website
    {
        $website = $request->attributes->get('api_website');
        abort_unless($website instanceof Website, 500, 'Website context missing');

        return $website;
    }
}
