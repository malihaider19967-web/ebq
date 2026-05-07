<?php

namespace App\Services\Research\Niche;

use App\Models\Research\Keyword;
use App\Models\Research\Niche;

/**
 * Reads/writes vectors to the existing nullable BLOB columns on
 * `keywords.embedding` and `niches.embedding`, encoded as JSON.
 *
 * JSON over float32-pack: easier to debug, ~2x bigger but vectors are
 * already small for `mistral-embed` (1024 dims). If size becomes an
 * issue we swap to pack/unpack without changing call sites.
 */
class EmbeddingCache
{
    public function __construct(private readonly EmbeddingProvider $provider) {}

    /** @return list<float>|null */
    public function forKeyword(Keyword $keyword): ?array
    {
        $cached = $this->decode($keyword->embedding);
        if ($cached !== null) {
            return $cached;
        }

        $vec = $this->provider->embed([$keyword->normalized_query])[0] ?? [];
        if ($vec === []) {
            return null;
        }

        $keyword->forceFill(['embedding' => $this->encode($vec)])->save();

        return $vec;
    }

    /** @return list<float>|null */
    public function forNiche(Niche $niche): ?array
    {
        $cached = $this->decode($niche->embedding ?? null);
        if ($cached !== null) {
            return $cached;
        }

        $text = $niche->slug.' '.$niche->name;
        $vec = $this->provider->embed([$text])[0] ?? [];
        if ($vec === []) {
            return null;
        }

        $niche->forceFill(['embedding' => $this->encode($vec)])->save();

        return $vec;
    }

    /** @return list<float>|null */
    public function forText(string $text): ?array
    {
        if (trim($text) === '') {
            return null;
        }

        $vec = $this->provider->embed([$text])[0] ?? [];

        return $vec === [] ? null : $vec;
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    public static function cosine(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }
        $len = min(count($a), count($b));
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        if ($na === 0.0 || $nb === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }

    /** @return list<float>|null */
    private function decode(mixed $blob): ?array
    {
        if (! is_string($blob) || $blob === '') {
            return null;
        }
        $decoded = json_decode($blob, true);
        if (! is_array($decoded)) {
            return null;
        }

        return array_values(array_map('floatval', $decoded));
    }

    /** @param list<float> $vector */
    private function encode(array $vector): string
    {
        return json_encode(array_map('floatval', $vector), JSON_UNESCAPED_SLASHES) ?: '';
    }
}
