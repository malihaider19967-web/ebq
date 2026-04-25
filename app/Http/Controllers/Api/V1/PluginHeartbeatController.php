<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WebsitePluginInstall;
use App\Services\ClientActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PluginHeartbeatController extends Controller
{
    public function __invoke(Request $request, ClientActivityLogger $logger): JsonResponse
    {
        $website = $request->attributes->get('api_website');
        abort_unless($website, 401);

        $data = $request->validate([
            'installed_version' => ['nullable', 'string', 'max:40'],
            'channel' => ['nullable', Rule::in(['stable', 'beta'])],
            'site_url' => ['nullable', 'url', 'max:255'],
        ]);

        $install = WebsitePluginInstall::query()->updateOrCreate(
            ['website_id' => $website->id],
            [
                'installed_version' => $data['installed_version'] ?? null,
                'channel' => $data['channel'] ?? 'stable',
                'site_url' => $data['site_url'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        $logger->log(
            'plugin.heartbeat',
            userId: (int) $website->user_id,
            websiteId: (int) $website->id,
            provider: 'wordpress',
            meta: ['installed_version' => $install->installed_version, 'channel' => $install->channel]
        );

        return response()->json(['ok' => true]);
    }
}
