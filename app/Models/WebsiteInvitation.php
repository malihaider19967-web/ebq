<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WebsiteInvitation extends Model
{
    protected $fillable = [
        'website_id',
        'email',
        'token',
        'invited_by_user_id',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isValid(): bool
    {
        return $this->expires_at->isFuture();
    }

    public static function findValidByPlainToken(string $plainToken): ?self
    {
        if ($plainToken === '') {
            return null;
        }

        $hash = hash('sha256', $plainToken);

        $invitation = self::query()->where('token', $hash)->first();

        if (! $invitation || ! $invitation->isValid()) {
            return null;
        }

        return $invitation;
    }

    /**
     * @return array{0: self, 1: string} Invitation model and plain token for URLs/email.
     */
    public static function issue(
        Website $website,
        string $email,
        int $invitedByUserId,
        int $expiresInDays = 14,
    ): array {
        $plain = Str::random(64);

        $invitation = self::query()->create([
            'website_id' => $website->id,
            'email' => Str::lower($email),
            'token' => hash('sha256', $plain),
            'invited_by_user_id' => $invitedByUserId,
            'expires_at' => now()->addDays($expiresInDays),
        ]);

        return [$invitation, $plain];
    }

    public function acceptFor(User $user): void
    {
        if (Str::lower($user->email) !== Str::lower($this->email)) {
            throw new \InvalidArgumentException('Invitation email does not match user.');
        }

        if ($user->id === $this->website->user_id) {
            $this->delete();

            return;
        }

        $this->website->members()->syncWithoutDetaching([$user->id]);
        $this->delete();
    }
}
