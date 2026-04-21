<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WordPressPluginDownloadController extends Controller
{
    /**
     * Always serves the latest packaged plugin ZIP with aggressive no-cache
     * headers so re-packaging invalidates every downstream cache instantly.
     * Filename includes the file's mtime so the browser treats each re-package
     * as a different download artifact.
     */
    public function __invoke(): BinaryFileResponse|Response
    {
        $path = public_path('downloads/ebq-seo.zip');

        if (! is_file($path)) {
            return response('Plugin not packaged yet — run `php artisan ebq:package-plugin`.', 404)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        clearstatcache(true, $path);
        $filename = 'ebq-seo-'.date('Ymd-His', filemtime($path)).'.zip';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
