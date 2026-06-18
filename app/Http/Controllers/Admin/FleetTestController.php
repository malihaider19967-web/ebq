<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Read-only admin screen that presents the latest browser-based (Dusk) fleet
 * E2E test as a screenshot slideshow with step descriptions. The run writes its
 * screenshots + manifest to storage/app/public/fleet-test/ (served via the
 * public storage symlink). Pure presentation — no fleet actions here.
 */
class FleetTestController extends Controller
{
    public function index(): View
    {
        $manifest = ['title' => 'Fleet UI E2E Test', 'generated_at' => null, 'slides' => []];
        if (Storage::disk('public')->exists('fleet-test/manifest.json')) {
            $decoded = json_decode((string) Storage::disk('public')->get('fleet-test/manifest.json'), true);
            if (is_array($decoded)) {
                $manifest = array_merge($manifest, $decoded);
            }
        }

        $slides = array_map(fn ($s) => [
            'url' => asset('storage/fleet-test/'.($s['img'] ?? '')),
            'title' => $s['title'] ?? '',
            'desc' => $s['desc'] ?? '',
        ], $manifest['slides'] ?? []);

        return view('admin.fleet-test', ['manifest' => $manifest, 'slides' => $slides]);
    }
}
