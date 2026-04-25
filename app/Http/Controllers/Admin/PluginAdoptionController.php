<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\View\View;

class PluginAdoptionController extends Controller
{
    public function index(Request $request): View
    {
        $domain = trim((string) $request->query('domain', ''));
        $items = Website::query()
            ->with(['owner:id,name,email', 'pluginInstall'])
            ->when($domain !== '', fn ($q) => $q->where('domain', 'like', '%'.$domain.'%'))
            ->orderBy('domain')
            ->paginate(30)
            ->withQueryString();

        $tokenCounts = PersonalAccessToken::query()
            ->where('tokenable_type', Website::class)
            ->whereIn('tokenable_id', $items->pluck('id')->all())
            ->selectRaw('tokenable_id, COUNT(*) as total, MAX(last_used_at) as last_used_at')
            ->groupBy('tokenable_id')
            ->get()
            ->keyBy('tokenable_id');

        return view('admin.plugin-adoption.index', [
            'items' => $items,
            'tokenCounts' => $tokenCounts,
            'domain' => $domain,
        ]);
    }
}
