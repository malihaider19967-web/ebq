<?php

namespace App\Models\Research;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerpFeature extends Model
{
    protected $fillable = [
        'snapshot_id',
        'feature_type',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(SerpSnapshot::class, 'snapshot_id');
    }
}
