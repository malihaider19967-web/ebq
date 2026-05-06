<?php

namespace App\Models\Research;

use App\Models\Website;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteInternalLink extends Model
{
    protected $fillable = [
        'website_id',
        'from_page_id',
        'to_page_id',
        'anchor_text',
        'status',
        'discovered_at',
    ];

    protected function casts(): array
    {
        return [
            'discovered_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function fromPage(): BelongsTo
    {
        return $this->belongsTo(WebsitePage::class, 'from_page_id');
    }

    public function toPage(): BelongsTo
    {
        return $this->belongsTo(WebsitePage::class, 'to_page_id');
    }
}
