<?php

namespace App\Support\Crawler;

use App\Models\WebsitePage;

/**
 * Language-agnostic significant-term extraction. No stopword lists: corpus
 * document-frequency identifies boilerplate/stopwords in ANY language (they're
 * the highest-DF terms), so they drop out automatically. NFKC normalization
 * collapses styled-Unicode variants (𝐂𝐇𝐄𝐓𝐀𝐍 / ᶜʰᵉᵗᵃⁿ → chetan); a junk filter
 * removes pure numbers / id-codes.
 *
 * Two phases:
 *   candidates()  — per page, during crawl: weighted-TF terms + bigram phrases.
 *   buildDf()     — per site, in analysis: document-frequency over a page sample.
 *   significant() — combine the two into TF-IDF top terms (used for matching).
 */
class TermExtractor
{
    private const MIN_LEN = 3;
    private const TOP_CANDIDATES = 30;
    private const TOP_PHRASES = 8;
    private const TITLE_BOOST = 3;
    private const SLUG_BOOST = 2;
    private const BODY_CHARS = 6000;

    /** NFKC-normalize + lowercase so styled-Unicode variants collapse to one form. */
    public static function normalize(string $t): string
    {
        if (class_exists(\Normalizer::class)) {
            $t = \Normalizer::normalize($t, \Normalizer::FORM_KC) ?: $t;
        }

        return mb_strtolower($t, 'UTF-8');
    }

    /** Universal web/markup tokens (structural, not linguistic — language-blind). */
    private const WEB_JUNK = ['http', 'https', 'www', 'com', 'org', 'net', 'html', 'php', 'href', 'utm'];

    /** True for tokens with no topical value: pure numbers, id-codes, web junk. */
    public static function isJunk(string $t): bool
    {
        if (preg_match('/\p{L}/u', $t) !== 1) {
            return true; // no letters at all (numbers / symbols)
        }
        if (in_array($t, self::WEB_JUNK, true)) {
            return true; // url/markup scheme tokens
        }
        $digits = preg_match_all('/\p{Nd}/u', $t);

        return $digits >= mb_strlen($t) * 0.4; // digit-heavy → id/code (e.g. phone numbers)
    }

    /** Unicode-aware tokenizer → normalized tokens ≥ MIN_LEN, junk removed. */
    public static function tokenize(string $text): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($parts as $w) {
            $w = self::normalize($w);
            if (mb_strlen($w) >= self::MIN_LEN && ! self::isJunk($w)) {
                $out[] = $w;
            }
        }

        return $out;
    }

    /**
     * Per-page candidate terms (weighted TF, title/slug-boosted) + bigram phrases.
     * Returned shape — compact for JSON storage in website_pages.content_terms:
     *   ['t' => [term => weightedTf, ...], 'p' => [bigram => tf, ...]]
     */
    public function candidates(string $title, string $body, string $url): array
    {
        $bodyTok = self::tokenize(mb_substr($body, 0, self::BODY_CHARS));
        $titleTok = self::tokenize($title);
        $slugTok = self::tokenize(str_replace(['/', '-', '_'], ' ', (string) parse_url($url, PHP_URL_PATH)));

        $tf = [];
        foreach ($bodyTok as $t) {
            $tf[$t] = ($tf[$t] ?? 0) + 1;
        }
        foreach ($titleTok as $t) {
            $tf[$t] = ($tf[$t] ?? 0) + self::TITLE_BOOST;
        }
        foreach ($slugTok as $t) {
            $tf[$t] = ($tf[$t] ?? 0) + self::SLUG_BOOST;
        }
        arsort($tf);
        $top = array_slice($tf, 0, self::TOP_CANDIDATES, true);

        // Always keep title + slug (curated) terms through the cap — they carry the
        // page's topic and are often LOW body-frequency (e.g. "tuna" in a slug),
        // so a raw-TF cut would wrongly drop them before IDF can rank them up.
        foreach (array_unique(array_merge($titleTok, $slugTok)) as $t) {
            if (! isset($top[$t]) && isset($tf[$t])) {
                $top[$t] = $tf[$t];
            }
        }
        $tf = $top;

        // Bigrams from the title + body token stream (collocations).
        $stream = array_merge($titleTok, $bodyTok);
        $bg = [];
        $n = count($stream);
        for ($i = 0; $i < $n - 1; $i++) {
            $p = $stream[$i].' '.$stream[$i + 1];
            $bg[$p] = ($bg[$p] ?? 0) + 1;
        }
        arsort($bg);
        $bg = array_slice($bg, 0, self::TOP_PHRASES, true);

        return ['t' => $tf, 'p' => $bg];
    }

    /**
     * Document-frequency map over a sample of the site's pages (term => #docs).
     * A sample is enough: boilerplate/stopwords are on (nearly) every page, so any
     * sample catches them; this bounds cost on 100k-page sites.
     *
     * @return array{0: array<string,int>, 1: int}  [df, docCount]
     */
    public function buildDf(string $crawlSiteId, int $sample = 3000): array
    {
        $df = [];
        $docs = 0;
        // DF must be computed over the FULL token set per page (not the stored
        // top-N candidates) or high-frequency stopwords/boilerplate are
        // under-counted and slip past the df-cut. body_text is present at analysis
        // time (pruning runs afterwards); even a pruned excerpt still contains the
        // common words DF needs to identify.
        $rows = WebsitePage::where('crawl_site_id', $crawlSiteId)
            ->whereNotNull('body_text')
            ->limit($sample)
            ->get(['title', 'body_text']);

        foreach ($rows as $r) {
            $toks = array_unique(array_merge(
                self::tokenize((string) $r->title),
                self::tokenize(mb_substr((string) $r->body_text, 0, self::BODY_CHARS)),
            ));
            if ($toks === []) {
                continue;
            }
            $docs++;
            foreach ($toks as $t) {
                $df[$t] = ($df[$t] ?? 0) + 1;
            }
        }

        return [$df, $docs];
    }

    /**
     * Final significant terms for one page: TF-IDF over its candidates, dropping
     * site-common terms (df > dfCut share) and one-off noise (df < 2).
     *
     * @param  array  $candidates  decoded content_terms (['t'=>..., 'p'=>...])
     * @return array<string,float>  term => score, highest first, capped at $topN
     */
    public function significant(array $candidates, array $df, int $docs, int $topN = 15, float $dfCut = 0.40): array
    {
        if ($docs < 1 || empty($candidates['t'])) {
            return [];
        }
        $cut = $docs * $dfCut;
        $scores = [];
        foreach ($candidates['t'] as $term => $wtf) {
            $dfi = $df[$term] ?? 1;
            if ($dfi > $cut) {
                continue; // boilerplate / stopword (common across the site)
            }
            // Drop one-off noise (usernames/typos) — BUT keep a unique term when it's
            // curated (title/slug → weight ≥ 2), e.g. a single-article topic word.
            if ($dfi < 2 && $wtf < 2) {
                continue;
            }
            $scores[$term] = (1 + log((float) $wtf)) * log($docs / max($dfi, 1));
        }
        arsort($scores);

        return array_slice($scores, 0, $topN, true);
    }
}
