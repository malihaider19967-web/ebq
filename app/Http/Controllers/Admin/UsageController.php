<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin "API Usage" dashboard. Aggregates `client_activities` rows where
 * provider IN ('keywords_everywhere', 'serp_api') over a chosen window and
 * shows per-client + per-website spend, plus a recent-calls feed.
 *
 * Cost rates come from config/services.php so they can be tuned per
 * contract without redeploy.
 */
class UsageController extends Controller
{
    /** Provider id → display label / cost-per-unit (USD). */
    private const PROVIDERS = [
        'keywords_everywhere' => [
            'label' => 'Keywords Everywhere',
            'unit' => 'keyword',
        ],
        'serp_api' => [
            'label' => 'Serper SERP API',
            'unit' => 'call',
        ],
    ];

    public function index(Request $request): View
    {
        // ─── Date range — default 30 days, allow 7/30/90/custom. ───────
        $preset = $request->query('range', '30');
        if (in_array($preset, ['7', '30', '90'], true)) {
            $endDate = Carbon::now();
            $startDate = $endDate->copy()->subDays((int) $preset)->startOfDay();
        } else {
            $startDate = $this->parseDate($request->query('from')) ?? Carbon::now()->subDays(30)->startOfDay();
            $endDate = $this->parseDate($request->query('to')) ?? Carbon::now();
            $preset = 'custom';
        }
        if ($endDate->lt($startDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $providerFilter = (string) $request->query('provider', '');
        $userFilter = (int) $request->query('user_id', 0);

        $rates = $this->rates();

        // ─── Summary cards ─────────────────────────────────────────────
        $summary = $this->summary($startDate, $endDate, $rates);

        // ─── Top clients (provider × user totals) ──────────────────────
        $topClients = ClientActivity::query()
            ->selectRaw('user_id, provider,
                COUNT(*) AS calls,
                COALESCE(SUM(units_consumed), 0) AS units')
            ->whereIn('provider', array_keys(self::PROVIDERS))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($userFilter > 0, fn ($q) => $q->where('user_id', $userFilter))
            ->when($providerFilter !== '', fn ($q) => $q->where('provider', $providerFilter))
            ->whereNotNull('user_id')
            ->groupBy('user_id', 'provider')
            ->orderByDesc('units')
            ->limit(500)
            ->get();

        // Pivot to one row per user with both providers as columns + cost.
        $byClient = [];
        foreach ($topClients as $row) {
            $uid = (int) $row->user_id;
            $byClient[$uid] ??= ['user_id' => $uid, 'units' => 0, 'cost' => 0.0, 'providers' => []];
            $units = (int) $row->units;
            $cost = $units * ($rates[$row->provider] ?? 0);
            $byClient[$uid]['providers'][$row->provider] = [
                'calls' => (int) $row->calls,
                'units' => $units,
                'cost' => $cost,
            ];
            $byClient[$uid]['units'] += $units;
            $byClient[$uid]['cost'] += $cost;
        }
        usort($byClient, fn ($a, $b) => $b['cost'] <=> $a['cost']);
        $byClient = array_slice($byClient, 0, 50);

        // Hydrate user details in one query.
        $userIds = array_column($byClient, 'user_id');
        $users = $userIds
            ? \App\Models\User::query()->whereIn('id', $userIds)->get(['id', 'name', 'email'])->keyBy('id')
            : collect();

        // ─── Top websites ──────────────────────────────────────────────
        $byWebsite = ClientActivity::query()
            ->selectRaw('website_id, provider,
                COUNT(*) AS calls,
                COALESCE(SUM(units_consumed), 0) AS units')
            ->whereIn('provider', array_keys(self::PROVIDERS))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($userFilter > 0, fn ($q) => $q->where('user_id', $userFilter))
            ->when($providerFilter !== '', fn ($q) => $q->where('provider', $providerFilter))
            ->whereNotNull('website_id')
            ->groupBy('website_id', 'provider')
            ->orderByDesc('units')
            ->limit(200)
            ->get();

        $websitesAgg = [];
        foreach ($byWebsite as $row) {
            $wid = (int) $row->website_id;
            $websitesAgg[$wid] ??= ['website_id' => $wid, 'units' => 0, 'cost' => 0.0, 'providers' => []];
            $units = (int) $row->units;
            $cost = $units * ($rates[$row->provider] ?? 0);
            $websitesAgg[$wid]['providers'][$row->provider] = [
                'calls' => (int) $row->calls,
                'units' => $units,
                'cost' => $cost,
            ];
            $websitesAgg[$wid]['units'] += $units;
            $websitesAgg[$wid]['cost'] += $cost;
        }
        usort($websitesAgg, fn ($a, $b) => $b['cost'] <=> $a['cost']);
        $websitesAgg = array_slice($websitesAgg, 0, 30);
        $websiteIds = array_column($websitesAgg, 'website_id');
        $websites = $websiteIds
            ? \App\Models\Website::query()->whereIn('id', $websiteIds)->get(['id', 'domain', 'user_id'])->keyBy('id')
            : collect();

        // ─── Daily series (sparkline data) ─────────────────────────────
        $daily = ClientActivity::query()
            ->selectRaw('DATE(created_at) AS d, provider, COALESCE(SUM(units_consumed), 0) AS units')
            ->whereIn('provider', array_keys(self::PROVIDERS))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($userFilter > 0, fn ($q) => $q->where('user_id', $userFilter))
            ->when($providerFilter !== '', fn ($q) => $q->where('provider', $providerFilter))
            ->groupBy('d', 'provider')
            ->orderBy('d')
            ->get();
        $dailySeries = $this->buildDailySeries($daily, $startDate, $endDate);

        // ─── Recent calls feed (most-recent 50) ────────────────────────
        $recent = ClientActivity::query()
            ->with(['user:id,name,email', 'website:id,domain'])
            ->whereIn('provider', array_keys(self::PROVIDERS))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($userFilter > 0, fn ($q) => $q->where('user_id', $userFilter))
            ->when($providerFilter !== '', fn ($q) => $q->where('provider', $providerFilter))
            ->latest('id')
            ->limit(50)
            ->get();

        return view('admin.usage.index', [
            'preset' => $preset,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'providers' => self::PROVIDERS,
            'rates' => $rates,
            'summary' => $summary,
            'byClient' => $byClient,
            'users' => $users,
            'byWebsite' => $websitesAgg,
            'websites' => $websites,
            'dailySeries' => $dailySeries,
            'recent' => $recent,
            'filters' => [
                'provider' => $providerFilter,
                'user_id' => $userFilter,
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ],
        ]);
    }

    private function rates(): array
    {
        return [
            'keywords_everywhere' => (float) config('services.keywords_everywhere.cost_per_keyword_usd', 0.0001),
            'serp_api' => (float) config('services.serper.cost_per_call_usd', 0.0003),
        ];
    }

    /**
     * @return array{period: array, lifetime: array, this_month: array}
     */
    private function summary(Carbon $start, Carbon $end, array $rates): array
    {
        $monthStart = Carbon::now()->startOfMonth();

        $byProviderInRange = ClientActivity::query()
            ->selectRaw('provider, COALESCE(SUM(units_consumed), 0) AS units, COUNT(*) AS calls')
            ->whereIn('provider', array_keys(self::PROVIDERS))
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('provider')
            ->pluck(DB::raw("CONCAT(units, '|', calls)"), 'provider');

        $byProviderMonth = ClientActivity::query()
            ->selectRaw('provider, COALESCE(SUM(units_consumed), 0) AS units')
            ->whereIn('provider', array_keys(self::PROVIDERS))
            ->where('created_at', '>=', $monthStart)
            ->groupBy('provider')
            ->pluck('units', 'provider');

        $byProviderLifetime = ClientActivity::query()
            ->selectRaw('provider, COALESCE(SUM(units_consumed), 0) AS units')
            ->whereIn('provider', array_keys(self::PROVIDERS))
            ->groupBy('provider')
            ->pluck('units', 'provider');

        $shape = function ($map, array $rates): array {
            $totalUnits = 0; $totalCost = 0; $byProvider = [];
            foreach (self::PROVIDERS as $key => $_meta) {
                $raw = $map[$key] ?? '0|0';
                if (str_contains((string) $raw, '|')) {
                    [$units, $calls] = array_map('intval', explode('|', (string) $raw, 2));
                } else {
                    $units = (int) $raw; $calls = 0;
                }
                $cost = $units * ($rates[$key] ?? 0);
                $byProvider[$key] = ['units' => $units, 'calls' => $calls, 'cost' => $cost];
                $totalUnits += $units;
                $totalCost += $cost;
            }
            return ['units' => $totalUnits, 'cost' => $totalCost, 'providers' => $byProvider];
        };

        $shapeUnitsOnly = function ($map, array $rates): array {
            $totalUnits = 0; $totalCost = 0; $byProvider = [];
            foreach (self::PROVIDERS as $key => $_meta) {
                $units = (int) ($map[$key] ?? 0);
                $cost = $units * ($rates[$key] ?? 0);
                $byProvider[$key] = ['units' => $units, 'cost' => $cost];
                $totalUnits += $units;
                $totalCost += $cost;
            }
            return ['units' => $totalUnits, 'cost' => $totalCost, 'providers' => $byProvider];
        };

        return [
            'period' => $shape($byProviderInRange, $rates),
            'this_month' => $shapeUnitsOnly($byProviderMonth, $rates),
            'lifetime' => $shapeUnitsOnly($byProviderLifetime, $rates),
        ];
    }

    /**
     * Fill in missing days so the sparkline doesn't gap.
     *
     * @return array{labels: list<string>, series: array<string, list<int>>}
     */
    private function buildDailySeries($rows, Carbon $start, Carbon $end): array
    {
        $days = [];
        $cursor = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();
        while ($cursor->lte($endDay)) {
            $days[$cursor->toDateString()] = 0;
            $cursor->addDay();
        }

        $series = [];
        foreach (array_keys(self::PROVIDERS) as $provider) {
            $series[$provider] = $days; // copy
        }

        foreach ($rows as $r) {
            $d = (string) $r->d;
            $p = (string) $r->provider;
            if (! isset($series[$p][$d])) {
                continue;
            }
            $series[$p][$d] = (int) $r->units;
        }

        return [
            'labels' => array_keys($days),
            'series' => array_map('array_values', $series),
        ];
    }

    private function parseDate(?string $s): ?Carbon
    {
        if (! $s || trim($s) === '') return null;
        try {
            return Carbon::parse($s);
        } catch (\Throwable) {
            return null;
        }
    }
}
