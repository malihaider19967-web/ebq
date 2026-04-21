<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

class Website extends Model
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'user_id',
        'domain',
        'ga_property_id',
        'gsc_site_url',
        'gsc_keyword_lookback_days',
        'report_recipients',
        'last_analytics_sync_at',
        'last_search_console_sync_at',
        'last_traffic_drop_alert_at',
        'last_rank_drop_alert_at',
    ];

    protected function casts(): array
    {
        return [
            'report_recipients' => 'array',
            'gsc_keyword_lookback_days' => 'integer',
            'last_analytics_sync_at' => 'datetime',
            'last_search_console_sync_at' => 'datetime',
            'last_traffic_drop_alert_at' => 'datetime',
            'last_rank_drop_alert_at' => 'datetime',
        ];
    }

    /**
     * Users who should receive reports. Falls back to the owner if none configured.
     *
     * @return Collection<int, User>
     */
    public function getReportRecipientUsers(): Collection
    {
        $ids = $this->report_recipients;

        if (empty($ids)) {
            return User::where('id', $this->user_id)->get();
        }

        return User::whereIn('id', $ids)->get();
    }

    /**
     * Rolling window (days) for Search Console rows used in page audits and page-level GSC UI.
     */
    public function effectiveGscKeywordLookbackDays(): int
    {
        $default = (int) config('audit.gsc_keyword_lookback_days_default', 28);
        $min = (int) config('audit.gsc_keyword_lookback_days_min', 7);
        $max = (int) config('audit.gsc_keyword_lookback_days_max', 480);
        $raw = $this->gsc_keyword_lookback_days;

        if ($raw === null) {
            return max($min, min($max, $default));
        }

        return max($min, min($max, (int) $raw));
    }

    /**
     * Inclusive lower bound date (Y-m-d) for GSC keyword aggregates: date >= today - N days.
     */
    public function gscKeywordWindowStartDate(?Carbon $today = null): string
    {
        $today ??= Carbon::today((string) config('app.timezone'));

        return $today->copy()->subDays($this->effectiveGscKeywordLookbackDays())->toDateString();
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
        return $this->belongsToMany(User::class, 'website_user')
            ->withPivot(['role', 'permissions'])
            ->withTimestamps();
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

    public function customPageAudits(): HasMany
    {
        return $this->hasMany(CustomPageAudit::class);
    }

    /**
     * Whether a page URL is on this website's domain or a subdomain of it (www normalized).
     */
    public function isAuditUrlForThisSite(string $url): bool
    {
        $domain = strtolower(trim((string) $this->domain));
        $domain = preg_replace('/^www\./', '', $domain) ?: $domain;
        if ($domain === '') {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);
        $host = preg_replace('/^www\./', '', $host) ?: $host;

        return $host === $domain || str_ends_with($host, '.'.$domain);
    }
}
