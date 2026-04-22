<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $competitor_domain
 * @property string $referring_page_url
 * @property string $referring_page_hash
 * @property ?string $referring_domain
 * @property ?string $anchor_text
 * @property ?int $domain_authority
 * @property ?string $backlink_type
 * @property ?\Illuminate\Support\Carbon $first_seen_at
 * @property \Illuminate\Support\Carbon $fetched_at
 * @property \Illuminate\Support\Carbon $expires_at
 */
class CompetitorBacklink extends Model
{
    protected $fillable = [
        'competitor_domain',
        'referring_page_url',
        'referring_page_hash',
        'referring_domain',
        'anchor_text',
        'domain_authority',
        'backlink_type',
        'first_seen_at',
        'fetched_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'domain_authority' => 'integer',
            'first_seen_at' => 'date',
            'fetched_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public static function hashUrl(string $url): string
    {
        return hash('sha256', mb_strtolower(trim($url)));
    }

    /**
     * Strip protocol, www, port, path, query, fragment — leave the bare
     * registerable domain. Used to normalize competitor URLs from the SERP
     * benchmark into the cache key.
     */
    public static function extractDomain(string $url): string
    {
        $host = parse_url(trim($url), PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            // Fallback: treat input as domain if parse_url can't find one.
            $host = preg_replace('#^https?://#i', '', trim($url));
            $host = (string) strtok((string) $host, '/?#');
        }
        $host = strtolower((string) $host);

        return preg_replace('/^www\./', '', $host) ?? '';
    }

    public function scopeFresh(Builder $q): Builder
    {
        return $q->where('expires_at', '>', now());
    }

    public function scopeForDomain(Builder $q, string $domain): Builder
    {
        return $q->where('competitor_domain', $domain);
    }
}
