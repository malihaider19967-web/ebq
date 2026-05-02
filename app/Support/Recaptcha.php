<?php

namespace App\Support;

class Recaptcha
{
    public static function isEnabled(): bool
    {
        $site = trim((string) config('services.recaptcha.site_key', ''));
        $secret = trim((string) config('services.recaptcha.secret_key', ''));

        return $site !== '' && $secret !== '';
    }
}
