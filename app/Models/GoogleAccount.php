<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class GoogleAccount extends Model
{
    use HasUlids;
    use HasFactory;

    protected $fillable = [
        'user_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'google_id',
        'email',
    ];

    /**
     * Human-friendly label for source pickers: the connected email, or a
     * stable fallback when an older row predates the email column.
     */
    public function label(): string
    {
        return $this->email ?: ('Google account #'.$this->id);
    }

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
