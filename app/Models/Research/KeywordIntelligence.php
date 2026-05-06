<?php

namespace App\Models\Research;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $keyword_id
 * @property ?int $search_volume
 * @property ?float $cpc
 * @property ?float $competition
 * @property ?string $intent
 * @property ?int $difficulty_score
 * @property ?int $serp_strength_score
 * @property ?float $volatility_score
 * @property ?\Illuminate\Support\Carbon $last_serp_at
 * @property ?\Illuminate\Support\Carbon $last_metrics_at
 */
class KeywordIntelligence extends Model
{
    protected $table = 'keyword_intelligence';

    protected $fillable = [
        'keyword_id',
        'search_volume',
        'cpc',
        'competition',
        'intent',
        'difficulty_score',
        'serp_strength_score',
        'volatility_score',
        'last_serp_at',
        'last_metrics_at',
    ];

    protected function casts(): array
    {
        return [
            'search_volume' => 'integer',
            'cpc' => 'float',
            'competition' => 'float',
            'difficulty_score' => 'integer',
            'serp_strength_score' => 'integer',
            'volatility_score' => 'float',
            'last_serp_at' => 'datetime',
            'last_metrics_at' => 'datetime',
        ];
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }
}
