<?php

namespace App\Models\Research;

use App\Models\Website;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeywordAlert extends Model
{
    public const TYPE_RANKING_DROP = 'ranking_drop';
    public const TYPE_NEW_OPPORTUNITY = 'new_opportunity';
    public const TYPE_SERP_CHANGE = 'serp_change';
    public const TYPE_VOLATILITY_SPIKE = 'volatility_spike';

    protected $fillable = [
        'website_id',
        'keyword_id',
        'type',
        'severity',
        'payload',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }
}
