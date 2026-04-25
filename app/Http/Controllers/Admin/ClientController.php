<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientActivity;
use App\Models\User;
use App\Services\ClientActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $sort = (string) $request->query('sort', 'recent');

        // ─── Compact summary cards: total / admins / disabled / new this week ─
        $summary = [
            'total' => User::query()->count(),
            'admins' => User::query()->where('is_admin', true)->count(),
            'disabled' => User::query()->where('is_disabled', true)->count(),
            'new_7d' => User::query()->where('created_at', '>=', Carbon::now()->subDays(7))->count(),
        ];

        $monthStart = Carbon::now()->startOfMonth();
        $costPerKeyword = (float) config('services.keywords_everywhere.cost_per_keyword_usd', 0.0001);
        $costPerCall = (float) config('services.serper.cost_per_call_usd', 0.0003);

        // ─── Per-user enrichments via correlated sub-selects so the listing
        //     stays a single query as it grows. None of these are filterable
        //     (we only use them for display), so sub-selects are fine here. ─
        $clients = User::query()
            ->select('users.*')
            ->selectSub(
                fn ($q) => $q->from('websites')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('websites.user_id', 'users.id'),
                'websites_count'
            )
            ->selectSub(
                fn ($q) => $q->from('client_activities')
                    ->selectRaw('MAX(created_at)')
                    ->whereColumn('client_activities.user_id', 'users.id'),
                'last_activity_at'
            )
            ->selectSub(
                fn ($q) => $q->from('client_activities')
                    ->selectRaw('COALESCE(SUM(units_consumed), 0)')
                    ->whereColumn('client_activities.user_id', 'users.id')
                    ->where('provider', 'keywords_everywhere')
                    ->where('created_at', '>=', $monthStart),
                'ke_units_mtd'
            )
            ->selectSub(
                fn ($q) => $q->from('client_activities')
                    ->selectRaw('COALESCE(SUM(units_consumed), 0)')
                    ->whereColumn('client_activities.user_id', 'users.id')
                    ->where('provider', 'serp_api')
                    ->where('created_at', '>=', $monthStart),
                'serp_units_mtd'
            )
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%');
            }))
            ->when($status === 'admins', fn ($query) => $query->where('is_admin', true))
            ->when($status === 'active', fn ($query) => $query->where('is_disabled', false))
            ->when($status === 'disabled', fn ($query) => $query->where('is_disabled', true))
            ->when($sort === 'name', fn ($query) => $query->orderBy('name'))
            ->when($sort === 'email', fn ($query) => $query->orderBy('email'))
            ->when($sort === 'spend', fn ($query) => $query->orderByRaw('(ke_units_mtd + serp_units_mtd) DESC'))
            ->when(! in_array($sort, ['name', 'email', 'spend'], true), fn ($query) => $query->orderByDesc('created_at'))
            ->paginate(25)
            ->withQueryString();

        return view('admin.clients.index', [
            'clients' => $clients,
            'q' => $q,
            'status' => $status,
            'sort' => $sort,
            'summary' => $summary,
            'rates' => [
                'keywords_everywhere' => $costPerKeyword,
                'serp_api' => $costPerCall,
            ],
            'editId' => (int) $request->query('edit', 0),
            'showCreate' => (bool) $request->query('new', 0) || $request->old('_create_open'),
        ]);
    }

    public function store(Request $request, ClientActivityLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'is_admin' => ['nullable', 'boolean'],
        ]);

        $client = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_admin' => (bool) ($data['is_admin'] ?? false),
            'is_disabled' => false,
        ]);

        $logger->log('admin.client_created', userId: $client->id, meta: ['email' => $client->email]);

        return redirect()->route('admin.clients.index')->with('status', "Client {$client->email} created.");
    }

    public function update(Request $request, User $user, ClientActivityLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'is_admin' => ['nullable', 'boolean'],
            'is_disabled' => ['nullable', 'boolean'],
        ]);

        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'is_admin' => (bool) ($data['is_admin'] ?? false),
            'is_disabled' => (bool) ($data['is_disabled'] ?? false),
        ])->save();

        $logger->log('admin.client_updated', userId: $user->id, meta: [
            'is_admin' => $user->is_admin,
            'is_disabled' => $user->is_disabled,
        ]);

        return redirect()->route('admin.clients.index')->with('status', "Client {$user->email} updated.");
    }
}
