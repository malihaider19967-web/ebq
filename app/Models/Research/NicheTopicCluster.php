<?php

namespace App\Models\Research;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NicheTopicCluster extends Model
{
    protected $fillable = [
        'niche_id',
        'cluster_id',
        'topic_name',
        'total_search_volume',
        'avg_difficulty',
        'priority_score',
    ];

    protected function casts(): array
    {
        return [
            'total_search_volume' => 'integer',
            'avg_difficulty' => 'float',
            'priority_score' => 'float',
        ];
    }

    public function niche(): BelongsTo
    {
        return $this->belongsTo(Niche::class);
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(KeywordCluster::class, 'cluster_id');
    }
}
