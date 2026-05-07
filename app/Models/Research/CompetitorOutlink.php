<?php

namespace App\Models\Research;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitorOutlink extends Model
{
    protected $fillable = [
        'competitor_scan_id',
        'from_page_id',
        'to_url',
        'to_url_hash',
        'to_domain',
        'anchor_text',
        'is_external',
    ];

    protected function casts(): array
    {
        return [
            'is_external' => 'boolean',
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(CompetitorScan::class, 'competitor_scan_id');
    }

    public function fromPage(): BelongsTo
    {
        return $this->belongsTo(CompetitorPage::class, 'from_page_id');
    }
}
