<?php

namespace App\Models\Research;

use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitorScan extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_CANCELLING = 'cancelling';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'website_id',
        'triggered_by_user_id',
        'seed_domain',
        'seed_url',
        'seed_keywords',
        'caps',
        'status',
        'progress',
        'page_count',
        'external_page_count',
        'last_heartbeat_at',
        'started_at',
        'finished_at',
        'cancelled_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'seed_keywords' => 'array',
            'caps' => 'array',
            'progress' => 'array',
            'page_count' => 'integer',
            'external_page_count' => 'integer',
            'last_heartbeat_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(CompetitorPage::class);
    }

    public function outlinks(): HasMany
    {
        return $this->hasMany(CompetitorOutlink::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(CompetitorTopic::class);
    }

    public function scanKeywords(): HasMany
    {
        return $this->hasMany(CompetitorScanKeyword::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING, self::STATUS_CANCELLING], true);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_QUEUED, self::STATUS_RUNNING, self::STATUS_CANCELLING]);
    }

    public function isHeartbeatStale(int $secondsThreshold = 60): bool
    {
        if ($this->status !== self::STATUS_RUNNING || $this->last_heartbeat_at === null) {
            return false;
        }

        return $this->last_heartbeat_at->diffInSeconds(now()) > $secondsThreshold;
    }
}
