<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use LanguageDetection\Language;

class LanguageDetectorService
{
    /**
     * Languages the detector is allowed to pick from. patrickschur ships ~400
     * locale models; most are obscure (Oromo, Ganda, etc.) and almost never
     * show up legitimately in SEO queries, but they easily "win" short,
     * brand-heavy inputs like "free fire names" by noise. Restricting to the
     * top languages spoken on the web eliminates those false positives.
     *
     * @var list<string>
     */
    private const ALLOWED = [
        'en', 'es', 'fr', 'de', 'it', 'pt', 'nl', 'sv', 'no', 'da', 'fi',
        'pl', 'cs', 'ro', 'hu', 'el', 'tr', 'ru', 'uk', 'bg', 'hr', 'sr',
        'ar', 'he', 'fa', 'ur', 'hi', 'bn', 'ta', 'te', 'ml', 'mr', 'gu',
        'pa', 'ja', 'ko', 'zh-Hans', 'zh-Hant', 'th', 'vi', 'id', 'ms',
        'tl', 'sw',
    ];

    /** Minimum chars before detection is attempted. */
    private const MIN_CHARS = 10;

    /** Minimum top-language score and margin over #2 for the n-gram detector. */
    private const MIN_SCORE = 0.45;
    private const MIN_MARGIN = 0.03;

    /**
     * Function words that almost never appear in short non-English queries.
     * Their presence is a stronger signal than patrickschur's n-gram scores,
     * which routinely mis-rank short SEO queries (e.g. "how to cook pasta"
     * scoring Spanish above English).
     */
    private const ENGLISH_FUNCTION_WORDS = [
        'the', 'and', 'for', 'with', 'how', 'what', 'why', 'when', 'where',
        'is', 'are', 'to', 'of', 'in', 'on', 'vs', 'best', 'top', 'near',
    ];

    /** Cache key prefix — bump the version to invalidate prior detections. */
    private const CACHE_VERSION = 'v4';

    private ?Language $detector = null;

    /** @var array<string, ?string> */
    private array $memo = [];

    public function detect(?string $text): ?string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }

        $key = mb_strtolower($text);
        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $lang = Cache::remember(
            'lang-detect:' . self::CACHE_VERSION . ':' . sha1($key),
            now()->addDays(30),
            fn () => $this->run($key),
        );

        return $this->memo[$key] = $lang;
    }

    /**
     * @param  iterable<string>  $texts
     * @return array<string, ?string>  lower-cased text => ISO-639-1 code (or null)
     */
    public function detectMany(iterable $texts): array
    {
        $out = [];
        foreach ($texts as $t) {
            $k = mb_strtolower(trim((string) $t));
            if ($k === '' || isset($out[$k])) {
                continue;
            }
            $out[$k] = $this->detect($k);
        }
        return $out;
    }

    private function run(string $text): ?string
    {
        if (mb_strlen($text) < self::MIN_CHARS) {
            return null;
        }

        if ($this->looksLikeEnglish($text)) {
            return 'en';
        }

        $result = $this->getDetector()->detect($text)->close();
        if ($result === []) {
            return null;
        }

        $scores = array_values($result);
        $top = (float) $scores[0];
        $second = $scores[1] ?? 0.0;
        if ($top < self::MIN_SCORE || ($top - $second) < self::MIN_MARGIN) {
            return null;
        }

        $code = strtolower((string) array_key_first($result));
        return preg_replace('/[-_].*$/', '', $code) ?? $code;
    }

    private function looksLikeEnglish(string $text): bool
    {
        if (! preg_match('/^[\x00-\x7F]+$/', $text)) {
            return false;
        }
        $words = preg_split('/[^a-z]+/', $text) ?: [];
        foreach ($words as $w) {
            if (in_array($w, self::ENGLISH_FUNCTION_WORDS, true)) {
                return true;
            }
        }
        return false;
    }

    private function getDetector(): Language
    {
        return $this->detector ??= new Language(self::ALLOWED);
    }
}
