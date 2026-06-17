<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class AnalyticsData extends Model
{
    use HasUlids;
    protected $fillable = [
        'website_id',
        'date',
        'users',
        'sessions',
        'source',
        'bounce_rate',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'bounce_rate' => 'float',
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
