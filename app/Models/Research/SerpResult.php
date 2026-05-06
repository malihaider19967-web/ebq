<?php

namespace App\Models\Research;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerpResult extends Model
{
    protected $fillable = [
        'snapshot_id',
        'rank',
        'url',
        'domain',
        'title',
        'snippet',
        'result_type',
        'is_low_quality',
    ];

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'is_low_quality' => 'boolean',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(SerpSnapshot::class, 'snapshot_id');
    }
}
