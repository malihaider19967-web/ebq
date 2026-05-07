<?php

namespace App\Models\Research;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitorScanKeyword extends Model
{
    protected $fillable = [
        'competitor_scan_id',
        'keyword_id',
        'total_occurrences',
        'top_pages_json',
    ];

    protected function casts(): array
    {
        return [
            'top_pages_json' => 'array',
            'total_occurrences' => 'integer',
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(CompetitorScan::class, 'competitor_scan_id');
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }
}
