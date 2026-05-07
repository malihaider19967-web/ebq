<?php

namespace App\Models\Research;

use App\Models\Website;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchTarget extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SCANNING = 'scanning';
    public const STATUS_DONE = 'done';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_BLACKLISTED = 'blacklisted';

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_WEBSITE_ONBOARDING = 'website-onboarding';
    public const SOURCE_SERP_COMPETITOR = 'serp-competitor';
    public const SOURCE_OUTLINK = 'outlink';
    public const SOURCE_USER_SUPPLIED = 'user-supplied';

    public const PRIORITY_OWN_SITE = 100;
    public const PRIORITY_DIRECT_COMPETITOR = 80;
    public const PRIORITY_SERP_DOMAIN = 50;
    public const PRIORITY_OUTLINK = 20;

    protected $fillable = [
        'domain',
        'root_url',
        'source',
        'priority',
        'status',
        'attached_website_id',
        'last_scan_id',
        'last_scanned_at',
        'next_scan_at',
        'total_scans',
        'seed_keywords',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'total_scans' => 'integer',
            'last_scanned_at' => 'datetime',
            'next_scan_at' => 'datetime',
            'seed_keywords' => 'array',
        ];
    }

    public function attachedWebsite(): BelongsTo
    {
        return $this->belongsTo(Website::class, 'attached_website_id');
    }

    public function lastScan(): BelongsTo
    {
        return $this->belongsTo(CompetitorScan::class, 'last_scan_id');
    }

    public function scopeReadyToScan(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_QUEUED)
            ->where(function (Builder $w) {
                $w->whereNull('next_scan_at')->orWhere('next_scan_at', '<=', now());
            })
            ->orderByDesc('priority')
            ->orderBy('id');
    }
}
