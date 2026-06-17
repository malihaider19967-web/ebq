<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * A single self-hosted keyword-data API server. `api_key` and `webhook_secret`
 * are transparently encrypted at rest. Health columns are refreshed by
 * {@see \App\Console\Commands\CheckKeywordServers} and the on-demand admin
 * "Test" button.
 *
 * @property string $name
 * @property string $base_url
 * @property string $api_key
 * @property string $webhook_secret
 * @property string $default_location
 * @property string $default_language
 * @property int $weight
 * @property bool $is_active
 * @property ?bool $is_healthy
 * @property ?bool $logged_in
 * @property ?int $last_queue_waiting
 * @property ?int $last_queue_running
 * @property ?\Illuminate\Support\Carbon $last_health_at
 * @property ?string $last_error
 */
class KeywordApiServer extends Model
{
    use HasUlids;
    protected $fillable = [
        'name',
        'base_url',
        'api_key',
        'webhook_secret',
        'default_location',
        'default_language',
        'weight',
        'is_active',
        'is_healthy',
        'logged_in',
        'last_queue_waiting',
        'last_queue_running',
        'last_health_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'weight' => 'integer',
            'is_active' => 'boolean',
            'is_healthy' => 'boolean',
            'logged_in' => 'boolean',
            'last_queue_waiting' => 'integer',
            'last_queue_running' => 'integer',
            'last_health_at' => 'datetime',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(KeywordApiRequest::class);
    }

    /** Normalized base URL with no trailing slash. */
    public function baseUrl(): string
    {
        return rtrim(trim($this->base_url), '/');
    }

    /**
     * Candidate servers for load balancing: active and not known-unhealthy,
     * least-busy first (lowest queue depth), then highest weight. A server
     * that has never been health-checked (`is_healthy` null) is still a
     * candidate — we optimistically try it.
     */
    public function scopeRoutable(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->where(function (Builder $w): void {
                $w->whereNull('is_healthy')->orWhere('is_healthy', true);
            })
            ->orderByRaw('COALESCE(last_queue_waiting, 0) asc')
            ->orderByDesc('weight')
            ->orderBy('id');
    }
}
