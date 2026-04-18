<?php

namespace App\Support\Audit;

class RecommendationEngine
{
    public const SEV_CRITICAL = 'critical';

    public const SEV_WARNING = 'warning';

    public const SEV_INFO = 'info';

    public const SEV_GOOD = 'good';

    /** Consultant-style signal from SERP sample (length/readability vs. organic set). */
    public const SEV_SERP_GAP = 'serp_gap';

    /**
     * Produce a prioritized list of recommendations for an audit result.
     *
     * Each entry: ['id', 'section', 'severity', 'title', 'why', 'fix']
     * Severities include {@see self::SEV_SERP_GAP} for SERP-derived length gaps.
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
        try {
            $recs = array_merge($recs, $this->serpBenchmark($result));
        } catch (\Throwable) {
            // Benchmark recs are optional; never fail the audit recommendation pass.
        }
        try {
            $recs = array_merge($recs, $this->market($result));
        } catch (\Throwable) {
            // Market-delta recs are optional; never fail the audit recommendation pass.
        }

        $order = [
            self::SEV_CRITICAL => 0,
            self::SEV_WARNING => 1,
            self::SEV_SERP_GAP => 2,
            self::SEV_INFO => 3,
            self::SEV_GOOD => 4,
        ];
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
                    'Keyword stuffing risk: "'.$top['term'].'" at '.$density.'%',
                    'Single-term density above 3% is a strong keyword-stuffing signal. Google has penalized pages for this pattern since the Panda update.',
                    'Reduce repetitions of "'.$top['term'].'". Introduce 3–4 synonyms and related LSI phrases to diversify the lexical footprint.');
            } elseif ($density >= 1.5) {
                $out[] = $this->rec('struct.density.high', 'Structure', self::SEV_INFO,
                    'Top keyword "'.$top['term'].'" at '.$density.'%',
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
                count($broken).' broken outbound link(s)',
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
            $list = $missingTerms ? ' (e.g. '.implode(', ', array_map(fn ($t) => '"'.$t.'"', $missingTerms)).')' : '';
            $out[] = $this->rec('kw.coverage.low', 'Keywords', self::SEV_WARNING,
                "Topical coverage is low ({$score}%)",
                'Your page ranks for '.($cov['total'] ?? 0).' queries in Search Console, but only '.($cov['found_count'] ?? 0).' of those phrases actually appear in the body. Google infers topical depth from lexical breadth — a thin overlap caps your ranking ceiling.',
                "Expand the article to cover the missing queries{$list}. Weave each in naturally once or twice; don't force exact matches.");
        } elseif ($verdict === 'high_authority') {
            $out[] = $this->rec('kw.coverage.high', 'Keywords', self::SEV_GOOD,
                "Strong topical coverage ({$score}%)",
                'Your body text references most of the queries Google already sends you. This is a strong topical-authority signal.',
                'Maintain this breadth when editing. Audit again after substantial content changes.');
        }

        // 3. Intent alignment (weighted scores + compound dominants, e.g. commercial_utility)
        $intent = $kw['intent'] ?? [];
        $dom = $intent['dominant'] ?? null;
        if ($dom === 'commercial_utility'
            && ($intent['blend_counts']['commercial_utility'] ?? 0) >= 1
            && (($intent['commercial_count'] ?? 0) + ($intent['utility_count'] ?? 0)) >= 2) {
            $out[] = $this->rec('kw.intent.commercial_utility', 'Keywords', self::SEV_INFO,
                'Commercial + tool intent blended',
                'Queries mix evaluation language (best, vs, reviews) with tool-task language (generator, calculator). Users want to pick the best option and use it immediately.',
                'Open with a tight comparison or ranked picks, then place the interactive tool above the fold. Align the H1 with both evaluation and the outcome the tool delivers.');
        } elseif ($dom === 'informational' && ($intent['informational_count'] ?? 0) >= 3) {
            $out[] = $this->rec('kw.intent.informational', 'Keywords', self::SEV_INFO,
                'Informational search intent detected',
                'Several ranking queries signal discovery and learning (how-to, explainers, research-style phrasing). Pages tuned for informational intent earn more rich-result and long-click behavior when structured for depth.',
                'Lead with a clear answer or overview, add a table of contents with jump links, and use FAQPage JSON-LD where you answer concrete questions in the copy.');
        } elseif ($dom === 'utility' && ($intent['utility_count'] ?? 0) >= 3) {
            $out[] = $this->rec('kw.intent.utility', 'Keywords', self::SEV_INFO,
                'Tool / app search intent detected',
                'Many queries imply an active task (generator, calculator, checker, online tool). Users expect the interactive surface immediately; long intros increase bounce before the task starts.',
                'Place the primary tool or action above the fold with a visible primary control. Move marketing copy and secondary links below; add short numbered steps for first-time use.');
        } elseif ($dom === 'commercial' && ($intent['commercial_count'] ?? 0) >= 3) {
            $out[] = $this->rec('kw.intent.commercial', 'Keywords', self::SEV_INFO,
                'Commercial evaluation intent detected',
                'Queries skew toward comparison, reviews, alternatives, and “worth it” evaluation. Thin opinion blocks underperform versus structured comparison and proof.',
                'Add a comparison table (you vs alternatives or plans), cite specs and limits honestly, and surface ratings, testimonials, or third-party proof near the decision section.');
        } elseif ($dom === 'transactional' && ($intent['transactional_count'] ?? 0) >= 3) {
            $out[] = $this->rec('kw.intent.transactional', 'Keywords', self::SEV_INFO,
                'Transactional intent detected',
                'Ranking queries reference price, purchase, deals, trials, or checkout-style language. Hesitation or buried pricing hurts conversion on these clicks.',
                'Put price or “from” pricing, primary CTA, and trust signals (guarantee, refund, security) in the first screen. Repeat the CTA after the value proof block.');
        } elseif ($dom === 'navigational' && ($intent['navigational_count'] ?? 0) >= 3) {
            $out[] = $this->rec('kw.intent.navigational', 'Keywords', self::SEV_INFO,
                'Navigational intent detected',
                'Queries suggest users already chose your brand and need a specific destination (login, account, contact, careers). Friction or ambiguous IA wastes branded demand.',
                'Use obvious labels in navigation and footer, deep-link common destinations from the homepage, and keep login/account paths one click from global chrome.');
        } elseif ($dom === 'local' && ($intent['local_count'] ?? 0) >= 3) {
            $out[] = $this->rec('kw.intent.local', 'Keywords', self::SEV_INFO,
                'Local intent detected',
                'Queries reference geography, hours, directions, pickup, or “near me.” Missing local cues reduces relevance for map packs and local SERPs.',
                'Publish consistent NAP (name, address, phone), valid LocalBusiness or store schema where applicable, and a block for hours, map embed, and service area or pickup options.');
        } elseif ($dom === 'support' && ($intent['support_count'] ?? 0) >= 3) {
            $out[] = $this->rec('kw.intent.support', 'Keywords', self::SEV_INFO,
                'Support and troubleshooting intent detected',
                'Queries point to errors, fixes, setup, refunds, or passwords. These visits need fast resolution paths, not marketing fluff — poor support UX also signals low quality to search.',
                'Surface a search-first help layout: top FAQs, links to docs, version or environment notes, and a visible path to human support or status page for outages.');
        }

        // 4. Accidental authority
        foreach ($kw['accidental'] ?? [] as $entry) {
            $term = $entry['term'] ?? '';
            $density = $entry['density'] ?? 0;
            if ($term === '') {
                continue;
            }
            $out[] = $this->rec('kw.accidental.'.md5($term), 'Keywords', self::SEV_INFO,
                "Accidental authority: \"{$term}\" ({$density}% density)",
                "You use \"{$term}\" frequently in the body, but it is not among your tracked target keywords and does not appear in the title or H1. You may be ranking for it without intent — and missing the chance to rank higher.",
                "If \"{$term}\" is relevant to this page, add it to the title or H1 to capture the unexpected search traffic.");
        }

        return $out;
    }

    /**
     * SERP sample insights: readability median, length gap, and readability vs. market average.
     *
     * @return list<array{id: string, section: string, severity: string, title: string, why: string, fix: string}>
     */
    private function serpBenchmark(array $result): array
    {
        $bench = $result['benchmark'] ?? null;
        if (! is_array($bench)) {
            return [];
        }

        $comps = $bench['competitors'] ?? [];
        if (! is_array($comps) || count($comps) < 2) {
            return [];
        }

        $kw = (string) ($bench['keyword'] ?? 'this query');
        $out = [];
        $yours = $bench['your_flesch'] ?? null;

        $fleschValues = [];
        foreach ($comps as $c) {
            if (is_array($c) && isset($c['flesch']) && is_numeric($c['flesch'])) {
                $fleschValues[] = (float) $c['flesch'];
            }
        }

        if (count($fleschValues) >= 2) {
            sort($fleschValues);
            $n = count($fleschValues);
            $median = ($n % 2 === 1)
                ? $fleschValues[(int) ($n / 2)]
                : ($fleschValues[$n / 2 - 1] + $fleschValues[$n / 2]) / 2.0;

            if (is_numeric($yours)) {
                $y = (float) $yours;
                if ($y < $median - 10) {
                    $medRounded = round($median, 1);
                    $out[] = $this->rec('bench.readability.below_median', 'Keywords', self::SEV_INFO,
                        'Readability below SERP sample median',
                        "For primary query \"{$kw}\", your Flesch score ({$y}) is more than 10 points below the median (≈{$medRounded}) of top organic pages we could fetch. That does not mean you should match competitors blindly—search intent still rules—but large readability gaps can correlate with weaker engagement for the same intent.",
                        'If your audience is broad, shorten sentences, simplify jargon, and add definition boxes for acronyms. If the topic is intentionally expert-level, keep difficulty but improve structure: headings, lists, and scannable summaries so users still get value quickly.',
                    );
                }
            }

            $avgFlesch = array_sum($fleschValues) / count($fleschValues);
            if (is_numeric($yours) && (float) $yours > $avgFlesch + 20) {
                $y = (float) $yours;
                $avgRounded = round($avgFlesch, 1);
                $out[] = $this->rec('bench.readability.easier_than_market', 'SERP benchmark', self::SEV_INFO,
                    'Your content is significantly easier to read than the market—maintain this for UX.',
                    "For \"{$kw}\", your Flesch score ({$y}) is more than 20 points above the average (≈{$avgRounded}) of the top organic pages we sampled. Searchers often reward clarity when intent is informational or mixed.",
                    'Keep plain language and scannable structure. If the query is expert or regulatory, add a short "for professionals" section so advanced readers still find depth without hurting the broad UX win.',
                );
            }
        }

        $wordSamples = [];
        foreach ($comps as $c) {
            if (is_array($c) && array_key_exists('word_count', $c) && is_numeric($c['word_count']) && (int) $c['word_count'] > 0) {
                $wordSamples[] = (float) $c['word_count'];
            }
        }
        if (count($wordSamples) >= 2) {
            $avgWords = array_sum($wordSamples) / count($wordSamples);
            $yourWords = (int) ($result['content']['word_count'] ?? 0);
            $threshold = $avgWords * 0.8;
            if ($avgWords > 0 && $yourWords < $threshold) {
                $avgRounded = (int) round($avgWords);
                $thrRounded = (int) round($threshold);
                $out[] = $this->rec('bench.serp_gap.length', 'SERP benchmark', self::SEV_SERP_GAP,
                    'Length gap vs. top organic pages',
                    "For \"{$kw}\", your page has about {$yourWords} words in the audited body, while the average of comparable top results we fetched is ≈{$avgRounded} words (80% of that average ≈{$thrRounded} words). Falling below that band can signal under-developed topical coverage for the same intent—not always, but it is a common pattern where pages stall on page two.",
                    'Aim to close the gap with substance, not padding: add sections that answer adjacent questions (FAQs, comparisons, checklists), cite primary sources, and cover subtopics competitors address. Re-audit after changes to see how the SERP sample moves.',
                );
            }
        }

        return $out;
    }

    private function rec(string $id, string $section, string $severity, string $title, string $why, string $fix): array
    {
        return compact('id', 'section', 'severity', 'title', 'why', 'fix');
    }

    /**
     * Sharp, delta-indexed recommendations from the SERP benchmark gap table.
     * Complements {@see serpBenchmark()} (which stays advisory) with concrete
     * thresholds for word-count, readability, image, and tech-stack gaps.
     *
     * @return list<array{id: string, section: string, severity: string, title: string, why: string, fix: string}>
     */
    private function market(array $result): array
    {
        $rows = data_get($result, 'benchmark.gap_table.rows');
        if (! is_array($rows) || $rows === []) {
            return [];
        }

        $byKey = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['key'])) {
                $byKey[(string) $row['key']] = $row;
            }
        }

        $out = [];

        // Word count deltas
        if (isset($byKey['word_count'])) {
            $wc = $byKey['word_count'];
            $delta = is_numeric($wc['delta'] ?? null) ? (float) $wc['delta'] : null;
            $yours = (int) ($wc['yours'] ?? 0);
            $avg = (int) round((float) ($wc['market_avg'] ?? 0));
            if ($delta !== null && $delta < -500) {
                $out[] = $this->rec('market.word_count.thin', 'SERP benchmark', self::SEV_CRITICAL,
                    'Content is significantly thinner than the Top 3',
                    "Your content is significantly thinner than the Top 3. This is likely the primary reason for your current ranking ceiling. Your page is ~{$yours} words; the sampled Top 3 average ≈{$avg} (delta " . round($delta, 0) . ').',
                    'Add depth first, length second — cover adjacent questions (FAQs, comparisons, checklists), cite primary sources, and include expert context. Re-audit after changes so you can see the delta close.');
            } elseif ($delta !== null && $delta > 1000) {
                $out[] = $this->rec('market.word_count.bloat', 'SERP benchmark', self::SEV_INFO,
                    'Content is substantially longer than the Top 3',
                    "Your page is ~{$yours} words while the Top 3 average ≈{$avg} (delta +" . round($delta, 0) . '). Length past what competitors need risks padding and can dilute topical focus.',
                    'Audit for padding. Keep depth where it earns its keep (examples, data, original analysis), but cut repetition and filler. Aim for density, not length.');
            }
        }

        // Readability deltas — zone-aware. A page already in the accessible band
        // (≥60) must never get the "too complex" warning just because the sanitized
        // market average happens to sit higher. Equally, a page that is in the sweet
        // spot deserves a positive call-out even when its delta is small.
        $absFlesch = is_numeric(data_get($result, 'content.readability.flesch'))
            ? (float) data_get($result, 'content.readability.flesch')
            : null;
        if (isset($byKey['flesch']) && $absFlesch !== null) {
            $fl = $byKey['flesch'];
            $delta = is_numeric($fl['delta'] ?? null) ? (float) $fl['delta'] : null;
            $sampleNote = $fl['sample_note'] ?? null;
            $fired = false;

            // Positive: explicit "more accessible than competitors" call-out.
            if ($absFlesch >= 60.0 && (
                ($delta !== null && $delta > 15.0)
                || ($absFlesch >= 70.0 && $delta !== null && $delta > 5.0)
            )) {
                $out[] = $this->rec('market.readability.accessible', 'SERP benchmark', self::SEV_GOOD,
                    'Much more accessible than competitors',
                    'Your content is much more accessible than competitors. This is a strong retention signal; do not increase complexity even if you add length.',
                    'Keep plain sentences, short paragraphs, and scannable structure. If you expand coverage, preserve the reading level that is winning engagement for you today.');
                $fired = true;
            }

            // Warning: only when your absolute score is actually weak AND the
            // sanitized market average isn't poisoned by outliers.
            if (! $fired && $absFlesch < 50.0 && $delta !== null && $delta < -15.0 && $sampleNote !== 'flesch_out_of_range') {
                $out[] = $this->rec('market.readability.complex', 'SERP benchmark', self::SEV_WARNING,
                    'Significantly harder to read than the Top 3',
                    'Your Flesch score is ' . round($delta, 1) . ' points below the Top 3 average, and your absolute score (' . round($absFlesch, 1) . ') sits in the "difficult" band. Pages with a readability gap of this size typically have lower dwell time and weaker engagement on the same intent.',
                    'Shorten sentences (≤20 words), replace jargon with plain language, and break paragraphs. Keep depth but improve delivery.');
                $fired = true;
            }

            // Positive fallback: always give credit when the absolute score is in
            // the broad-audience sweet spot (60–80), even if no other rule triggered.
            if (! $fired && $absFlesch >= 60.0 && $absFlesch <= 80.0) {
                $out[] = $this->rec('market.readability.sweet_spot', 'SERP benchmark', self::SEV_GOOD,
                    'Readability is in the broad-audience sweet spot',
                    'Your Flesch score (' . round($absFlesch, 1) . ') sits in the 60–80 range that reads at a 7th–9th grade level — the highest-engagement band for most web audiences.',
                    'No change needed. Preserve this reading level as you add content; avoid dense paragraphs and jargon creep.');
            }
        }

        // Image deltas
        if (isset($byKey['images'])) {
            $img = $byKey['images'];
            $delta = is_numeric($img['delta'] ?? null) ? (float) $img['delta'] : null;
            if ($delta !== null && $delta < -3) {
                $avg = round((float) ($img['market_avg'] ?? 0), 1);
                $out[] = $this->rec('market.images.light', 'SERP benchmark', self::SEV_WARNING,
                    'Competitors visualize more than you',
                    "You have {$img['yours']} images; the Top 3 average ≈{$avg} (delta " . round($delta, 1) . '). Visuals are correlated with dwell time and shareability — a persistent image gap suppresses engagement metrics.',
                    'Add 3–5 targeted visuals: screenshots, diagrams, data charts, or annotated examples. Use descriptive alt text and modern formats (WebP/AVIF).');
            }
        }

        // Tech-stack moat / disadvantage
        if (isset($byKey['stack'])) {
            $st = $byKey['stack'];
            $kind = (string) ($st['delta_kind'] ?? 'parity');
            $yourLabel = (string) ($st['yours'] ?? 'your stack');
            $compLabel = (string) ($st['market_avg'] ?? 'the Top 3');
            if ($kind === 'moat') {
                $out[] = $this->rec('market.stack.moat', 'SERP benchmark', self::SEV_GOOD,
                    'Competitive moat: modern stack vs. CMS-heavy Top 3',
                    "You are on {$yourLabel} while the Top 3 are mostly {$compLabel}. Expect a real Core Web Vitals advantage (faster LCP/INP) versus the competitor set — that translates to measurably better mobile rankings over time.",
                    'Lean on speed in your UX and content — mention load-time or ease-of-use where relevant, and keep the bundle disciplined so you do not surrender the edge.');
            } elseif ($kind === 'disadvantage') {
                $out[] = $this->rec('market.stack.disadvantage', 'SERP benchmark', self::SEV_WARNING,
                    'Stack gap: Top 3 run a modern stack, you run a CMS',
                    "The Top 3 are on {$compLabel}; you are on {$yourLabel}. A heavy CMS can cap Core Web Vitals — inflating LCP/INP beyond thresholds that Google uses as a ranking signal for mobile.",
                    'Before fighting for ranking marginals, invest in performance: full-page caching, headless/static output, image CDN, and trimming render-blocking plugins. Measure Core Web Vitals before and after.');
            }
        }

        return $out;
    }
}
