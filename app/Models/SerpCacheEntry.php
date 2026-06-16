<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One cached organic SERP for a (keyword, country) pair — the shared,
 * cross-client lookup cache. Written + read through
 * {@see \App\Services\Competitive\SerpCache}.
 *
 * @property string $query_hash
 * @property string $query
 * @property string $gl
 * @property array $payload
 * @property \Illuminate\Support\Carbon $fetched_at
 * @property \Illuminate\Support\Carbon $expires_at
 */
class SerpCacheEntry extends Model
{
    protected $table = 'serp_cache';

    protected $fillable = [
        'query_hash',
        'query',
        'gl',
        'payload',
        'fetched_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'fetched_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public static function hash(string $keyword, string $gl): string
    {
        return hash('sha256', mb_strtolower(trim($keyword)).'|'.strtolower(trim($gl)));
    }

    public function isFresh(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isFuture();
    }
}
