<?php

namespace App\Models\Research;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CompetitorTopic extends Model
{
    protected $fillable = [
        'competitor_scan_id',
        'name',
        'centroid_keyword_id',
        'page_count',
        'top_keyword_ids',
    ];

    protected function casts(): array
    {
        return [
            'top_keyword_ids' => 'array',
            'page_count' => 'integer',
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(CompetitorScan::class, 'competitor_scan_id');
    }

    public function centroid(): BelongsTo
    {
        return $this->belongsTo(Keyword::class, 'centroid_keyword_id');
    }

    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(CompetitorPage::class, 'competitor_topic_pages', 'competitor_topic_id', 'competitor_page_id')
            ->withTimestamps();
    }
}
