<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bulk anchor-text replacement rule. The crawler / save_post hook
 * walks post content, matches `anchor_pattern` (literal substring,
 * case-insensitive), wraps the match in an `<a>` linking to
 * `replacement_url` with `replacement_anchor` (or the original match
 * when `replacement_anchor` is null), and bumps `applied_count`.
 *
 * `status=paused` rules are kept in the DB for future re-activation
 * but are skipped by the apply path.
 */
class LinkGeniusAnchorRule extends Model
{
    protected $table = 'link_genius_anchor_rules';

    protected $fillable = [
        'website_id',
        'anchor_pattern',
        'replacement_anchor',
        'replacement_url',
        'status',
        'applied_count',
        'last_applied_at',
    ];

    protected function casts(): array
    {
        return [
            'applied_count'   => 'integer',
            'last_applied_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
