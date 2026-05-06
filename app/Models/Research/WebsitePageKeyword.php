<?php

namespace App\Models\Research;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsitePageKeyword extends Model
{
    protected $table = 'website_page_keyword_map';

    protected $fillable = [
        'page_id',
        'keyword_id',
        'source',
        'position_avg',
        'clicks_30d',
        'impressions_30d',
    ];

    protected function casts(): array
    {
        return [
            'position_avg' => 'float',
            'clicks_30d' => 'integer',
            'impressions_30d' => 'integer',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(WebsitePage::class, 'page_id');
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }
}
