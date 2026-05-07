<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Research\ResearchTarget;
use App\Support\ResearchEngineSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin control panel for the Research engine. Every runtime knob
 * surfaces here; deploy-time values (python path, cwd) appear as
 * read-only diagnostics so admins can see what's resolved without
 * SSH-ing in.
 */
class ResearchSettingsController extends Controller
{
    public function show(): View
    {
        return view('admin.research.settings', [
            'settings' => ResearchEngineSettings::all(),
            'diagnostics' => ResearchEngineSettings::diagnostics(),
            'queueDepth' => [
                'queued' => ResearchTarget::query()->where('status', ResearchTarget::STATUS_QUEUED)->count(),
                'scanning' => ResearchTarget::query()->where('status', ResearchTarget::STATUS_SCANNING)->count(),
                'done' => ResearchTarget::query()->where('status', ResearchTarget::STATUS_DONE)->count(),
                'paused' => ResearchTarget::query()->where('status', ResearchTarget::STATUS_PAUSED)->count(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'engine_paused' => 'sometimes|boolean',
            'auto_discovery_disabled' => 'sometimes|boolean',
            'auto_fetch_volume' => 'sometimes|boolean',
            'embeddings_enabled' => 'sometimes|boolean',

            'rollout_mode' => 'required|in:ga,cohort,admin',
            'rollout_allowlist' => 'nullable|string|max:8000',

            'quotas.keyword_lookup' => 'required|integer|min:0|max:1000000',
            'quotas.serp_fetch' => 'required|integer|min:0|max:1000000',
            'quotas.llm_call' => 'required|integer|min:0|max:1000000',
            'quotas.brief' => 'required|integer|min:0|max:1000000',

            'scraper.ceiling_total_pages' => 'required|integer|min:10|max:100000',
            'scraper.ceiling_external_per_domain' => 'required|integer|min:0|max:1000',
            'scraper.ceiling_depth' => 'required|integer|min:1|max:20',
            'scraper.timeout_seconds' => 'required|integer|min:60|max:21600',
        ]);

        $allowlist = collect(preg_split('/[,\s]+/', (string) ($data['rollout_allowlist'] ?? '')))
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($n) => $n > 0)
            ->unique()
            ->values()
            ->all();

        ResearchEngineSettings::save([
            'engine_paused' => $request->boolean('engine_paused'),
            'auto_discovery_disabled' => $request->boolean('auto_discovery_disabled'),
            'auto_fetch_volume' => $request->boolean('auto_fetch_volume'),
            'embeddings_enabled' => $request->boolean('embeddings_enabled'),
            'rollout_mode' => $data['rollout_mode'],
            'rollout_allowlist' => $allowlist,
            'quotas' => [
                'keyword_lookup' => (int) $data['quotas']['keyword_lookup'],
                'serp_fetch' => (int) $data['quotas']['serp_fetch'],
                'llm_call' => (int) $data['quotas']['llm_call'],
                'brief' => (int) $data['quotas']['brief'],
            ],
            'scraper' => [
                'ceiling_total_pages' => (int) $data['scraper']['ceiling_total_pages'],
                'ceiling_external_per_domain' => (int) $data['scraper']['ceiling_external_per_domain'],
                'ceiling_depth' => (int) $data['scraper']['ceiling_depth'],
                'timeout_seconds' => (int) $data['scraper']['timeout_seconds'],
            ],
        ]);

        return redirect()
            ->route('admin.research.settings.show')
            ->with('status', 'Research settings saved.');
    }
}
