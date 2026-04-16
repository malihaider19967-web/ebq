<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Website extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'domain',
        'ga_property_id',
        'gsc_site_url',
        'report_recipients',
        'last_analytics_sync_at',
        'last_search_console_sync_at',
    ];

    protected function casts(): array
    {
        return [
            'report_recipients' => 'array',
            'last_analytics_sync_at' => 'datetime',
            'last_search_console_sync_at' => 'datetime',
        ];
    }

    /**
     * Users who should receive reports. Falls back to the owner if none configured.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function getReportRecipientUsers(): \Illuminate\Database\Eloquent\Collection
    {
        $ids = $this->report_recipients;

        if (empty($ids)) {
            return User::where('id', $this->user_id)->get();
        }

        return User::whereIn('id', $ids)->get();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'website_user')->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WebsiteInvitation::class);
    }

    public function analyticsData(): HasMany
    {
        return $this->hasMany(AnalyticsData::class);
    }

    public function searchConsoleData(): HasMany
    {
        return $this->hasMany(SearchConsoleData::class);
    }

    public function backlinks(): HasMany
    {
        return $this->hasMany(Backlink::class);
    }

    public function pageIndexingStatuses(): HasMany
    {
        return $this->hasMany(PageIndexingStatus::class);
    }
}
