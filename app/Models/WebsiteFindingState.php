<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user (per-website) overlay on a SHARED crawl_finding: each subscriber can
 * independently ignore/resolve a finding without affecting other users on the
 * same domain. No row = 'open'.
 *
 * @property int $website_id
 * @property int $finding_id
 * @property string $status
 */
class WebsiteFindingState extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_IGNORED = 'ignored';
    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = ['website_id', 'finding_id', 'status', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(CrawlFinding::class, 'finding_id');
    }
}
