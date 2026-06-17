<?php

namespace App\Services\Fleet;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the Hetzner Cloud REST API (https://api.hetzner.cloud/v1).
 * There is no hcloud CLI on the boxes, so we call the HTTP API directly with the
 * web box's HCLOUD_TOKEN (config/services.php → `hetzner.token`).
 *
 * Mirrors {@see \App\Services\KeywordFinder\KeywordFinderClient}: never throws on
 * a network/HTTP error — returns a structured outcome so the autoscaler/fleet
 * service degrades (skips a tick) instead of crashing the scheduler.
 *
 * Only the web box ever instantiates this; ephemeral worker boxes never hold the
 * token or call the API.
 */
class HetznerClient
{
    private const BASE = 'https://api.hetzner.cloud/v1';

    public function configured(): bool
    {
        return (bool) config('services.hetzner.token');
    }

    /**
     * Create a worker server attached to the private network + firewall.
     *
     * @param  array{user_data?:string,server_type?:string,image?:string,labels?:array<string,string>}  $opts
     * @return array{ok:bool, server_id:?int, private_ip:?string, public_ip:?string, status:?string, error:?string}
     */
    public function createServer(string $name, array $opts = []): array
    {
        $cfg = config('services.hetzner');
        $body = array_filter([
            'name' => $name,
            'server_type' => $opts['server_type'] ?? null,
            'image' => $opts['image'] ?? null,
            'location' => $cfg['location'] ?? null,
            'start_after_create' => true,
            'ssh_keys' => array_filter([$cfg['ssh_key_id'] ?? null]),
            'networks' => array_filter([$cfg['network_id'] ? (int) $cfg['network_id'] : null]),
            'firewalls' => $cfg['firewall_id'] ? [['firewall' => (int) $cfg['firewall_id']]] : [],
            'labels' => array_merge(['role' => 'ebq-crawl-worker', 'managed-by' => 'ebq-autoscaler'], $opts['labels'] ?? []),
            'user_data' => $opts['user_data'] ?? null,
        ], static fn ($v) => $v !== null && $v !== []);

        $out = $this->request('post', '/servers', $body);
        if (! $out['ok']) {
            return ['ok' => false, 'server_id' => null, 'private_ip' => null, 'public_ip' => null, 'status' => null, 'error' => $out['error']];
        }
        $server = $out['json']['server'] ?? [];

        return [
            'ok' => true,
            'server_id' => isset($server['id']) ? (int) $server['id'] : null,
            'private_ip' => $server['private_net'][0]['ip'] ?? null,
            'public_ip' => $server['public_net']['ipv4']['ip'] ?? null,
            'status' => $server['status'] ?? null,
            'error' => null,
        ];
    }

    public function deleteServer(string $serverId): array
    {
        return $this->request('delete', "/servers/{$serverId}");
    }

    /** @return array{ok:bool, status:?string, private_ip:?string, error:?string} */
    public function getServer(string $serverId): array
    {
        $out = $this->request('get', "/servers/{$serverId}");
        if (! $out['ok']) {
            return ['ok' => false, 'status' => null, 'private_ip' => null, 'error' => $out['error']];
        }
        $server = $out['json']['server'] ?? [];

        return [
            'ok' => true,
            'status' => $server['status'] ?? null, // initializing|starting|running|stopping|off|deleting|...
            'private_ip' => $server['private_net'][0]['ip'] ?? null,
            'error' => null,
        ];
    }

    /**
     * List servers we manage (by label). Used by reconcile to find orphans.
     *
     * @return array{ok:bool, servers:array<int,array{id:int,name:string,status:string,private_ip:?string}>, error:?string}
     */
    public function listByLabel(string $selector = 'role=ebq-crawl-worker'): array
    {
        $out = $this->request('get', '/servers', ['label_selector' => $selector]);
        if (! $out['ok']) {
            return ['ok' => false, 'servers' => [], 'error' => $out['error']];
        }
        $servers = array_map(static fn (array $s): array => [
            'id' => (int) $s['id'],
            'name' => (string) ($s['name'] ?? ''),
            'status' => (string) ($s['status'] ?? ''),
            'private_ip' => $s['private_net'][0]['ip'] ?? null,
        ], $out['json']['servers'] ?? []);

        return ['ok' => true, 'servers' => $servers, 'error' => null];
    }

    /**
     * @param  array<string,mixed>  $payload  query (GET) or body (POST)
     * @return array{ok:bool, status:?int, json:?array, error:?string}
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'status' => null, 'json' => null, 'error' => 'HCLOUD_TOKEN not configured'];
        }
        try {
            $req = $this->http();
            $response = match ($method) {
                'get' => $req->get(self::BASE.$path, $payload),
                'post' => $req->post(self::BASE.$path, $payload),
                'delete' => $req->delete(self::BASE.$path),
                default => throw new \InvalidArgumentException("bad method {$method}"),
            };

            $json = $response->json();
            $json = is_array($json) ? $json : null;

            if ($response->successful()) {
                return ['ok' => true, 'status' => $response->status(), 'json' => $json, 'error' => null];
            }

            $error = $json['error']['message'] ?? ('HTTP '.$response->status());
            Log::warning('HetznerClient non-2xx', ['method' => $method, 'path' => $path, 'status' => $response->status(), 'error' => $error]);

            return ['ok' => false, 'status' => $response->status(), 'json' => $json, 'error' => $error];
        } catch (\Throwable $e) {
            Log::warning('HetznerClient threw', ['method' => $method, 'path' => $path, 'message' => $e->getMessage()]);

            return ['ok' => false, 'status' => null, 'json' => null, 'error' => $e->getMessage()];
        }
    }

    private function http(): PendingRequest
    {
        return Http::withToken((string) config('services.hetzner.token'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.hetzner.request_timeout_s', 30))
            ->connectTimeout(10)
            ->retry(2, 500, throw: false);
    }
}
