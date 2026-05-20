<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AiModelConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AiModelSettingsController extends Controller
{
    public function edit(): View
    {
        $models = AiModelConfig::listAvailableModels();
        return view('admin.ai-model.settings', [
            'currentModel' => AiModelConfig::currentModel(),
            'models'       => $models,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $availableIds = array_column(AiModelConfig::listAvailableModels(), 'id');

        $data = $request->validate([
            // Validate against the live list — prevents typos and
            // models the API key has no access to.
            'model' => ['required', 'string', Rule::in($availableIds)],
        ]);

        AiModelConfig::setModel((string) $data['model']);

        return redirect()
            ->route('admin.ai-model.settings')
            ->with('status', 'AI model updated. New requests use '.$data['model'].'.');
    }

    public function refresh(): RedirectResponse
    {
        AiModelConfig::clearModelsCache();
        return redirect()
            ->route('admin.ai-model.settings')
            ->with('status', 'Model list refreshed from the provider.');
    }
}
