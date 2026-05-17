<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\AuditConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditSettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.audit.settings', [
            'competitorKeywordsEverywhere' => AuditConfig::competitorKeywordsEverywhereEnabled(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'competitor_keywords_everywhere' => 'nullable|boolean',
        ]);

        Setting::set(
            AuditConfig::SETTING_COMPETITOR_KEYWORDS_EVERYWHERE,
            $request->boolean('competitor_keywords_everywhere'),
        );

        return redirect()
            ->route('admin.audit.settings')
            ->with('status', 'Page audit settings saved.');
    }
}
