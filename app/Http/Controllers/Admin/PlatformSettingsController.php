<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\AiModelConfig;
use App\Support\AuditConfig;
use App\Support\RankTrackerConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Single consolidated admin settings page. Replaces the previously separate
 * AI model / Rank tracker / Page audit settings pages with one screen:
 *
 *   - Default AI model (Mistral) for every AI feature
 *   - Rank tracker default re-check interval
 *   - Keywords Everywhere competitor-data toggle
 *
 * Each control still reads/writes its existing config helper + Setting key,
 * so behaviour is unchanged — only the UI is unified.
 */
class PlatformSettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.index', [
            'models'                       => AiModelConfig::listAvailableModels(),
            'currentModel'                 => AiModelConfig::currentModel(),
            'checkIntervalHours'           => RankTrackerConfig::checkIntervalHours(),
            'defaultDepth'                 => RankTrackerConfig::DEFAULT_DEPTH,
            'competitorKeywordsEverywhere' => AuditConfig::competitorKeywordsEverywhereEnabled(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $availableIds = array_column(AiModelConfig::listAvailableModels(), 'id');

        $data = $request->validate([
            'model' => ['required', 'string', Rule::in($availableIds)],
            'default_check_interval_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'competitor_keywords_everywhere' => ['nullable', 'boolean'],
        ]);

        AiModelConfig::setModel((string) $data['model']);
        Setting::set(RankTrackerConfig::SETTING_CHECK_INTERVAL, (int) $data['default_check_interval_hours']);
        Setting::set(
            AuditConfig::SETTING_COMPETITOR_KEYWORDS_EVERYWHERE,
            $request->boolean('competitor_keywords_everywhere'),
        );

        return redirect()
            ->route('admin.settings')
            ->with('status', 'Settings saved.');
    }

    public function refreshModels(): RedirectResponse
    {
        AiModelConfig::clearModelsCache();

        return redirect()
            ->route('admin.settings')
            ->with('status', 'Model list refreshed from the provider.');
    }
}
