<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class WebsiteVerification extends Model
{
    protected $fillable = [
        'website_id',
        'challenge_code',
        'verified_at',
        'last_attempt_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function isActive(): bool
    {
        return $this->verified_at === null && $this->expires_at->isFuture();
    }

    public static function issueFor(Website $website, int $ttlMinutes = 30): self
    {
        self::query()
            ->where('website_id', $website->id)
            ->whereNull('verified_at')
            ->delete();

        return self::create([
            'website_id' => $website->id,
            'challenge_code' => 'ebq-'.Str::lower(Str::random(40)),
            'expires_at' => Carbon::now()->addMinutes($ttlMinutes),
        ]);
    }
}
