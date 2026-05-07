<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Research\Niche;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pending dynamic-niche review queue. DiscoverEmergingNichesJob seeds rows
 * with is_dynamic=true / is_approved=false; this screen lets the admin
 * promote them into the curated tree (assigning a parent) or delete them.
 *
 * Promotion is the only way for a dynamic niche to start participating in
 * NicheClassificationService — the service filters on is_approved=true.
 */
class NicheCandidateController extends Controller
{
    public function index(): View
    {
        $candidates = Niche::query()
            ->where('is_dynamic', true)
            ->where('is_approved', false)
            ->orderByDesc('created_at')
            ->paginate(50);

        $parents = Niche::query()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.research.niche-candidates', [
            'candidates' => $candidates,
            'parents' => $parents,
        ]);
    }

    public function approve(Request $request, Niche $niche): RedirectResponse
    {
        abort_unless($niche->is_dynamic && ! $niche->is_approved, 422, 'Niche is not a pending candidate.');

        $data = $request->validate([
            'parent_id' => 'nullable|integer|exists:niches,id',
            'name' => 'nullable|string|max:255',
        ]);

        $niche->forceFill([
            'is_approved' => true,
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'] ?? $niche->name,
        ])->save();

        return back()->with('status', 'Niche "'.$niche->name.'" approved.');
    }

    public function destroy(Niche $niche): RedirectResponse
    {
        abort_unless($niche->is_dynamic && ! $niche->is_approved, 422, 'Only pending dynamic niches can be deleted here.');

        $slug = $niche->slug;
        $niche->delete();

        return back()->with('status', 'Candidate "'.$slug.'" rejected.');
    }
}
