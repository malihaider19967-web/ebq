<?php

namespace App\Models\Research;

use App\Models\Website;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property ?int $parent_id
 * @property bool $is_dynamic
 * @property bool $is_approved
 */
class Niche extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'parent_id',
        'is_dynamic',
        'is_approved',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'is_dynamic' => 'boolean',
            'is_approved' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(Keyword::class, 'niche_keyword_map', 'niche_id', 'keyword_id')
            ->withPivot('relevance_score')
            ->withTimestamps();
    }

    public function websites(): BelongsToMany
    {
        return $this->belongsToMany(Website::class, 'website_niche_map', 'niche_id', 'website_id')
            ->withPivot(['weight', 'is_primary', 'source', 'confidence', 'last_classified_at'])
            ->withTimestamps();
    }

    public function topicClusters(): HasMany
    {
        return $this->hasMany(NicheTopicCluster::class);
    }

    public function aggregates(): HasMany
    {
        return $this->hasMany(NicheAggregate::class);
    }
}
