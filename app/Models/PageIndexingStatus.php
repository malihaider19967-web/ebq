<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageIndexingStatus extends Model
{
    protected $fillable = [
        'website_id',
        'page',
        'last_indexed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_indexed_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
