<?php

namespace App\Models\Research;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anonymised cross-client aggregate. Privacy floor is enforced in the
 * recompute service (n>=3 sites); the column is here so consumers can
 * filter defensively too.
 */
class NicheAggregate extends Model
{
    protected $fillable = [
        'niche_id',
        'keyword_id',
        'avg_ctr_by_position',
        'avg_content_length',
        'avg_backlinks_estimate',
        'ranking_probability_score',
        'sample_site_count',
        'last_recomputed_at',
    ];

    protected function casts(): array
    {
        return [
            'avg_ctr_by_position' => 'array',
            'avg_content_length' => 'integer',
            'avg_backlinks_estimate' => 'integer',
            'ranking_probability_score' => 'float',
            'sample_site_count' => 'integer',
            'last_recomputed_at' => 'datetime',
        ];
    }

    public function niche(): BelongsTo
    {
        return $this->belongsTo(Niche::class);
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function scopeAboveSamplingFloor(Builder $q, int $floor = 3): Builder
    {
        return $q->where('sample_site_count', '>=', $floor);
    }
}
