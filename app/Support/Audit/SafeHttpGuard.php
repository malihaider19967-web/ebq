<?php

namespace App\Support\Audit;

/**
 * SSRF guard for outbound HTTP requests made by the page-audit feature.
 *
 * A URL is considered safe only when:
 *  - its scheme is http or https,
 *  - its host is a registered hostname (not a bare IP, not a single-label host),
 *  - every IP its host resolves to is a public/global address (no loopback,
 *    private, link-local, multicast, or reserved range — IPv4 or IPv6).
 *
 * On check failure a short reason string is returned so callers can surface
 * a useful message to the user or log instead of failing silently.
 */
class SafeHttpGuard
{
    /**
     * Validate a URL for outbound fetch.
     *
     * @return array{ok: bool, reason?: string}
     */
    public function check(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['ok' => false, 'reason' => 'empty_url'];
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return ['ok' => false, 'reason' => 'malformed_url'];
        }

        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return ['ok' => false, 'reason' => 'unsupported_scheme'];
        }

        $host = strtolower($parts['host']);

        // Reject bare IPs in the URL (could be a private range written literally).
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return ['ok' => false, 'reason' => 'literal_ip_host'];
        }

        // Require at least a two-label host (blocks http://intranet/ and similar).
        if (substr_count($host, '.') < 1) {
            return ['ok' => false, 'reason' => 'single_label_host'];
        }

        $ips = $this->resolveHost($host);
        if ($ips === []) {
            return ['ok' => false, 'reason' => 'dns_resolution_failed'];
        }

        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                return ['ok' => false, 'reason' => 'private_network_address'];
            }
        }

        return ['ok' => true];
    }

    /**
     * @return list<string>
     */
    private function resolveHost(string $host): array
    {
        $ips = [];

        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            foreach ($v4 as $ip) {
                $ips[] = $ip;
            }
        }

        $records = @dns_get_record($host, DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $r) {
                if (isset($r['ipv6']) && is_string($r['ipv6'])) {
                    $ips[] = $r['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * An IP is considered public when it is NOT in any reserved / private /
     * loopback / link-local / multicast range. We use PHP's FILTER_FLAG_*
     * directly and add explicit checks for the IPv4 metadata address and
     * IPv4-mapped IPv6 ranges since filter_var's FILTER_FLAG_NO_RES_RANGE
     * coverage is not exhaustive for IPv6.
     */
    private function isPublicIp(string $ip): bool
    {
        if (filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false) {
            return false;
        }

        // Explicit extra blocks filter_var misses on some PHP builds:
        //  - IPv4 link-local metadata (169.254.0.0/16)
        //  - IPv4-mapped IPv6 ::ffff:0:0/96 (extract inner IPv4 + re-check)
        if (str_starts_with($ip, '169.254.')) {
            return false;
        }

        if (str_contains($ip, ':')) {
            // Normalize IPv4-mapped IPv6.
            if (preg_match('/^::ffff:([\d.]+)$/i', $ip, $m) && isset($m[1])) {
                return $this->isPublicIp($m[1]);
            }
            // Unique-local fc00::/7 and link-local fe80::/10 are covered by
            // FILTER_FLAG_NO_PRIV_RANGE / NO_RES_RANGE in recent PHP, but be
            // explicit just in case the filter_var build is older.
            $lower = strtolower($ip);
            if (str_starts_with($lower, 'fc') || str_starts_with($lower, 'fd') || str_starts_with($lower, 'fe8') || str_starts_with($lower, 'fe9') || str_starts_with($lower, 'fea') || str_starts_with($lower, 'feb')) {
                return false;
            }
            if ($lower === '::1' || $lower === '::') {
                return false;
            }
        }

        return true;
    }
}
