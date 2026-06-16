<?php

namespace App\Console\Commands;

use App\Models\KeywordApiServer;
use App\Services\KeywordFinder\KeywordFinderClient;
use Illuminate\Console\Command;

/**
 * Poll every active self-hosted keyword API server's /health, /status and
 * /queue endpoints and cache the results on the row. The load balancer
 * ({@see \App\Services\KeywordFinder\KeywordFinderPool}) reads these columns to
 * route to the least-busy healthy server and skip dead ones.
 *
 * Scheduled every 5 minutes (routes/console.php). Read-only against the API —
 * safe to run any time.
 */
class CheckKeywordServers extends Command
{
    protected $signature = 'ebq:check-keyword-servers {--id= : Only check one server by id}';

    protected $description = 'Refresh health/queue status for self-hosted keyword API servers';

    public function handle(): int
    {
        $query = KeywordApiServer::query()->where('is_active', true);
        if ($this->option('id')) {
            $query->where('id', (int) $this->option('id'));
        }

        $servers = $query->get();
        if ($servers->isEmpty()) {
            $this->info('No active keyword API servers to check.');

            return self::SUCCESS;
        }

        foreach ($servers as $server) {
            $this->checkServer($server);
        }

        $this->info(sprintf('Checked %d server(s).', $servers->count()));

        return self::SUCCESS;
    }

    private function checkServer(KeywordApiServer $server): void
    {
        $client = new KeywordFinderClient($server);

        $health = $client->health();
        $healthy = is_array($health) && (($health['ok'] ?? false) === true);

        $loggedIn = null;
        $error = null;
        if ($healthy) {
            $status = $client->status();
            if (is_array($status)) {
                $loggedIn = (bool) ($status['loggedIn'] ?? false);
                if ($loggedIn === false && isset($status['reason']) && is_string($status['reason'])) {
                    $error = $status['reason'];
                }
            }
        } else {
            $error = 'Health check failed';
        }

        $waiting = null;
        $running = null;
        $queue = $healthy ? $client->queue() : null;
        if (is_array($queue)) {
            $waiting = isset($queue['waiting']) && is_numeric($queue['waiting']) ? (int) $queue['waiting'] : null;
            $running = isset($queue['running']) && is_numeric($queue['running']) ? (int) $queue['running'] : null;
        }

        // A server that's up but logged out can't serve keyword data.
        $isHealthy = $healthy && ($loggedIn !== false);

        $server->forceFill([
            'is_healthy' => $isHealthy,
            'logged_in' => $loggedIn,
            'last_queue_waiting' => $waiting,
            'last_queue_running' => $running,
            'last_health_at' => now(),
            'last_error' => $error,
        ])->save();

        $this->line(sprintf(
            '  #%d %s — %s%s',
            $server->id,
            $server->name,
            $isHealthy ? '<info>healthy</info>' : '<error>unhealthy</error>',
            $waiting !== null ? " (queue {$waiting})" : '',
        ));
    }
}
