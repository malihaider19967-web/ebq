<?php

namespace App\Models\Research;

use App\Models\Website;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsitePage extends Model
{
    protected $fillable = [
        'website_id',
        'url',
        'url_hash',
        'title',
        'content_length',
        'headings_json',
        'body_text',
        'last_crawled_at',
    ];

    protected function casts(): array
    {
        return [
            'content_length' => 'integer',
            'headings_json' => 'array',
            'last_crawled_at' => 'datetime',
        ];
    }

    public static function hashUrl(string $url): string
    {
        return hash('sha256', mb_strtolower(rtrim(trim($url), '/')));
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function keywordRows(): HasMany
    {
        return $this->hasMany(WebsitePageKeyword::class, 'page_id');
    }

    public function outboundInternalLinks(): HasMany
    {
        return $this->hasMany(WebsiteInternalLink::class, 'from_page_id');
    }

    public function inboundInternalLinks(): HasMany
    {
        return $this->hasMany(WebsiteInternalLink::class, 'to_page_id');
    }
}
