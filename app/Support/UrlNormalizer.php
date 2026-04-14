<?php

namespace App\Support;

class UrlNormalizer
{
    public static function normalize(string $url): string
    {
        $trimmed = trim($url);
        $normalized = preg_replace('/#.*$/', '', $trimmed) ?? $trimmed;
        $normalized = rtrim($normalized, '/');

        return strtolower($normalized);
    }
}
