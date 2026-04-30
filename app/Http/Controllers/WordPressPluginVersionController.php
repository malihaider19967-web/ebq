<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Services\PluginReleaseResolver;
use Illuminate\Http\JsonResponse;

class WordPressPluginVersionController extends Controller
{
    /**
     * Returns the current packaged plugin metadata. The plugin uses this to
     * decide whether a newer version is available and to populate the native
     * WordPress update flow.
     */
    public function __invoke(PluginReleaseResolver $resolver): JsonResponse
    {
        $channel = request()->query('channel', 'stable');
        $channel = in_array($channel, ['stable', 'beta'], true) ? $channel : 'stable';

        $sourceFile = base_path('ebq-seo-wp/ebq-seo.php');
        $release = $resolver->latestPublished($channel);

        $version = $release?->version ?: $this->parseVersion($sourceFile);
        $tested = $this->parseHeader($sourceFile, 'Tested up to') ?: '6.7';
        $requiresWp = $this->parseHeader($sourceFile, 'Requires at least') ?: '6.0';
        $requiresPhp = $this->parseHeader($sourceFile, 'Requires PHP') ?: '8.1';

        $packagedAt = $release?->published_at?->timestamp;
        if (! $packagedAt) {
            $zipPath = public_path('downloads/ebq-seo.zip');
            $packagedAt = is_file($zipPath) ? (int) filemtime($zipPath) : null;
        }

        return response()->json([
            'slug' => 'ebq-seo',
            'name' => 'EBQ SEO',
            'version' => $version,
            'channel' => $channel,
            'download_url' => route('wordpress.plugin.download', ['channel' => $channel]),
            'packaged_at' => $packagedAt ? date('c', $packagedAt) : null,
            'requires' => [
                'wp' => $requiresWp,
                'php' => $requiresPhp,
            ],
            'tested' => $tested,
            'homepage' => url('/features').'#wordpress',
            'changelog_url' => url('/features').'#wordpress',
            'release_notes' => $release?->release_notes,
            // Master per-feature kill-switch broadcast to every install
            // — connected and unconnected. The plugin's EBQ_Updater
            // captures this map every ~6 h via its existing transient
            // and stores it in `ebq_global_feature_flags`. The plugin's
            // is_enabled() AND's this against per-site flags so a
            // global FALSE always wins. Anonymous: no install identifier
            // is captured server-side from this endpoint.
            'global_features' => Website::globalFeatureFlags(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    private function parseVersion(string $file): string
    {
        return $this->parseHeader($file, 'Version') ?: '0.0.0';
    }

    private function parseHeader(string $file, string $key): ?string
    {
        if (! is_file($file)) {
            return null;
        }
        $contents = (string) file_get_contents($file, false, null, 0, 8192);
        if (preg_match('/^\s*\*\s*'.preg_quote($key, '/').'\s*:\s*(.+?)\s*$/mi', $contents, $m)) {
            return trim($m[1]);
        }

        return null;
    }
}
