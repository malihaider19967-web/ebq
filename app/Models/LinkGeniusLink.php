<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single discovered link from the Link Genius crawler. One row per
 * unique (source_post_id, target_url) pair. Status transitions:
 *   ok       — last fetch returned 2xx
 *   redirect — last fetch returned 3xx; `target_url` may differ from
 *              the recorded anchor href.
 *   broken   — last fetch returned 4xx/5xx or timed out.
 *
 * Periodic recheck via `App\Jobs\LinkGenius\CrawlWebsiteJob`; on-demand
 * recheck per link via `LinkGeniusController::recheck`.
 */
class LinkGeniusLink extends Model
{
    protected $table = 'link_genius_links';

    protected $fillable = [
        'website_id',
        'source_post_id',
        'target_url',
        'target_post_id',
        'anchor',
        'kind',
        'status',
        'http_status',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_checked_at' => 'datetime',
            'http_status'     => 'integer',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
