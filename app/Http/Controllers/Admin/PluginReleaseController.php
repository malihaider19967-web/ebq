<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PluginRelease;
use App\Services\ClientActivityLogger;
use App\Services\PluginReleaseResolver;
use App\Services\WordPressPluginSourceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class PluginReleaseController extends Controller
{
    public function index(WordPressPluginSourceService $source): View
    {
        return view('admin.plugin-releases.index', [
            'releases' => PluginRelease::query()->latest('id')->paginate(20),
            'sourceVersion' => $source->readCurrentVersion(),
        ]);
    }

    public function store(
        Request $request,
        ClientActivityLogger $logger,
        PluginReleaseResolver $resolver,
        WordPressPluginSourceService $source,
    ): RedirectResponse {
        $data = $request->validate([
            'version' => ['required', 'string', 'max:40', 'regex:/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/'],
            'channel' => ['required', Rule::in(['stable', 'beta'])],
            'release_notes' => ['nullable', 'string'],
            'publish_mode' => ['required', Rule::in(['draft', 'now', 'schedule'])],
            'publish_at' => ['nullable', 'date'],
        ]);

        $exists = PluginRelease::query()
            ->where('slug', 'ebq-seo')
            ->where('version', $data['version'])
            ->where('channel', $data['channel'])
            ->exists();
        if ($exists) {
            return back()->withErrors(['version' => 'This version already exists for the selected channel.']);
        }

        $status = PluginRelease::STATUS_DRAFT;
        $publishAt = null;
        $publishedAt = null;

        if ($data['publish_mode'] === 'now') {
            try {
                $source->syncVersionAndPackage($data['version']);
            } catch (InvalidArgumentException $e) {
                return back()->withErrors(['version' => $e->getMessage()]);
            }
            $status = PluginRelease::STATUS_PUBLISHED;
            $publishedAt = now();
        } elseif ($data['publish_mode'] === 'schedule') {
            $status = PluginRelease::STATUS_SCHEDULED;
            $publishAt = $data['publish_at'] ? Carbon::parse($data['publish_at']) : null;
        }

        $release = PluginRelease::query()->create([
            'slug' => 'ebq-seo',
            'version' => $data['version'],
            'channel' => $data['channel'],
            'status' => $status,
            'release_notes' => $data['release_notes'] ?? null,
            'zip_path' => PluginRelease::ZIP_PUBLIC_BUILD,
            'publish_at' => $publishAt,
            'published_at' => $publishedAt,
            'created_by' => $request->user()?->id,
        ]);

        if ($release->status === PluginRelease::STATUS_PUBLISHED) {
            $resolver->markPublished($release);
        }

        $logger->log('admin.plugin_release_created', meta: [
            'release_id' => $release->id,
            'version' => $release->version,
            'channel' => $release->channel,
            'status' => $release->status,
        ]);

        return back()->with('status', 'Plugin release saved. Source version and ZIP are updated when you publish (or when a scheduled release runs).');
    }

    public function publish(
        PluginRelease $pluginRelease,
        PluginReleaseResolver $resolver,
        ClientActivityLogger $logger,
        WordPressPluginSourceService $source,
    ): RedirectResponse {
        try {
            $source->syncVersionAndPackage($pluginRelease->version);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['version' => $e->getMessage()]);
        }
        $pluginRelease->forceFill(['zip_path' => PluginRelease::ZIP_PUBLIC_BUILD])->save();

        $resolver->markPublished($pluginRelease);

        $logger->log('admin.plugin_release_published', meta: [
            'release_id' => $pluginRelease->id,
            'version' => $pluginRelease->version,
            'channel' => $pluginRelease->channel,
        ]);

        return back()->with('status', 'Release published; ebq-seo-wp version updated and plugin packaged.');
    }

    public function rollback(
        PluginRelease $pluginRelease,
        ClientActivityLogger $logger,
        WordPressPluginSourceService $source,
    ): RedirectResponse {
        if ($pluginRelease->status !== PluginRelease::STATUS_PUBLISHED) {
            return back()->withErrors(['release' => 'Only published releases can be rolled back.']);
        }

        $replacement = PluginRelease::query()
            ->where('slug', $pluginRelease->slug)
            ->where('channel', $pluginRelease->channel)
            ->where('status', PluginRelease::STATUS_ROLLED_BACK)
            ->whereNotNull('published_at')
            ->when($pluginRelease->published_at, fn ($q) => $q->where('published_at', '<', $pluginRelease->published_at))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();

        $pluginRelease->update([
            'status' => PluginRelease::STATUS_ROLLED_BACK,
            'rolled_back_at' => now(),
        ]);

        if ($replacement) {
            try {
                $source->syncVersionAndPackage($replacement->version);
            } catch (InvalidArgumentException $e) {
                return back()->withErrors(['release' => $e->getMessage()]);
            }
            $replacement->forceFill([
                'status' => PluginRelease::STATUS_PUBLISHED,
                'published_at' => now(),
                'rolled_back_at' => null,
                'rollback_of_id' => $pluginRelease->id,
                'zip_path' => PluginRelease::ZIP_PUBLIC_BUILD,
            ])->save();
        }

        $logger->log('admin.plugin_release_rolled_back', meta: [
            'release_id' => $pluginRelease->id,
            'replacement_id' => $replacement?->id,
        ]);

        return back()->with('status', $replacement
            ? 'Rolled back; source and package restored to the previous release version.'
            : 'Release marked rolled back (no prior release to restore).');
    }

    public function destroy(PluginRelease $pluginRelease): RedirectResponse
    {
        if ($pluginRelease->status === PluginRelease::STATUS_PUBLISHED) {
            return back()->withErrors(['release' => 'Cannot delete a published release. Roll it back first.']);
        }

        if ($pluginRelease->zip_path !== ''
            && $pluginRelease->zip_path !== PluginRelease::ZIP_PUBLIC_BUILD
            && str_starts_with($pluginRelease->zip_path, 'plugin-releases/')
            && Storage::disk('local')->exists($pluginRelease->zip_path)) {
            Storage::disk('local')->delete($pluginRelease->zip_path);
        }

        $pluginRelease->delete();

        return back()->with('status', 'Release deleted.');
    }
}
