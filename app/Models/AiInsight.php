<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class AiInsight extends Model
{
    protected $fillable = ['website_id', 'date', 'page', 'payload'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'payload' => 'array',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
