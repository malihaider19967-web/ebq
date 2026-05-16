<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Schema Spy — server-side fetcher for the "Import schema from URL"
 * wizard in the editor sidebar. Pulls every
 * `<script type="application/ld+json">` block out of the target URL,
 * decodes each block, and returns ready-to-import normalised schema
 * entries the editor can multi-select.
 *
 * Lives server-side because:
 *   - WP would hit cross-origin issues fetching the URL in-browser.
 *   - The server can honour the user's plan-level quotas (no abuse).
 *   - We can sanitise the response before it ever touches `_ebq_schemas`.
 *
 * Gated by `plan_features.schema_spy`.
 */
class SchemaSpyController extends Controller
{
    /**
     * POST /api/v1/schema-spy
     * Body: { url: string }
     */
    public function spy(Request $request): JsonResponse
    {
        $website = $this->resolveWebsite($request);

        $gate = $website->featureGateInfo('schema_spy');
        if ($gate !== null) {
            return response()->json(array_merge($gate, [
                'message' => 'Schema Spy is a paid feature. Upgrade to unlock.',
            ]), 402);
        }

        $url = (string) $request->input('url', '');
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_url',
                'message' => 'Provide a valid http(s) URL to import schema from.',
            ], 422);
        }

        try {
            $resp = Http::withHeaders([
                'User-Agent' => 'EBQ-SchemaSpy/1.0',
                'Accept' => 'text/html',
            ])->timeout(15)->get($url);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'fetch_failed',
                'message' => 'Could not reach that URL.',
            ], 502);
        }
        if (! $resp->ok()) {
            return response()->json([
                'ok' => false,
                'error' => 'http_'.$resp->status(),
                'message' => 'Target URL returned HTTP '.$resp->status(),
            ], 502);
        }

        $html = (string) $resp->body();
        $entries = self::parseLdJson($html);

        return response()->json([
            'ok' => true,
            'source_url' => $url,
            'count' => count($entries),
            'entries' => $entries,
        ]);
    }

    /**
     * Extract every `application/ld+json` block from the HTML and
     * normalise into a list of `{ id, template, type, data, enabled }`
     * shaped entries the editor sidebar can drop straight into
     * `_ebq_schemas`. Returns an empty list when nothing parses cleanly.
     *
     * @return list<array<string, mixed>>
     */
    private static function parseLdJson(string $html): array
    {
        $entries = [];
        if (! preg_match_all('#<script[^>]*application/ld\+json[^>]*>(.*?)</script>#is', $html, $matches)) {
            return [];
        }
        foreach ($matches[1] as $raw) {
            $raw = trim((string) $raw);
            if ($raw === '') continue;
            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) continue;

            // The block may carry a single node, a `@graph` envelope,
            // or a top-level array. Flatten all three into a list of
            // node arrays.
            $nodes = [];
            if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
                $nodes = $decoded['@graph'];
            } elseif (isset($decoded[0]) && is_array($decoded[0])) {
                $nodes = $decoded;
            } else {
                $nodes = [$decoded];
            }
            foreach ($nodes as $node) {
                if (! is_array($node)) continue;
                $type = (string) ($node['@type'] ?? '');
                if ($type === '') continue;
                $entries[] = self::normaliseEntry($node, $type);
            }
        }
        return $entries;
    }

    /**
     * Map a parsed LD-JSON node onto an `_ebq_schemas`-shaped entry.
     * The `template` slot resolves to the matching EBQ template ID
     * when we can recognise it; falls back to `custom` for unknown
     * types so the editor still imports them faithfully.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function normaliseEntry(array $node, string $type): array
    {
        $template = self::templateForType($type);
        // Strip @context / @id — the renderer recomputes them.
        unset($node['@context'], $node['@id']);
        return [
            'id'       => bin2hex(random_bytes(6)),
            'template' => $template,
            'type'     => $type,
            'enabled'  => true,
            'data'     => $node,
        ];
    }

    private static function templateForType(string $type): string
    {
        $map = [
            'Article'         => 'article',
            'BlogPosting'     => 'article',
            'NewsArticle'     => 'article',
            'Product'         => 'product',
            'Event'           => 'event',
            'FAQPage'         => 'faq',
            'Recipe'          => 'recipe',
            'LocalBusiness'   => 'local_business',
            'Restaurant'      => 'local_business',
            'Store'           => 'local_business',
            'Book'            => 'book',
            'Course'          => 'course',
            'JobPosting'      => 'job_posting',
            'VideoObject'     => 'video',
            'SoftwareApplication' => 'software',
            'Service'         => 'service',
            'Person'          => 'person',
            'MusicAlbum'      => 'music_album',
            'Movie'           => 'movie',
            'Review'          => 'review',
            'WebSite'         => 'website',
            'Organization'    => 'organization',
            'WebPage'         => 'webpage',
            'Dataset'         => 'dataset',
            'ClaimReview'     => 'fact_check',
            'PodcastEpisode'  => 'podcast_episode',
        ];
        return $map[$type] ?? 'custom';
    }

    private function resolveWebsite(Request $request): Website
    {
        $website = $request->attributes->get('api_website');
        abort_unless($website instanceof Website, 500, 'Website context missing');
        return $website;
    }
}
