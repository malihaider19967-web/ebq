<?php

namespace App\Models;

use App\Enums\BacklinkType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Backlink extends Model
{
    /** @use HasFactory<\Database\Factories\BacklinkFactory> */
    use HasFactory;

    protected $fillable = [
        'website_id',
        'tracked_date',
        'referring_page_url',
        'target_page_url',
        'domain_authority',
        'spam_score',
        'anchor_text',
        'type',
        'is_dofollow',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tracked_date' => 'date',
            'is_dofollow' => 'boolean',
            'type' => BacklinkType::class,
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
