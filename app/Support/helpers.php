<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

if (! function_exists('display_timezone')) {
    /**
     * Effective IANA timezone for the given or authenticated user (falls back to app default).
     */
    function display_timezone(?User $user = null): string
    {
        $user ??= auth()->user();
        if ($user instanceof User) {
            return $user->timezoneForDisplay();
        }

        return (string) config('app.timezone');
    }
}

if (! function_exists('format_user_datetime')) {
    /**
     * Format an instant (stored in UTC in the database) in the user's timezone.
     *
     * @param  DateTimeInterface|CarbonInterface|string|null  $value
     */
    function format_user_datetime($value, string $format = 'M j, Y g:i A', ?User $user = null): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Carbon::parse($value)->timezone(display_timezone($user))->format($format);
    }
}

if (! function_exists('format_user_date')) {
    /**
     * Format a calendar date (Y-m-d) without shifting the calendar day when changing timezone.
     */
    function format_user_date(?string $ymd, string $format = 'M j, Y', ?User $user = null): string
    {
        if ($ymd === null || $ymd === '') {
            return '';
        }

        return Carbon::parse($ymd, display_timezone($user))->format($format);
    }
}

if (! function_exists('format_user_now')) {
    function format_user_now(string $format = 'M j, Y g:i A', ?User $user = null): string
    {
        return Carbon::now(display_timezone($user))->format($format);
    }
}

if (! function_exists('plugin_download_url')) {
    /**
     * URL to the packaged WordPress plugin ZIP. Routed through Laravel so the
     * controller can emit no-cache headers and a filemtime-stamped filename —
     * guarantees every repackage is immediately the version users download.
     */
    function plugin_download_url(): string
    {
        $absolute = public_path('downloads/ebq-seo.zip');
        $version = is_file($absolute) ? (string) filemtime($absolute) : '0';

        return route('wordpress.plugin.download').'?v='.$version;
    }
}
