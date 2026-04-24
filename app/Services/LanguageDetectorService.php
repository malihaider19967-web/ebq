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
     * Per-language word signals. On short queries, patrickschur's n-gram
     * scores are routinely within a percentage point of each other (e.g.
     * `generador de nombres de free fire` scores es:0.494 da:0.493), so
     * a word-count vote beats the raw detector on these inputs. Each list
     * is intentionally a mix of function and high-frequency content words
     * distinctive enough to the language to be a reliable signal in a
     * short SEO query.
     *
     * @var array<string, list<string>>
     */
    private const FASTPATH_WORDS = [
        'en' => ['the', 'and', 'for', 'with', 'how', 'what', 'why', 'when', 'where', 'is', 'are', 'to', 'of', 'on', 'vs', 'best', 'top', 'near', 'near me'],
        'es' => ['que', 'con', 'para', 'como', 'una', 'por', 'del', 'sin', 'pero', 'mejor', 'mejores', 'nombres', 'generador', 'gratis', 'comprar', 'donde', 'cuando', 'los', 'las', 'el', 'ella', 'esto', 'mas', 'sobre'],
        'pt' => ['nao', 'voce', 'muito', 'melhor', 'pelo', 'pela', 'tambem', 'estao', 'sao', 'ate', 'obrigado', 'voces'],
        'fr' => ['les', 'des', 'une', 'est', 'avec', 'pour', 'sans', 'comment', 'pourquoi', 'meilleur', 'chez', 'ceci', 'cela', 'mais', 'aussi'],
        'de' => ['der', 'die', 'das', 'den', 'und', 'oder', 'ist', 'sind', 'wie', 'was', 'warum', 'mit', 'ohne', 'fur', 'bei', 'beste', 'ein', 'eine', 'auch', 'nicht', 'kaufen'],
        'it' => ['gli', 'della', 'delle', 'sono', 'come', 'perche', 'migliore', 'anche', 'questo', 'quello', 'cosa', 'molto'],
        'nl' => ['het', 'een', 'voor', 'maar', 'niet', 'ook', 'hoe', 'wat', 'waar', 'beste', 'kopen'],
    ];

    /** Cache key prefix — bump the version to invalidate prior detections. */
    private const CACHE_VERSION = 'v5';

    private ?Language $detector = null;

    /** @var array<string, ?string> */
    private array $memo = [];

    public function detect(?string $text): ?string
    {
        if (! config('services.language_detection.enabled', true)) {
            return null;
        }

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

        if ($fast = $this->fastPathLanguage($text)) {
            return $fast;
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

    /**
     * Count word-signal hits per language and pick the clear winner.
     * Returns null on ties or zero hits — the n-gram detector handles the rest.
     * Accent-insensitive so queries like "perché" match the Italian "perche" entry.
     */
    private function fastPathLanguage(string $text): ?string
    {
        $folded = $this->foldAccents(mb_strtolower($text));
        $words = preg_split('/[^a-z]+/', $folded) ?: [];
        $words = array_values(array_filter($words, fn ($w) => $w !== ''));

        $counts = [];
        foreach (self::FASTPATH_WORDS as $lang => $list) {
            $n = 0;
            foreach ($words as $w) {
                if (in_array($w, $list, true)) {
                    $n++;
                }
            }
            if ($n > 0) {
                $counts[$lang] = $n;
            }
        }
        if ($counts === []) {
            return null;
        }

        arsort($counts);
        $codes = array_keys($counts);
        $top = $counts[$codes[0]];
        $second = $counts[$codes[1] ?? ''] ?? 0;
        return $top > $second ? $codes[0] : null;
    }

    private function foldAccents(string $text): string
    {
        $map = [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ñ' => 'n', 'ç' => 'c', 'ß' => 'ss',
        ];
        return strtr($text, $map);
    }

    private function getDetector(): Language
    {
        return $this->detector ??= new Language(self::ALLOWED);
    }
}
