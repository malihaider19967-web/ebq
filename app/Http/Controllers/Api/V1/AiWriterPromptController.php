<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiWriterPrompt;
use App\Models\Website;
use App\Services\AiWriter\CustomPromptGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-user library of AI Writer custom prompts. The wizard's Step 1 lets
 * users save reusable "additional writing instructions" and pick from
 * them across all the user's websites.
 *
 * Scoping: each WordPress connection authenticates as a Website (see
 * WebsiteApiAuth); we use `$website->user_id` as the owning user so
 * prompts saved on Site A are visible when the same user opens the
 * wizard on Site B. Em-dashes are stripped server-side per the no-em-
 * dashes-in-AI rule. A CustomPromptGuard call rejects off-topic or
 * jailbreak prompts before they hit the library.
 */
class AiWriterPromptController extends Controller
{
    public function __construct(private readonly CustomPromptGuard $guard)
    {
    }

    /**
     * GET /api/v1/hq/ai-writer-prompts
     * List the current user's saved prompts (newest first).
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $this->userId($request);

        $prompts = AiWriterPrompt::query()
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $prompts->map(fn (AiWriterPrompt $p) => $this->shape($p))->all(),
        ]);
    }

    /**
     * POST /api/v1/hq/ai-writer-prompts
     * body: { title, body }
     */
    public function store(Request $request): JsonResponse
    {
        $userId = $this->userId($request);

        $data = $request->validate([
            'title' => 'required|string|min:2|max:120',
            'body'  => 'required|string|min:5|max:2000',
        ]);

        $body = self::stripDashes(trim((string) $data['body']));
        $title = self::stripDashes(trim((string) $data['title']));

        $verdict = $this->guard->check($body);
        if (($verdict['ok'] ?? false) !== true) {
            return response()->json([
                'ok'      => false,
                'error'   => 'prompt_rejected',
                'message' => (string) ($verdict['reason'] ?? 'This prompt isn\'t related to AI writing.'),
            ], 422);
        }

        $prompt = AiWriterPrompt::create([
            'user_id' => $userId,
            'title'   => $title,
            'body'    => $body,
        ]);

        return response()->json([
            'prompt' => $this->shape($prompt),
        ], 201);
    }

    /**
     * DELETE /api/v1/hq/ai-writer-prompts/{externalId}
     */
    public function destroy(Request $request, string $externalId): JsonResponse
    {
        $userId = $this->userId($request);

        $prompt = AiWriterPrompt::query()
            ->where('user_id', $userId)
            ->where('external_id', $externalId)
            ->first();

        abort_unless($prompt instanceof AiWriterPrompt, 404, 'Prompt not found');

        $prompt->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(AiWriterPrompt $p): array
    {
        return [
            'id'         => $p->external_id,
            'title'      => $p->title,
            'body'       => $p->body,
            'updated_at' => $p->updated_at?->toIso8601String(),
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }

    private function userId(Request $request): string
    {
        $website = $request->attributes->get('api_website');
        abort_unless($website instanceof Website, 500, 'Website context missing');
        abort_unless($website->user_id !== null, 403, 'Website has no owner');

        return (string) $website->user_id;
    }

    /**
     * Mirror of AiWriterService::stripDashes — em / en dashes and the
     * double-hyphen are AI tells and are banned from anything we store.
     */
    private static function stripDashes(string $s): string
    {
        if ($s === '') {
            return $s;
        }
        $s = str_replace(["\u{2014}", "\u{2013}", '--'], ' ', $s);
        return preg_replace('/\s{2,}/u', ' ', $s) ?? $s;
    }
}
