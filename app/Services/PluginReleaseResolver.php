<?php

namespace App\Services;

use App\Models\PluginRelease;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Illuminate\Support\Facades\Log;

class PluginReleaseResolver
{
    public function __construct(
        private WordPressPluginSourceService $pluginSource,
    ) {}

    public function latestPublished(string $channel = 'stable'): ?PluginRelease
    {
        try {
            return PluginRelease::query()
                ->where('slug', 'ebq-seo')
                ->where('channel', $channel)
                ->where('status', PluginRelease::STATUS_PUBLISHED)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->first();
        } catch (QueryException) {
            return null;
        }
    }

    public function publishScheduled(): int
    {
        $updated = 0;

        try {
            PluginRelease::query()
                ->where('status', PluginRelease::STATUS_SCHEDULED)
                ->whereNotNull('publish_at')
                ->where('publish_at', '<=', now())
                ->orderBy('id')
                ->get()
                ->each(function (PluginRelease $release) use (&$updated): void {
                    try {
                        $this->pluginSource->syncVersionAndPackage($release->version);
                    } catch (InvalidArgumentException $e) {
                        Log::error('Scheduled plugin release failed to build', [
                            'release_id' => $release->id,
                            'version' => $release->version,
                            'message' => $e->getMessage(),
                        ]);

                        return;
                    }
                    $release->forceFill(['zip_path' => PluginRelease::ZIP_PUBLIC_BUILD])->save();
                    $this->markPublished($release);
                    $updated++;
                });
        } catch (QueryException) {
            return 0;
        }

        return $updated;
    }

    public function markPublished(PluginRelease $release): void
    {
        PluginRelease::query()
            ->where('slug', $release->slug)
            ->where('channel', $release->channel)
            ->where('id', '!=', $release->id)
            ->where('status', PluginRelease::STATUS_PUBLISHED)
            ->update(['status' => PluginRelease::STATUS_ROLLED_BACK, 'rolled_back_at' => now()]);

        $release->forceFill([
            'status' => PluginRelease::STATUS_PUBLISHED,
            'published_at' => now(),
            'rolled_back_at' => null,
        ])->save();
    }
}
