<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'timezone',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_growth_report_sent_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function googleAccounts(): HasMany
    {
        return $this->hasMany(GoogleAccount::class);
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    public function customPageAudits(): HasMany
    {
        return $this->hasMany(CustomPageAudit::class);
    }

    public function sharedWebsites(): BelongsToMany
    {
        return $this->belongsToMany(Website::class, 'website_user')->withTimestamps();
    }

    public function timezoneForDisplay(): string
    {
        $tz = $this->timezone;
        if (is_string($tz) && $tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
            return $tz;
        }

        return (string) config('app.timezone');
    }

    /**
     * Websites this user owns or has been granted access to.
     *
     * @return Builder<Website>
     */
    public function accessibleWebsitesQuery(): Builder
    {
        return Website::query()
            ->where(function (Builder $q): void {
                $q->where('websites.user_id', $this->id)
                    ->orWhereExists(function ($sub): void {
                        $sub->selectRaw('1')
                            ->from('website_user')
                            ->whereColumn('website_user.website_id', 'websites.id')
                            ->where('website_user.user_id', $this->id);
                    });
            });
    }

    public function hasAccessibleWebsites(): bool
    {
        return $this->accessibleWebsitesQuery()->exists();
    }

    public function canViewWebsiteId(int $websiteId): bool
    {
        if ($websiteId <= 0) {
            return false;
        }

        $website = Website::find($websiteId);

        return $website !== null && $this->can('view', $website);
    }
}
