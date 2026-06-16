<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An admin-managed outbound proxy for the crawler's anti-blocking pool.
 * `url` is a normalised Guzzle proxy URL: scheme://[user:pass@]host:port.
 * (The pool also merges proxies parsed from proxylist.txt at runtime.)
 */
class Proxy extends Model
{
    protected $fillable = [
        'label', 'url', 'url_hash', 'active', 'fail_count', 'success_count',
        'last_used_at', 'last_ok_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'fail_count' => 'integer',
            'success_count' => 'integer',
            'last_used_at' => 'datetime',
            'last_ok_at' => 'datetime',
        ];
    }

    public static function hashUrl(string $url): string
    {
        return hash('sha256', trim($url));
    }
}
