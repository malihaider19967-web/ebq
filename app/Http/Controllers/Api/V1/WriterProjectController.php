<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Models\WriterProject;
use App\Services\WriterProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * AI Writer wizard API — multi-step project lifecycle: topic → brief →
 * images → summary → completed. The WP plugin's React wizard talks to
 * this controller via the EBQ_Rest_Proxy.
 *
 * Pro tier only (mirrors the existing /ai-writer guard). Every state
 * mutation flows through WriterProjectService so credit accounting and
 * shape normalization stay in one place.
 */
class WriterProjectController extends Controller
{
    public function __construct(private readonly WriterProjectService $service)
    {
    }

    /**
     * GET /api/v1/hq/writer-projects?status=active|completed&per_page=20
     */
    public function index(Request $request): JsonResponse
    {
        $website = $this->website($request);
        $this->requirePro($website);

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min(50, $perPage));

        $statusFilter = (string) $request->query('status', 'all');

        $query = WriterProject::query()
            ->where('website_id', $website->id)
            ->orderByDesc('updated_at');

        if ($statusFilter === 'active') {
            $query->where('step', '!=', WriterProject::STEP_COMPLETED);
        } elseif ($statusFilter === 'completed') {
            $query->where('step', WriterProject::STEP_COMPLETED);
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (WriterProject $p) => $this->summary($p))->all(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/hq/writer-projects
     * body: { focus_keyword, additional_keywords?[], title? }
     */
    public function store(Request $request): JsonResponse
    {
        $website = $this->website($request);
        $this->requirePro($website);

        $data = $request->validate([
            'focus_keyword' => 'required|string|min:2|max:200',
            'title' => 'nullable|string|max:300',
            'additional_keywords' => 'nullable|array|max:20',
            'additional_keywords.*' => 'string|max:200',
        ]);

        $project = $this->service->create($website, $request->user()?->id, $data);

        return response()->json([
            'project' => $this->full($project),
        ], 201);
    }

    /**
     * GET /api/v1/hq/writer-projects/{externalId}
     */
    public function show(Request $request, string $externalId): JsonResponse
    {
        $project = $this->resolve($request, $externalId);

        return response()->json([
            'project' => $this->full($project),
        ]);
    }

    /**
     * PATCH /api/v1/hq/writer-projects/{externalId}
     * body: { title?, focus_keyword?, additional_keywords?[], step?, brief?, images?, wp_post_id? }
     *
     * Free-form patch — the wizard PATCHes after each user action so
     * leaving and resuming picks up the exact same state. Generation
     * has its own endpoint (POST /generate).
     */
    public function update(Request $request, string $externalId): JsonResponse
    {
        $project = $this->resolve($request, $externalId);

        $data = $request->validate([
            'title' => 'nullable|string|max:300',
            'focus_keyword' => 'nullable|string|min:2|max:200',
            'additional_keywords' => 'nullable|array|max:20',
            'additional_keywords.*' => 'string|max:200',
            'step' => ['nullable', Rule::in(WriterProject::STEPS)],
            'brief' => 'nullable|array',
            'images' => 'nullable|array|max:20',
            'images.*.url' => 'required_with:images|string|max:2048',
            'images.*.thumbnail_url' => 'nullable|string|max:2048',
            'images.*.source' => ['nullable', Rule::in(['serper', 'upload'])],
            'images.*.alt' => 'nullable|string|max:200',
            'images.*.caption' => 'nullable|string|max:280',
            'images.*.assigned_h2' => 'nullable|string|max:200',
            'images.*.width' => 'nullable|integer|min:0|max:20000',
            'images.*.height' => 'nullable|integer|min:0|max:20000',
            'wp_post_id' => 'nullable|integer|min:1',
        ]);

        if (array_key_exists('title', $data) && $data['title'] !== null) {
            $project->title = $data['title'];
        }
        if (array_key_exists('focus_keyword', $data) && $data['focus_keyword'] !== null) {
            $project->focus_keyword = $data['focus_keyword'];
        }
        if (array_key_exists('additional_keywords', $data) && $data['additional_keywords'] !== null) {
            $project->additional_keywords = array_values(array_filter(array_map('strval', $data['additional_keywords'])));
        }
        if (array_key_exists('step', $data) && $data['step'] !== null) {
            $project->step = $data['step'];
        }
        if (array_key_exists('brief', $data) && $data['brief'] !== null) {
            $project->brief = $data['brief'];
        }
        if (array_key_exists('wp_post_id', $data) && $data['wp_post_id'] !== null) {
            $project->wp_post_id = (int) $data['wp_post_id'];
        }
        $project->save();

        if (array_key_exists('images', $data) && $data['images'] !== null) {
            $project = $this->service->updateImages($project, $data['images']);
        }

        return response()->json([
            'project' => $this->full($project->refresh()),
        ]);
    }

    /**
     * DELETE /api/v1/hq/writer-projects/{externalId}
     * Soft delete — the row stays for billing audits.
     */
    public function destroy(Request $request, string $externalId): JsonResponse
    {
        $project = $this->resolve($request, $externalId);
        $project->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/v1/hq/writer-projects/{externalId}/brief
     * Generate (or regenerate) the topic brief for step 2.
     */
    public function generateBrief(Request $request, string $externalId): JsonResponse
    {
        $project = $this->resolve($request, $externalId);
        $website = $this->website($request);

        $project = $this->service->generateBrief($project, $website);

        return response()->json([
            'project' => $this->full($project),
        ]);
    }

    /**
     * POST /api/v1/hq/writer-projects/{externalId}/brief/chat
     * body: { message }
     */
    public function briefChat(Request $request, string $externalId): JsonResponse
    {
        $project = $this->resolve($request, $externalId);

        $data = $request->validate([
            'message' => 'required|string|min:1|max:2000',
        ]);

        $project = $this->service->applyBriefChat($project, (string) $data['message']);

        return response()->json([
            'project' => $this->full($project),
        ]);
    }

    /**
     * POST /api/v1/hq/writer-projects/{externalId}/images/search
     * body: { query?, num? }
     */
    public function searchImages(Request $request, string $externalId): JsonResponse
    {
        $project = $this->resolve($request, $externalId);

        $data = $request->validate([
            'query' => 'nullable|string|max:200',
            'num' => 'nullable|integer|min:1|max:40',
        ]);

        $images = $this->service->searchImages(
            $project,
            $data['query'] ?? null,
            (int) ($data['num'] ?? 16),
        );

        return response()->json([
            'images' => $images,
            'project' => $this->full($project->refresh()),
        ]);
    }

    /**
     * POST /api/v1/hq/writer-projects/{externalId}/generate
     * Final generation — strict-mode writer + image placement.
     */
    public function generate(Request $request, string $externalId): JsonResponse
    {
        $project = $this->resolve($request, $externalId);
        $website = $this->website($request);

        $project = $this->service->generate($project, $website);

        return response()->json([
            'project' => $this->full($project),
        ]);
    }

    /**
     * GET /api/v1/hq/writer-projects/{externalId}/credits
     * Live sum from client_activities (segregated by provider).
     */
    public function credits(Request $request, string $externalId): JsonResponse
    {
        $project = $this->resolve($request, $externalId);

        return response()->json([
            'project_id' => $project->external_id,
            'credits_used' => $this->service->totalCreditsForProject($project),
            'credits_used_cached' => (int) $project->credits_used,
        ]);
    }

    /* ───────────────── helpers ───────────────── */

    private function website(Request $request): Website
    {
        $w = $request->attributes->get('api_website');
        abort_unless($w instanceof Website, 500, 'Website context missing');
        return $w;
    }

    private function requirePro(Website $website): void
    {
        if (! $website->isPro()) {
            abort(response()->json([
                'ok' => false,
                'error' => 'tier_required',
                'tier' => $website->tier,
                'required_tier' => Website::TIER_PRO,
                'message' => 'AI Writer is available on Pro. Upgrade to unlock.',
            ], 402));
        }
    }

    private function resolve(Request $request, string $externalId): WriterProject
    {
        $website = $this->website($request);
        $this->requirePro($website);

        $project = WriterProject::query()
            ->where('website_id', $website->id)
            ->where('external_id', $externalId)
            ->first();

        abort_unless($project instanceof WriterProject, 404, 'Project not found');
        return $project;
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(WriterProject $p): array
    {
        return [
            'id' => $p->external_id,
            'title' => $p->title,
            'focus_keyword' => $p->focus_keyword,
            'step' => $p->step,
            'credits_used' => (int) $p->credits_used,
            'wp_post_id' => $p->wp_post_id,
            'updated_at' => $p->updated_at?->toIso8601String(),
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function full(WriterProject $p): array
    {
        return [
            'id' => $p->external_id,
            'title' => $p->title,
            'focus_keyword' => $p->focus_keyword,
            'additional_keywords' => is_array($p->additional_keywords) ? $p->additional_keywords : [],
            'step' => $p->step,
            'brief' => is_array($p->brief) ? $p->brief : null,
            'chat_history' => is_array($p->chat_history) ? $p->chat_history : [],
            'images' => is_array($p->images) ? $p->images : [],
            'generated_html' => (string) ($p->generated_html ?? ''),
            'wp_post_id' => $p->wp_post_id,
            'credits_used' => (int) $p->credits_used,
            'updated_at' => $p->updated_at?->toIso8601String(),
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }
}
