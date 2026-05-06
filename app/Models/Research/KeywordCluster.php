<?php

namespace App\Models\Research;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KeywordCluster extends Model
{
    protected $fillable = [
        'cluster_name',
        'parent_cluster_id',
        'centroid_keyword_id',
        'signal',
        'last_recomputed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_recomputed_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_cluster_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_cluster_id');
    }

    public function centroid(): BelongsTo
    {
        return $this->belongsTo(Keyword::class, 'centroid_keyword_id');
    }

    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(Keyword::class, 'keyword_cluster_map', 'cluster_id', 'keyword_id')
            ->withPivot('confidence')
            ->withTimestamps();
    }
}
