<?php

namespace App\Support\Audit;

class RecommendationEngine
{
    public const SEV_CRITICAL = 'critical';
    public const SEV_WARNING = 'warning';
    public const SEV_INFO = 'info';
    public const SEV_GOOD = 'good';

    /**
     * Produce a prioritized list of recommendations for an audit result.
     *
     * Each entry: ['id', 'section', 'severity', 'title', 'why', 'fix']
     */
    public function analyze(array $result): array
    {
        $recs = [];

        $recs = array_merge($recs, $this->metadata($result['metadata'] ?? []));
        $recs = array_merge($recs, $this->structure($result['content'] ?? []));
        $recs = array_merge($recs, $this->images($result['images'] ?? [], $result['content']['word_count'] ?? 0));
        $recs = array_merge($recs, $this->technical($result['technical'] ?? []));
        $recs = array_merge($recs, $this->advanced($result['advanced'] ?? []));
        $recs = array_merge($recs, $this->links($result['links'] ?? []));
        $recs = array_merge($recs, $this->keywords($result['keywords'] ?? []));

        $order = [self::SEV_CRITICAL => 0, self::SEV_WARNING => 1, self::SEV_INFO => 2, self::SEV_GOOD => 3];
        usort($recs, fn ($a, $b) => ($order[$a['severity']] ?? 9) <=> ($order[$b['severity']] ?? 9));

        return $recs;
    }

    private function metadata(array $meta): array
    {
        $out = [];
        $title = (string) ($meta['title'] ?? '');
        $titleLen = (int) ($meta['title_length'] ?? 0);
        $desc = (string) ($meta['meta_description'] ?? '');
        $descLen = (int) ($meta['meta_description_length'] ?? 0);

        if ($title === '') {
            $out[] = $this->rec('meta.title.missing', 'Metadata', self::SEV_CRITICAL,
                'Missing <title> tag',
                'The title tag is the single strongest on-page ranking signal and is what users click on in the search results. Pages with no title rank poorly and look broken in SERPs.',
                'Add a unique, descriptive <title> tag (50–60 characters) that includes your primary keyword near the front.');
        } elseif ($titleLen < 30) {
            $out[] = $this->rec('meta.title.short', 'Metadata', self::SEV_WARNING,
                "Title is short ({$titleLen} chars)",
                'Titles under 30 characters leave valuable SERP real estate unused and usually miss secondary keywords and a value proposition.',
                'Expand the title to 50–60 characters. Lead with the primary keyword, then add a differentiator (year, brand, benefit).');
        } elseif ($titleLen > 60) {
            $out[] = $this->rec('meta.title.long', 'Metadata', self::SEV_WARNING,
                "Title is too long ({$titleLen} chars)",
                'Google truncates titles beyond ~60 characters (580px). The tail gets cut with an ellipsis, which hides important words from searchers.',
                'Trim the title to ≤60 characters. Move the brand name to the end and keep the primary keyword in the first 50 characters.');
        } else {
            $out[] = $this->rec('meta.title.ok', 'Metadata', self::SEV_GOOD,
                "Title length is optimal ({$titleLen} chars)",
                'Titles in the 50–60 character range display fully in Google SERPs across desktop and mobile.',
                'No change needed. Review wording quarterly to keep CTR high.');
        }

        if ($desc === '') {
            $out[] = $this->rec('meta.desc.missing', 'Metadata', self::SEV_WARNING,
                'Missing meta description',
                'When no description is set, Google auto-generates one from body text — often pulling irrelevant sentences. A hand-written description increases CTR by 5–15%.',
                'Write a 120–160 character description that summarizes the page, includes the primary keyword, and ends with a call to action.');
        } elseif ($descLen < 120) {
            $out[] = $this->rec('meta.desc.short', 'Metadata', self::SEV_INFO,
                "Meta description is short ({$descLen} chars)",
                'Descriptions under 120 characters leave room that competitors will fill, reducing your share of the SERP.',
                'Expand to 120–160 characters. Add a secondary keyword and a clear benefit.');
        } elseif ($descLen > 160) {
            $out[] = $this->rec('meta.desc.long', 'Metadata', self::SEV_WARNING,
                "Meta description is too long ({$descLen} chars)",
                'Google truncates descriptions beyond ~160 characters (920px). The cut-off often removes your CTA or key selling point.',
                'Trim to ≤160 characters. Put the most important information in the first 120 characters.');
        } else {
            $out[] = $this->rec('meta.desc.ok', 'Metadata', self::SEV_GOOD,
                "Meta description length is optimal ({$descLen} chars)",
                'Descriptions in the 120–160 character window render fully across devices and maximize SERP engagement.',
                'No change needed.');
        }

        if (($meta['og_tag_count'] ?? 0) === 0) {
            $out[] = $this->rec('meta.og.missing', 'Metadata', self::SEV_WARNING,
                'No OpenGraph tags',
                'Without og:title, og:description, and og:image, links shared on Facebook, LinkedIn, Slack, and iMessage fall back to ugly defaults — hurting click-through from social.',
                'Add og:title, og:description, og:image (1200×630), og:url, og:type to the <head>.');
        }
        if (($meta['twitter_tag_count'] ?? 0) === 0) {
            $out[] = $this->rec('meta.twitter.missing', 'Metadata', self::SEV_INFO,
                'No Twitter card tags',
                'twitter:card controls how X/Twitter and some messaging apps render shared links. Missing tags fall back to plain URL previews.',
                'Add twitter:card (summary_large_image), twitter:title, twitter:description, twitter:image.');
        }

        $canonical = (string) ($meta['canonical'] ?? '');
        if ($canonical === '') {
            $out[] = $this->rec('meta.canonical.missing', 'Metadata', self::SEV_WARNING,
                'No canonical URL',
                'Without <link rel="canonical">, duplicate variants (trailing slash, UTM-tagged, paginated) can split ranking signals across URLs.',
                'Add <link rel="canonical" href="{page_url}"> in the <head>.');
        } elseif (! ($meta['canonical_matches'] ?? false)) {
            $out[] = $this->rec('meta.canonical.mismatch', 'Metadata', self::SEV_WARNING,
                'Canonical URL does not match page URL',
                'A mismatched canonical tells Google to index a different URL. If unintended, this page may be dropped from search results entirely.',
                'Verify the canonical points to the preferred version of this URL. If this page is the canonical, update the tag.');
        }

        return $out;
    }

    private function structure(array $content): array
    {
        $out = [];
        $h1 = (int) ($content['h1_count'] ?? 0);
        $wc = (int) ($content['word_count'] ?? 0);

        if ($h1 === 0) {
            $out[] = $this->rec('struct.h1.missing', 'Structure', self::SEV_CRITICAL,
                'No <h1> found',
                'The H1 is the on-page headline Google and screen readers use to understand the topic. Pages without an H1 rarely rank for competitive terms.',
                'Add exactly one <h1> near the top containing the primary keyword, phrased as it would appear to a human reader.');
        } elseif ($h1 > 1) {
            $out[] = $this->rec('struct.h1.multiple', 'Structure', self::SEV_WARNING,
                "Multiple <h1> tags ({$h1})",
                'More than one H1 dilutes the topical focus and creates accessibility issues. Modern SEO best practice is one H1 per page.',
                'Demote extra H1s to H2 or H3. Keep the one most specific to the page topic as the single H1.');
        }

        if (! ($content['heading_order_ok'] ?? true)) {
            $out[] = $this->rec('struct.headings.order', 'Structure', self::SEV_WARNING,
                'Heading levels are skipped',
                'Jumping from H2 to H4 (skipping H3) confuses assistive tech and search crawlers, and suggests sloppy content hierarchy.',
                'Re-order your subsections so headings descend in order: H1 → H2 → H3 → H4. Never skip a level.');
        }

        if ($wc === 0) {
            $out[] = $this->rec('struct.content.empty', 'Structure', self::SEV_CRITICAL,
                'No body content detected',
                'An empty page cannot rank. This may indicate a client-side-rendered page that did not hydrate for Googlebot, or a truly blank page.',
                'Verify the page server-side renders meaningful HTML. If it is an SPA, implement SSR or dynamic rendering for search crawlers.');
        } elseif ($wc < 300) {
            $out[] = $this->rec('struct.content.thin', 'Structure', self::SEV_WARNING,
                "Thin content ({$wc} words)",
                'Pages under 300 words are routinely classified as "thin content" by Google. They typically cannot rank for anything competitive.',
                'Expand to at least 600–1,000 words covering the topic in depth. Use sub-headings, examples, and comparisons.');
        } elseif ($wc > 2500) {
            $out[] = $this->rec('struct.content.long', 'Structure', self::SEV_INFO,
                "Long-form content ({$wc} words)",
                'Long pages can rank well but risk dropping engagement. Very long pages often work better split into a series or topic cluster.',
                'Consider splitting into multiple linked pages (a pillar + clusters), or add a table of contents for navigation.');
        }

        $kd = $content['keyword_density'] ?? [];
        if (! empty($kd)) {
            $top = $kd[0];
            $density = (float) ($top['density'] ?? 0);
            if ($density >= 3.0) {
                $out[] = $this->rec('struct.density.stuffing', 'Structure', self::SEV_WARNING,
                    'Keyword stuffing risk: "' . $top['term'] . '" at ' . $density . '%',
                    'Single-term density above 3% is a strong keyword-stuffing signal. Google has penalized pages for this pattern since the Panda update.',
                    'Reduce repetitions of "' . $top['term'] . '". Introduce 3–4 synonyms and related LSI phrases to diversify the lexical footprint.');
            } elseif ($density >= 1.5) {
                $out[] = $this->rec('struct.density.high', 'Structure', self::SEV_INFO,
                    'Top keyword "' . $top['term'] . '" at ' . $density . '%',
                    'A density of 1.5–3% is within the acceptable range but close to the stuffing threshold. Pair the term with natural synonyms.',
                    'Monitor as you add content. Introduce 2–3 synonyms to stay safely under 2%.');
            }
        }

        return $out;
    }

    private function images(array $images, int $wordCount): array
    {
        $out = [];
        $total = (int) ($images['total'] ?? 0);
        $missing = (int) ($images['missing_alt_count'] ?? 0);
        $modern = (int) ($images['modern_format_count'] ?? 0);

        if ($wordCount >= 1000 && $total === 0) {
            $out[] = $this->rec('img.none.long', 'Engagement', self::SEV_WARNING,
                'Long article with no images',
                'Pages over 1,000 words without any images show significantly lower dwell time and scroll depth. Google uses engagement metrics as ranking signals.',
                'Add 3–5 relevant images: screenshots, diagrams, or data visualizations. Use descriptive alt text.');
        }

        if ($missing > 0) {
            $out[] = $this->rec('img.alt.missing', 'Engagement', self::SEV_WARNING,
                "{$missing} image(s) missing alt text",
                'Missing alt text blocks screen-reader users from understanding visuals (ADA/WCAG issue) and prevents those images from appearing in Google Image Search.',
                'Add concise, descriptive alt attributes to every content image. Decorative images can use alt="" but must have the attribute.');
        }

        if ($total > 0 && $modern === 0) {
            $out[] = $this->rec('img.formats.legacy', 'Engagement', self::SEV_INFO,
                'No modern image formats (webp/avif) detected',
                'WebP is 25–35% smaller than JPEG at equivalent quality; AVIF is 50% smaller. Page weight directly affects LCP, a Core Web Vital.',
                'Convert key images to WebP (or AVIF with WebP fallback). Most CDNs can do this automatically.');
        }

        return $out;
    }

    private function technical(array $tech): array
    {
        $out = [];
        $status = (int) ($tech['http_status'] ?? 0);
        $ttfb = (int) ($tech['ttfb_ms'] ?? 0);
        $comp = (string) ($tech['compression'] ?? '');
        $https = (bool) ($tech['is_https'] ?? false);

        if (! $https) {
            $out[] = $this->rec('tech.https.off', 'Technical', self::SEV_CRITICAL,
                'Page is not served over HTTPS',
                'Browsers flag HTTP pages as "Not Secure", killing trust and conversion. Google confirmed HTTPS as a ranking signal in 2014 and now requires it for many features (HTTP/2, Core Web Vitals).',
                'Install a TLS certificate (Let\'s Encrypt is free) and 301-redirect every HTTP URL to its HTTPS equivalent.');
        }

        if ($status >= 500) {
            $out[] = $this->rec('tech.status.5xx', 'Technical', self::SEV_CRITICAL,
                "Server error ({$status})",
                'A 5xx response means the server failed. Repeated 5xx responses cause Googlebot to de-index the page.',
                'Investigate server logs, application errors, and infrastructure health immediately.');
        } elseif ($status >= 400) {
            $out[] = $this->rec('tech.status.4xx', 'Technical', self::SEV_CRITICAL,
                "Client error ({$status})",
                'A 4xx (usually 404) means the page cannot be reached by crawlers or users. It will not rank and will eventually be removed from the index.',
                'Either restore the page, 301-redirect it to the most relevant replacement, or remove all internal links to it.');
        }

        if ($ttfb >= 1000) {
            $out[] = $this->rec('tech.ttfb.very_slow', 'Technical', self::SEV_CRITICAL,
                "Very slow server response ({$ttfb} ms)",
                'TTFB above 1,000 ms makes the page feel broken and directly harms Largest Contentful Paint — a confirmed Core Web Vital.',
                'Profile server-side time: database queries, uncached views, blocking I/O. Enable application-level caching (Redis/Memcached) and a CDN.');
        } elseif ($ttfb >= 500) {
            $out[] = $this->rec('tech.ttfb.slow', 'Technical', self::SEV_WARNING,
                "Slow server response ({$ttfb} ms)",
                'TTFB should stay under 500 ms. Beyond that, each extra 100 ms correlates with measurable drops in conversion rate.',
                'Add a CDN in front of the origin, enable HTTP response caching, and audit the slowest SQL queries on this route.');
        }

        if ($comp === '' || $comp === 'none') {
            $out[] = $this->rec('tech.compression.off', 'Technical', self::SEV_WARNING,
                'Response is not compressed',
                'Uncompressed HTML is typically 3–5× larger on the wire, inflating Time to First Byte and data usage on mobile.',
                'Enable Brotli (preferred) or Gzip in your web server / reverse proxy. Most platforms offer this with a single config line.');
        } elseif ($comp === 'gzip') {
            $out[] = $this->rec('tech.compression.gzip', 'Technical', self::SEV_INFO,
                'Using Gzip compression',
                'Gzip is solid but Brotli typically compresses HTML 15–20% smaller at the same CPU cost.',
                'If your web server supports it, enable Brotli alongside Gzip and let the client negotiate.');
        }

        return $out;
    }

    private function advanced(array $adv): array
    {
        $out = [];

        if ((int) ($adv['schema_blocks'] ?? 0) === 0) {
            $out[] = $this->rec('adv.schema.none', 'Advanced', self::SEV_WARNING,
                'No JSON-LD structured data',
                'Schema markup unlocks rich results (stars, FAQs, breadcrumbs, sitelinks) that can dramatically increase SERP click-through rate.',
                'Add at least an Organization + WebPage + BreadcrumbList JSON-LD block. Use Article or Product schema where applicable.');
        }

        $flesch = data_get($adv, 'readability.flesch');
        if (is_numeric($flesch)) {
            $f = (float) $flesch;
            if ($f < 30) {
                $out[] = $this->rec('adv.read.hard', 'Advanced', self::SEV_WARNING,
                    "Readability is very difficult (Flesch {$f})",
                    'Below 30 requires a college-graduate reading level. Most commercial pages lose the majority of readers here.',
                    'Break long sentences (aim for ≤20 words), replace jargon with plain language, and use shorter paragraphs.');
            } elseif ($f < 60) {
                $out[] = $this->rec('adv.read.medium', 'Advanced', self::SEV_INFO,
                    "Readability is fairly difficult (Flesch {$f})",
                    'Scores of 30–60 are acceptable for technical or B2B content but reduce reach for consumer audiences.',
                    'If your audience is broad, aim for 60–70. Shorten sentences and prefer one- or two-syllable words where possible.');
            } else {
                $out[] = $this->rec('adv.read.good', 'Advanced', self::SEV_GOOD,
                    "Readability is in the sweet spot (Flesch {$f})",
                    'Scores of 60–80 read at a 7th–9th grade level — the highest-engagement range for most web audiences.',
                    'No change needed. Maintain this style as you add content.');
            }
        }

        if (! ($adv['has_favicon'] ?? false)) {
            $out[] = $this->rec('adv.favicon.missing', 'Advanced', self::SEV_INFO,
                'No favicon',
                'Favicons appear in browser tabs, bookmarks, and mobile SERPs. Their absence looks unfinished and reduces brand recall.',
                'Add a 32×32 PNG and a 180×180 apple-touch-icon; link both in the <head>.');
        }

        return $out;
    }

    private function links(array $links): array
    {
        $out = [];
        $broken = $links['broken'] ?? [];
        if (count($broken) > 0) {
            $out[] = $this->rec('links.broken', 'Technical', self::SEV_WARNING,
                count($broken) . ' broken outbound link(s)',
                'Broken links leak link equity, damage user trust, and are a known low-quality signal to Google.',
                'Either update each broken URL to a working equivalent or remove the link. Re-audit after changes to confirm.');
        }

        return $out;
    }

    private function keywords(array $kw): array
    {
        $out = [];
        if (empty($kw) || ! ($kw['available'] ?? false)) {
            return $out;
        }

        // 1. Power Placement
        $pp = $kw['power_placement'] ?? [];
        $primary = $pp['keyword'] ?? '';
        if ($primary !== '') {
            if (! ($pp['in_title'] ?? false)) {
                $out[] = $this->rec('kw.placement.title', 'Keywords', self::SEV_CRITICAL,
                    "Primary keyword missing from <title>: \"{$primary}\"",
                    'The title tag is the single strongest on-page ranking signal. A primary keyword that Google is already sending you impressions for, but which is absent from the title, is leaving ranking power on the table.',
                    "Rewrite the <title> to include \"{$primary}\" near the front, ideally in the first 50 characters.");
            }
            if (! ($pp['in_h1'] ?? false)) {
                $out[] = $this->rec('kw.placement.h1', 'Keywords', self::SEV_WARNING,
                    "Primary keyword missing from <h1>: \"{$primary}\"",
                    'The H1 tells Google (and readers) the page topic. Missing the query you rank for weakens topical relevance and makes CTR from SERPs harder to lift.',
                    "Use \"{$primary}\" (or a natural variant) in the page's H1 — this is a high-priority relevance signal.");
            }
            if (! ($pp['in_meta_description'] ?? false)) {
                $out[] = $this->rec('kw.placement.desc', 'Keywords', self::SEV_WARNING,
                    "Primary keyword missing from meta description: \"{$primary}\"",
                    "While not a direct ranking factor, Google bolds matched query terms in the SERP snippet. Missing the keyword means your result looks less relevant next to competitors'.",
                    "Include \"{$primary}\" naturally in the meta description, ideally in the first 120 characters.");
            }
        }

        // 2. Coverage
        $cov = $kw['coverage'] ?? [];
        $score = (float) ($cov['score'] ?? 0);
        $verdict = $cov['verdict'] ?? null;
        if ($verdict === 'expansion_needed') {
            $missingTerms = array_slice(array_map(fn ($m) => $m['query'], $cov['missing'] ?? []), 0, 6);
            $list = $missingTerms ? ' (e.g. ' . implode(', ', array_map(fn ($t) => '"' . $t . '"', $missingTerms)) . ')' : '';
            $out[] = $this->rec('kw.coverage.low', 'Keywords', self::SEV_WARNING,
                "Topical coverage is low ({$score}%)",
                "Your page ranks for " . ($cov['total'] ?? 0) . " queries in Search Console, but only " . ($cov['found_count'] ?? 0) . " of those phrases actually appear in the body. Google infers topical depth from lexical breadth — a thin overlap caps your ranking ceiling.",
                "Expand the article to cover the missing queries{$list}. Weave each in naturally once or twice; don't force exact matches.");
        } elseif ($verdict === 'high_authority') {
            $out[] = $this->rec('kw.coverage.high', 'Keywords', self::SEV_GOOD,
                "Strong topical coverage ({$score}%)",
                'Your body text references most of the queries Google already sends you. This is a strong topical-authority signal.',
                'Maintain this breadth when editing. Audit again after substantial content changes.');
        }

        // 3. Intent alignment
        $intent = $kw['intent'] ?? [];
        if (($intent['dominant'] ?? null) === 'informational' && ($intent['informational_count'] ?? 0) >= 3) {
            $out[] = $this->rec('kw.intent.informational', 'Keywords', self::SEV_INFO,
                'Informational search intent detected',
                'Several of your ranking queries use how-to / guide / tutorial phrasing. Pages serving informational intent win more rich-result real estate when structured for it.',
                'Add FAQ schema (JSON-LD FAQPage) for answered questions, and a Table of Contents anchor list near the top — both dramatically increase SERP footprint and dwell time.');
        } elseif (($intent['dominant'] ?? null) === 'utility' && ($intent['utility_count'] ?? 0) >= 3) {
            $out[] = $this->rec('kw.intent.utility', 'Keywords', self::SEV_INFO,
                'Utility / transactional search intent detected',
                'Queries like "generator", "tool", "maker", "online" indicate users want to *do* something, not just read. Pages that bury the tool under intro copy have far lower conversion.',
                'Move the interactive tool above-the-fold. Keep descriptive copy below. Add clear step-by-step usage instructions for SERP feature eligibility.');
        }

        // 4. Accidental authority
        foreach ($kw['accidental'] ?? [] as $entry) {
            $term = $entry['term'] ?? '';
            $density = $entry['density'] ?? 0;
            if ($term === '') {
                continue;
            }
            $out[] = $this->rec('kw.accidental.' . md5($term), 'Keywords', self::SEV_INFO,
                "Accidental authority: \"{$term}\" ({$density}% density)",
                "You use \"{$term}\" frequently in the body, but it is not among your tracked target keywords and does not appear in the title or H1. You may be ranking for it without intent — and missing the chance to rank higher.",
                "If \"{$term}\" is relevant to this page, add it to the title or H1 to capture the unexpected search traffic.");
        }

        return $out;
    }

    private function rec(string $id, string $section, string $severity, string $title, string $why, string $fix): array
    {
        return compact('id', 'section', 'severity', 'title', 'why', 'fix');
    }
}
