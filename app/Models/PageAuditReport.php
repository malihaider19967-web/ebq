<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageAuditReport extends Model
{
    protected $fillable = [
        'website_id',
        'page',
        'page_hash',
        'status',
        'audited_at',
        'http_status',
        'response_time_ms',
        'page_size_bytes',
        'error_message',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'audited_at' => 'datetime',
            'result' => 'array',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
