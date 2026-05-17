<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\RankTrackerConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RankTrackerSettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.rank-tracker.settings', [
            'checkIntervalHours' => RankTrackerConfig::checkIntervalHours(),
            'defaultDepth'       => RankTrackerConfig::DEFAULT_DEPTH,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'default_check_interval_hours' => 'required|integer|min:1|max:168',
        ]);

        Setting::set(
            RankTrackerConfig::SETTING_CHECK_INTERVAL,
            (int) $data['default_check_interval_hours'],
        );

        return redirect()
            ->route('admin.rank-tracker.settings')
            ->with('status', 'Rank tracker defaults saved. New keywords use this re-check interval.');
    }
}
