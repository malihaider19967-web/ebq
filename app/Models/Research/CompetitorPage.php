<?php

namespace App\Models\Research;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitorPage extends Model
{
    protected $fillable = [
        'competitor_scan_id',
        'url',
        'url_hash',
        'domain',
        'title',
        'meta_description',
        'headings_json',
        'word_count',
        'body_text',
        'status_code',
        'depth',
        'is_external',
        'seed_keyword_coverage',
    ];

    protected function casts(): array
    {
        return [
            'headings_json' => 'array',
            'seed_keyword_coverage' => 'array',
            'word_count' => 'integer',
            'status_code' => 'integer',
            'depth' => 'integer',
            'is_external' => 'boolean',
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(CompetitorScan::class, 'competitor_scan_id');
    }

    public function outlinks(): HasMany
    {
        return $this->hasMany(CompetitorOutlink::class, 'from_page_id');
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(CompetitorTopic::class, 'competitor_topic_pages', 'competitor_page_id', 'competitor_topic_id')
            ->withTimestamps();
    }
}
