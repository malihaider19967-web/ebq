<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\TeamPermissions;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

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
        return $this->belongsToMany(Website::class, 'website_user')
            ->withPivot(['role', 'permissions'])
            ->withTimestamps();
    }

    /**
     * Role for this user on the given website ('owner', 'admin', 'member') or null.
     */
    public function roleForWebsite(int $websiteId): ?string
    {
        if ($websiteId <= 0) {
            return null;
        }

        $ownerCount = Website::query()->whereKey($websiteId)->where('user_id', $this->id)->count();
        if ($ownerCount > 0) {
            return TeamPermissions::ROLE_OWNER;
        }

        $row = DB::table('website_user')
            ->where('website_id', $websiteId)
            ->where('user_id', $this->id)
            ->first();

        if (! $row) {
            return null;
        }

        return (string) ($row->role ?: TeamPermissions::ROLE_MEMBER);
    }

    /**
     * @return list<string>|null
     */
    public function permissionsForWebsite(int $websiteId): ?array
    {
        $role = $this->roleForWebsite($websiteId);
        if ($role === null) {
            return null;
        }
        if ($role === TeamPermissions::ROLE_OWNER || $role === TeamPermissions::ROLE_ADMIN) {
            return null;
        }

        $row = DB::table('website_user')
            ->where('website_id', $websiteId)
            ->where('user_id', $this->id)
            ->first();

        if (! $row || $row->permissions === null) {
            return null;
        }

        $decoded = json_decode((string) $row->permissions, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : null;
    }

    public function hasFeatureAccess(string $feature, int $websiteId): bool
    {
        $role = $this->roleForWebsite($websiteId);
        if ($role === null) {
            return false;
        }

        return TeamPermissions::allows($role, $this->permissionsForWebsite($websiteId), $feature);
    }

    public function canManageTeamFor(int $websiteId): bool
    {
        $role = $this->roleForWebsite($websiteId);

        return $role === TeamPermissions::ROLE_OWNER || $role === TeamPermissions::ROLE_ADMIN;
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
