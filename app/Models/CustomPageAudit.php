<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomPageAudit extends Model
{
    protected $fillable = [
        'website_id',
        'user_id',
        'page_url',
        'target_keyword',
        'page_audit_report_id',
        'status',
        'error_message',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pageAuditReport(): BelongsTo
    {
        return $this->belongsTo(PageAuditReport::class);
    }
}
