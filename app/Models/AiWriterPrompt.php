<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $external_id
 * @property int $user_id
 * @property string $title
 * @property string $body
 */
class AiWriterPrompt extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'external_id',
        'user_id',
        'title',
        'body',
    ];

    protected static function booted(): void
    {
        static::creating(function (AiWriterPrompt $prompt): void {
            if (! $prompt->external_id) {
                $prompt->external_id = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'external_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
