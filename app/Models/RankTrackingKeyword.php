<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RankTrackingKeyword extends Model
{
    protected $fillable = [
        'website_id',
        'user_id',
        'keyword',
        'keyword_hash',
        'target_domain',
        'target_url',
        'search_engine',
        'search_type',
        'country',
        'language',
        'location',
        'device',
        'depth',
        'tbs',
        'autocorrect',
        'safe_search',
        'competitors',
        'tags',
        'notes',
        'check_interval_hours',
        'is_active',
        'last_checked_at',
        'next_check_at',
        'last_status',
        'last_error',
        'current_position',
        'best_position',
        'initial_position',
        'position_change',
        'current_url',
    ];

    protected function casts(): array
    {
        return [
            'autocorrect' => 'boolean',
            'safe_search' => 'boolean',
            'is_active' => 'boolean',
            'competitors' => 'array',
            'tags' => 'array',
            'last_checked_at' => 'datetime',
            'next_check_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(RankTrackingSnapshot::class)->orderByDesc('checked_at');
    }

    public function latestSnapshot(): HasMany
    {
        return $this->hasMany(RankTrackingSnapshot::class)->latest('checked_at')->limit(1);
    }

    public static function hashKeyword(string $keyword): string
    {
        return hash('sha256', mb_strtolower(trim($keyword)));
    }
}
