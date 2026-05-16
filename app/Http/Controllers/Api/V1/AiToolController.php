<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Services\AiToolRegistry;
use App\Services\AiToolRunner;
use App\Services\BrandVoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI Studio entry points.
 *
 *   GET    /api/v1/hq/ai/tools                   list catalog
 *   GET    /api/v1/hq/ai/tools/{toolId}          single-tool meta
 *   POST   /api/v1/hq/ai/tools/{toolId}/run      execute (the only mutation)
 *   GET    /api/v1/hq/ai/brand-voice             current fingerprint summary
 *   PUT    /api/v1/hq/ai/brand-voice             upload samples → extract
 *   DELETE /api/v1/hq/ai/brand-voice             clear
 *
 * Pro tier is enforced inside `AiToolRunner` per-tool (some lightweight
 * utilities may go free in the future). Brand voice is gated here.
 */
class AiToolController extends Controller
{
    public function __construct(
        private readonly AiToolRegistry $registry,
        private readonly AiToolRunner $runner,
        private readonly BrandVoiceService $brandVoice,
    ) {
    }

    public function indexTools(Request $request): JsonResponse
    {
        $this->website($request); // assert website context exists

        $surface = $request->query('surface');
        if (is_string($surface) && $surface !== '') {
            $tools = $this->registry->forSurface($surface)
                ->map(fn ($t) => $t->meta()->toArray())
                ->values()
                ->all();
            return response()->json(['tools' => $tools]);
        }

        return response()->json($this->registry->catalog());
    }

    public function showTool(Request $request, string $toolId): JsonResponse
    {
        $this->website($request);
        $tool = $this->registry->find($toolId);
        if (! $tool) {
            return response()->json(['ok' => false, 'error' => 'unknown_tool'], 404);
        }
        return response()->json(['tool' => $tool->meta()->toArray()]);
    }

    public function runTool(Request $request, string $toolId): JsonResponse
    {
        $website = $this->website($request);

        if (! $this->registry->has($toolId)) {
            return response()->json(['ok' => false, 'error' => 'unknown_tool'], 404);
        }

        // Tool-specific shape is validated inside the runner against
        // meta()->inputs. Here we only sanity-check that there's a
        // body to work with.
        $input = $request->all();
        if (! is_array($input)) {
            $input = [];
        }

        // AI calls can chain Serper + LLM; bump the request timeout
        // so even cold-cache full-post wizards don't hit PHP's
        // 30s default.
        @set_time_limit(360);

        $result = $this->runner->run($toolId, $website, $request->user()?->id, $input);

        $payload = $result->toArray();
        if (! $result->ok && $result->error === 'tier_required') {
            // Decorate AI Studio tier-required failures with the
            // tier/required_tier/feature triple the WP plugin reads
            // through friendlyError. AI Studio tools all live under
            // the ai_writer feature flag; featureGateInfo() picks the
            // right error code (tier_required vs feature_disabled) and
            // the right required slug given the site's current state.
            $gate = $website->featureGateInfo('ai_writer');
            if ($gate !== null) {
                $payload['error'] = $gate['error'];
                $payload['tier'] = $gate['tier'];
                $payload['required_tier'] = $gate['required_tier'];
                $payload['feature'] = $gate['feature'];
            }
        }

        return response()->json($payload, $result->ok ? 200 : (in_array($result->error, ['tier_required', 'feature_disabled'], true) ? 402 : 422));
    }

    public function brandVoiceShow(Request $request): JsonResponse
    {
        $website = $this->website($request);
        $profile = $this->brandVoice->forWebsite($website);
        return response()->json($this->brandVoice->summaryForPlugin($profile));
    }

    public function brandVoiceUpdate(Request $request): JsonResponse
    {
        $website = $this->website($request);
        if ($gate = $website->featureGateInfo('ai_writer')) {
            return response()->json($gate + [
                'message' => 'Brand voice is available on Pro.',
            ], 402);
        }

        $data = $request->validate([
            'samples' => 'required|array|min:1|max:5',
            'samples.*' => 'required|string|min:200|max:50000',
        ]);

        @set_time_limit(180);

        $profile = $this->brandVoice->extract($website, $data['samples']);
        if (! $profile) {
            return response()->json([
                'ok' => false,
                'error' => 'extraction_failed',
                'message' => 'We could not extract a voice from those samples — try longer or more representative posts.',
            ], 422);
        }

        return response()->json($this->brandVoice->summaryForPlugin($profile));
    }

    public function brandVoiceDestroy(Request $request): JsonResponse
    {
        $website = $this->website($request);
        $this->brandVoice->clear($website);
        return response()->json(['ok' => true]);
    }

    private function website(Request $request): Website
    {
        $w = $request->attributes->get('api_website');
        abort_unless($w instanceof Website, 500, 'Website context missing');
        return $w;
    }
}
