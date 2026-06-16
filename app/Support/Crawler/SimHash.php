<?php

namespace App\Support\Crawler;

/**
 * 64-bit SimHash for noise-tolerant near-duplicate detection of page content.
 *
 * Exact content hashing (sha1 of body text) treats a page as "changed" whenever
 * a rotating ad, timestamp, view counter, or CSRF token differs — which would
 * peg the recrawl scheduler at its floor and never let stable pages back off.
 * SimHash instead produces a fingerprint where small textual differences flip
 * only a few bits, so a change is "significant" only when the Hamming distance
 * between two fingerprints exceeds a threshold.
 *
 * Fingerprints are returned as 16 hex chars (the 64-bit value), which sidesteps
 * PHP's signed-64-bit int limits in both storage and distance computation.
 */
final class SimHash
{
    private const BITS = 64;

    /** SimHash of $text as 16 lowercase hex chars (all-zero for empty text). */
    public static function fingerprint(string $text): string
    {
        $tokens = self::tokenize($text);
        if ($tokens === []) {
            return str_repeat('0', 16);
        }

        // Per-bit signed accumulator, weighted by token frequency.
        $sums = array_fill(0, self::BITS, 0);
        foreach (array_count_values($tokens) as $token => $weight) {
            $raw = substr(md5((string) $token, true), 0, 8); // 8 bytes = 64 bits
            for ($i = 0; $i < self::BITS; $i++) {
                $bit = (ord($raw[$i >> 3]) >> ($i & 7)) & 1;
                $sums[$i] += $bit ? $weight : -$weight;
            }
        }

        // Collapse to a bitstring, pack to 8 bytes, hex-encode.
        $bytes = array_fill(0, 8, 0);
        for ($i = 0; $i < self::BITS; $i++) {
            if ($sums[$i] > 0) {
                $bytes[$i >> 3] |= (1 << ($i & 7));
            }
        }

        return bin2hex(implode('', array_map('chr', $bytes)));
    }

    /** Hamming distance (0–64) between two 16-hex fingerprints. */
    public static function distance(string $a, string $b): int
    {
        $ba = @hex2bin($a);
        $bb = @hex2bin($b);
        if ($ba === false || $bb === false || strlen($ba) !== 8 || strlen($bb) !== 8) {
            return self::BITS; // malformed → treat as maximally different
        }

        $dist = 0;
        for ($i = 0; $i < 8; $i++) {
            $x = ord($ba[$i]) ^ ord($bb[$i]);
            // Kernighan popcount of a byte.
            while ($x) {
                $x &= $x - 1;
                $dist++;
            }
        }

        return $dist;
    }

    /** @return list<string> lowercased word tokens (length ≥ 2). */
    private static function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        // Drop pure-number/date-ish tokens up front (counters, timestamps, prices
        // standalone) so the most common volatile noise never enters the hash.
        $text = preg_replace('/\b[\d.,:\/\-]{1,}\b/u', ' ', $text) ?? $text;
        preg_match_all('/\p{L}[\p{L}\p{N}]+/u', $text, $m);

        return $m[0] ?? [];
    }
}
