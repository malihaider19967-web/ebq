<?php

namespace App\Models\Research;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SerpSnapshot extends Model
{
    protected $fillable = [
        'keyword_id',
        'device',
        'country',
        'location',
        'provider',
        'fetched_at',
        'fetched_on',
        'raw_payload_hash',
    ];

    protected function casts(): array
    {
        return [
            'fetched_at' => 'datetime',
            'fetched_on' => 'date',
        ];
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(SerpResult::class, 'snapshot_id');
    }

    public function features(): HasMany
    {
        return $this->hasMany(SerpFeature::class, 'snapshot_id');
    }
}
