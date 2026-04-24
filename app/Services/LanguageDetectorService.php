<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use LanguageDetection\Language;

class LanguageDetectorService
{
    private ?Language $detector = null;

    /** @var array<string, ?string> */
    private array $memo = [];

    public function detect(?string $text): ?string
    {
        $text = trim((string) $text);
        if ($text === '' || mb_strlen($text) < 2) {
            return null;
        }

        $key = mb_strtolower($text);
        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $lang = Cache::remember(
            'lang-detect:' . sha1($key),
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
        $result = $this->getDetector()->detect($text)->bestResults()->close();
        if ($result === []) {
            return null;
        }
        return (string) array_key_first($result);
    }

    private function getDetector(): Language
    {
        return $this->detector ??= new Language();
    }
}
