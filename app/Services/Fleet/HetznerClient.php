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
            'networks' => array_filter([$cfg['network_id'] ? (string) $cfg['network_id'] : null]),
            'firewalls' => $cfg['firewall_id'] ? [['firewall' => (string) $cfg['firewall_id']]] : [],
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

    public function deleteServer(int $serverId): array
    {
        return $this->request('delete', "/servers/{$serverId}");
    }

    /** @return array{ok:bool, status:?string, private_ip:?string, error:?string} */
    public function getServer(int $serverId): array
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
     * Whether a snapshot/image id still exists in this Hetzner project.
     *
     * Tri-state on purpose: callers must NOT confuse "couldn't reach the API" with
     * "the image is gone" — only a definitive 404 should trigger a snapshot rebuild.
     *
     * @return bool|null true = exists, false = confirmed gone (404),
     *                   null = couldn't determine (no token / network / 5xx)
     */
    public function imageExists(int $id): ?bool
    {
        $out = $this->request('get', "/images/{$id}");
        if ($out['ok']) {
            return true;
        }
        if (($out['status'] ?? null) === 404) {
            return false;
        }

        return null;
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
     * Available snapshot images, optionally filtered by a label selector
     * (e.g. 'role=ebq-crawl-worker' or 'role=ebq-db-node'). Powers the admin
     * snapshot dropdowns so an operator picks a real image id instead of typing
     * one (a wrong id => Hetzner "image not found" at provision time).
     *
     * @return array{ok:bool, snapshots:array<int,array{id:int,description:string,status:string,created:?string}>, error:?string}
     */
    public function listSnapshots(?string $selector = null): array
    {
        $query = ['type' => 'snapshot', 'per_page' => 50];
        if ($selector !== null && $selector !== '') {
            $query['label_selector'] = $selector;
        }
        $out = $this->request('get', '/images', $query);
        if (! $out['ok']) {
            return ['ok' => false, 'snapshots' => [], 'error' => $out['error']];
        }
        $images = array_filter($out['json']['images'] ?? [], static fn ($im): bool => ($im['status'] ?? '') === 'available');
        // Latest first — Hetzner `created` is ISO-8601, which sorts lexically.
        usort($images, static fn ($a, $b): int => strcmp((string) ($b['created'] ?? ''), (string) ($a['created'] ?? '')));
        $snapshots = array_map(static fn (array $im): array => [
            'id' => (int) $im['id'],
            'description' => (string) ($im['description'] ?? ''),
            'status' => (string) ($im['status'] ?? ''),
            // Date + time (UTC), e.g. "2026-06-18 00:42".
            'created' => isset($im['created']) ? str_replace('T', ' ', substr((string) $im['created'], 0, 16)) : null,
        ], $images);

        return ['ok' => true, 'snapshots' => array_values($snapshots), 'error' => null];
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
