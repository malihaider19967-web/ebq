<?php

namespace App\Models\Research;

use App\Models\SearchConsoleData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $query
 * @property string $normalized_query
 * @property string $query_hash
 * @property string $language
 * @property string $country
 * @property ?string $embedding
 */
class Keyword extends Model
{
    protected $fillable = [
        'query',
        'normalized_query',
        'query_hash',
        'language',
        'country',
        'embedding',
    ];

    public static function normalize(string $query): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $query) ?? ''));
    }

    public static function hashFor(string $query): string
    {
        return hash('sha256', self::normalize($query));
    }

    public function intelligence(): HasOne
    {
        return $this->hasOne(KeywordIntelligence::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(SerpSnapshot::class);
    }

    public function clusters(): BelongsToMany
    {
        return $this->belongsToMany(KeywordCluster::class, 'keyword_cluster_map', 'keyword_id', 'cluster_id')
            ->withPivot('confidence')
            ->withTimestamps();
    }

    public function niches(): BelongsToMany
    {
        return $this->belongsToMany(Niche::class, 'niche_keyword_map', 'keyword_id', 'niche_id')
            ->withPivot('relevance_score')
            ->withTimestamps();
    }

    public function searchConsoleRows(): HasMany
    {
        return $this->hasMany(SearchConsoleData::class);
    }
}
