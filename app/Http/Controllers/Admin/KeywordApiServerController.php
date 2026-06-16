<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KeywordApiRequest;
use App\Models\KeywordApiServer;
use App\Services\ClientActivityLogger;
use App\Services\KeywordFinder\KeywordFinderClient;
use App\Services\KeywordFinder\KeywordFinderPool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin CRUD for the self-hosted keyword API fleet. Plain controller + Blade,
 * matching {@see PlanController} / {@see ClientController}. Secrets (api_key,
 * webhook_secret) are encrypted at the model layer; on edit they're left
 * untouched unless the admin types a replacement.
 *
 * The "Test" actions probe a single server: connectivity (health/status/queue)
 * and a sample async lookup (volume + website-mode ideas) so an operator can
 * confirm a new server end-to-end before relying on it.
 */
class KeywordApiServerController extends Controller
{
    public function index(): View
    {
        $servers = KeywordApiServer::query()->orderBy('name')->get();

        // Most recent request per server (any status). A later success replaces
        // an earlier failure, so the page never shows a stale error once the
        // server recovers. Job-level results/errors arrive via the webhook.
        $latestIds = KeywordApiRequest::query()
            ->whereIn('keyword_api_server_id', $servers->pluck('id'))
            ->selectRaw('MAX(id) as id')
            ->groupBy('keyword_api_server_id')
            ->pluck('id');

        $lastRequests = KeywordApiRequest::query()
            ->whereIn('id', $latestIds)
            ->get()
            ->keyBy('keyword_api_server_id');

        return view('admin.keyword-servers.index', [
            'servers' => $servers,
            'lastRequests' => $lastRequests,
            'editId' => (int) request()->integer('edit'),
            'showCreate' => request()->boolean('new'),
        ]);
    }

    public function store(Request $request, ClientActivityLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'base_url' => ['required', 'url', 'max:255'],
            'api_key' => ['required', 'string', 'max:255'],
            'webhook_secret' => ['required', 'string', 'max:255'],
            'default_location' => ['nullable', 'string', 'max:120'],
            'default_language' => ['nullable', 'string', 'max:60'],
            'weight' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $server = KeywordApiServer::create([
            'name' => $data['name'],
            'base_url' => $data['base_url'],
            'api_key' => $data['api_key'],
            'webhook_secret' => $data['webhook_secret'],
            'default_location' => $data['default_location'] ?: 'United States',
            'default_language' => $data['default_language'] ?: 'English',
            'weight' => (int) ($data['weight'] ?? 1),
            'is_active' => $request->boolean('is_active'),
        ]);

        $logger->log('admin.keyword_server_created', meta: ['id' => $server->id, 'name' => $server->name]);

        return redirect()->route('admin.keyword-servers.index')
            ->with('status', "Server “{$server->name}” added.");
    }

    public function update(Request $request, KeywordApiServer $keywordServer, ClientActivityLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'base_url' => ['required', 'url', 'max:255'],
            // Optional on edit: blank means "keep the existing secret".
            'api_key' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'default_location' => ['nullable', 'string', 'max:120'],
            'default_language' => ['nullable', 'string', 'max:60'],
            'weight' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $keywordServer->fill([
            'name' => $data['name'],
            'base_url' => $data['base_url'],
            'default_location' => $data['default_location'] ?: 'United States',
            'default_language' => $data['default_language'] ?: 'English',
            'weight' => (int) ($data['weight'] ?? 1),
            'is_active' => $request->boolean('is_active'),
        ]);
        if (! empty($data['api_key'])) {
            $keywordServer->api_key = $data['api_key'];
        }
        if (! empty($data['webhook_secret'])) {
            $keywordServer->webhook_secret = $data['webhook_secret'];
        }
        $keywordServer->save();

        $logger->log('admin.keyword_server_updated', meta: ['id' => $keywordServer->id, 'name' => $keywordServer->name]);

        return redirect()->route('admin.keyword-servers.index')
            ->with('status', "Server “{$keywordServer->name}” updated.");
    }

    public function destroy(KeywordApiServer $keywordServer, ClientActivityLogger $logger): RedirectResponse
    {
        $name = $keywordServer->name;
        $logger->log('admin.keyword_server_deleted', meta: ['id' => $keywordServer->id, 'name' => $name]);
        $keywordServer->delete();

        return redirect()->route('admin.keyword-servers.index')
            ->with('status', "Server “{$name}” removed.");
    }

    /** Live connectivity probe — refreshes health columns and flashes full detail. */
    public function test(KeywordApiServer $keywordServer): RedirectResponse
    {
        $client = new KeywordFinderClient($keywordServer);

        $health = $client->probe('/health', auth: false);
        $status = $client->probe('/status');
        $queue = $client->probe('/queue');

        $healthBody = is_array($health['body']) ? $health['body'] : [];
        $statusBody = is_array($status['body']) ? $status['body'] : [];
        $queueBody = is_array($queue['body']) ? $queue['body'] : [];

        $healthy = $health['ok'] && (($healthBody['ok'] ?? false) === true);
        $loggedIn = $status['ok'] ? (bool) ($statusBody['loggedIn'] ?? false) : null;

        $keywordServer->forceFill([
            'is_healthy' => $healthy && ($loggedIn !== false),
            'logged_in' => $loggedIn,
            'last_queue_waiting' => isset($queueBody['waiting']) && is_numeric($queueBody['waiting']) ? (int) $queueBody['waiting'] : null,
            'last_queue_running' => isset($queueBody['running']) && is_numeric($queueBody['running']) ? (int) $queueBody['running'] : null,
            'last_health_at' => now(),
            'last_error' => $healthy ? ($statusBody['reason'] ?? null) : ($health['error'] ?? 'Health check failed'),
        ])->save();

        session()->flash('keyword_test', [
            'server' => $keywordServer->name,
            'title' => 'Connectivity probe',
            'sections' => [
                $this->probeSection('GET /health', $health, authSent: false),
                $this->probeSection('GET /status', $status, authSent: true),
                $this->probeSection('GET /queue', $queue, authSent: true),
            ],
        ]);

        return redirect()->route('admin.keyword-servers.index');
    }

    /**
     * Dispatch a sample keyword lookup against this one server, with full detail.
     * Uses the IDEAS endpoint (seed expansion) — same as the live user-facing
     * flow — so the admin sees the complete, unfiltered result set the server
     * actually returns (the user page filters this down to their query).
     */
    public function testKeyword(Request $request, KeywordApiServer $keywordServer, KeywordFinderPool $pool): RedirectResponse
    {
        $keyword = trim((string) $request->input('keyword', 'seo audit')) ?: 'seo audit';
        $req = $pool->dispatchIdeas(['seeds' => [$keyword]], only: $keywordServer, countryKey: 'us');

        $this->flashDispatchDetail($keywordServer, "Keyword ideas — “{$keyword}”", $pool, $req);

        return redirect()->route('admin.keyword-servers.index');
    }

    /** Dispatch a sample website-mode discovery against this one server, with full detail. */
    public function testWebsite(Request $request, KeywordApiServer $keywordServer, KeywordFinderPool $pool): RedirectResponse
    {
        $url = trim((string) $request->input('url', 'https://example.com')) ?: 'https://example.com';
        $req = $pool->dispatchIdeas(['url' => $url, 'scope' => 'site'], only: $keywordServer);

        $this->flashDispatchDetail($keywordServer, "Website discovery — “{$url}”", $pool, $req);

        return redirect()->route('admin.keyword-servers.index');
    }

    /**
     * Shape a GET probe result into a request/response section for the panel.
     *
     * @param  array{method: string, url: string, status: ?int, ok: bool, body: mixed, error: ?string}  $probe
     * @return array<string, mixed>
     */
    private function probeSection(string $label, array $probe, bool $authSent): array
    {
        return [
            'label' => $label,
            'request' => [
                'method' => $probe['method'],
                'url' => $probe['url'],
                'headers' => ['Accept' => 'application/json', 'x-api-key' => $authSent ? '••• (redacted)' : '(not sent)'],
            ],
            'response' => [
                'status' => $probe['status'],
                'ok' => $probe['ok'],
                'body' => $probe['body'],
                'error' => $probe['error'],
            ],
        ];
    }

    private function flashDispatchDetail(KeywordApiServer $server, string $title, KeywordFinderPool $pool, KeywordApiRequest $req): void
    {
        $outcome = $pool->lastOutcome();

        session()->flash('keyword_test', [
            'server' => $server->name,
            'title' => $title,
            'request_id' => $req->request_id,
            'request_status' => $req->status,
            'request_error' => $req->error,
            'sections' => [[
                'label' => 'POST '.($pool->lastEndpoint() ?? ''),
                'request' => [
                    'method' => 'POST',
                    'url' => $pool->lastEndpoint(),
                    'headers' => ['Content-Type' => 'application/json', 'x-api-key' => '••• (redacted)'],
                    'body' => $pool->lastRequestBody(),
                ],
                'response' => $outcome === null ? null : [
                    'status' => $outcome['status'],
                    'ok' => $outcome['ok'],
                    'body' => $outcome['body'],
                    'error' => $outcome['error'],
                ],
            ]],
            'note' => 'The server only ACKs synchronously here. Final keyword data arrives later via the webhook and appears in the “Last result” panel for this server.',
        ]);
    }
}
