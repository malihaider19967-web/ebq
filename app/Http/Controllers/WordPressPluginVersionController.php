<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class WordPressPluginVersionController extends Controller
{
    /**
     * Returns the current packaged plugin metadata. The plugin uses this to
     * decide whether a newer version is available and to populate the native
     * WordPress update flow.
     */
    public function __invoke(): JsonResponse
    {
        $sourceFile = base_path('ebq-seo-wp/ebq-seo.php');
        $zipPath = public_path('downloads/ebq-seo.zip');

        $version = $this->parseVersion($sourceFile);
        $tested = $this->parseHeader($sourceFile, 'Tested up to') ?: '6.7';
        $requiresWp = $this->parseHeader($sourceFile, 'Requires at least') ?: '6.0';
        $requiresPhp = $this->parseHeader($sourceFile, 'Requires PHP') ?: '8.1';

        $packagedAt = is_file($zipPath) ? (int) filemtime($zipPath) : null;

        return response()->json([
            'slug' => 'ebq-seo',
            'name' => 'EBQ SEO',
            'version' => $version,
            'download_url' => route('wordpress.plugin.download'),
            'packaged_at' => $packagedAt ? date('c', $packagedAt) : null,
            'requires' => [
                'wp' => $requiresWp,
                'php' => $requiresPhp,
            ],
            'tested' => $tested,
            'homepage' => url('/features').'#wordpress',
            'changelog_url' => url('/features').'#wordpress',
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
