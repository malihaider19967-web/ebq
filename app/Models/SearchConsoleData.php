<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class SearchConsoleData extends Model
{
    protected $fillable = [
        'website_id',
        'date',
        'query',
        'page',
        'clicks',
        'impressions',
        'position',
        'country',
        'device',
        'ctr',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'position' => 'float',
            'ctr' => 'float',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function scopeForDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('date', '<=', $to));
    }
}
