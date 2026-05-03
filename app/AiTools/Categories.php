<?php

namespace App\AiTools;

/**
 * AI Studio tool categories. Mirrors the seven RankMath buckets so users
 * familiar with that product find what they expect, plus EBQ-specific
 * grouping where it matters.
 */
final class Categories
{
    public const RESEARCH    = 'research';
    public const WRITING     = 'writing';
    public const IMPROVEMENT = 'improvement';
    public const MARKETING   = 'marketing';
    public const ECOMMERCE   = 'ecommerce';
    public const MEDIA       = 'media';
    public const MISC        = 'misc';

    public const ORDERED = [
        self::RESEARCH,
        self::WRITING,
        self::IMPROVEMENT,
        self::MARKETING,
        self::ECOMMERCE,
        self::MEDIA,
        self::MISC,
    ];

    public const LABELS = [
        self::RESEARCH    => 'Research',
        self::WRITING     => 'Write',
        self::IMPROVEMENT => 'Improve',
        self::MARKETING   => 'Marketing',
        self::ECOMMERCE   => 'eCommerce',
        self::MEDIA       => 'Media & SEO',
        self::MISC        => 'Utilities',
    ];

    public const DESCRIPTIONS = [
        self::RESEARCH    => 'Discover topics, keywords, and SERP intent before you write.',
        self::WRITING     => 'Generate full posts, sections, paragraphs, intros, and conclusions.',
        self::IMPROVEMENT => 'Polish, condense, simplify, summarise, or restructure existing copy.',
        self::MARKETING   => 'Ad copy, social posts, email, CTAs.',
        self::ECOMMERCE   => 'Product titles, descriptions, features, and FAQs.',
        self::MEDIA       => 'Alt text, internal links, schema, external link suggestions.',
        self::MISC        => 'Quick utilities: rephrase, define, list, single sentences.',
    ];
}
