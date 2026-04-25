<?php
/**
 * `[ebq_schema]` shortcode — renders a human-readable card from a schema
 * stored on the current post. The same data that emits as JSON-LD shows up
 * inline as a styled box readers can actually see (recipe summary, review
 * star + verdict, definition list for everything else).
 *
 * Attributes:
 *   id="<schema-id>"  Optional. Specific schema entry id from `_ebq_schemas`.
 *   type="Recipe"     Optional. First schema with this @type (or template id).
 *   With neither, falls back to the first Recipe → Review → any schema.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Schema_Shortcode
{
    private static bool $css_emitted = false;

    public function register(): void
    {
        add_shortcode('ebq_schema', [$this, 'render']);
    }

    /**
     * @param  array<string, string>|string  $atts
     */
    public function render($atts): string
    {
        if (! is_singular()) {
            return '';
        }
        $atts = shortcode_atts([
            'id'   => '',
            'type' => '',
        ], is_array($atts) ? $atts : [], 'ebq_schema');

        $post_id = (int) get_queried_object_id();
        if ($post_id <= 0) return '';

        $raw = (string) EBQ_Meta_Fields::get($post_id, '_ebq_schemas', '');
        if ($raw === '') return '';
        $list = json_decode($raw, true);
        if (! is_array($list)) return '';

        $entry = $this->pick_entry($list, $atts['id'], $atts['type']);
        if ($entry === null) return '';

        $node = EBQ_Schema_Templates::render($entry, $post_id);
        if (! is_array($node)) return '';

        return $this->css() . $this->render_card($entry, $node);
    }

    /**
     * Lazy one-time front-end stylesheet for the cards. Inlined so we don't
     * register a separate file just for the shortcode.
     */
    private function css(): string
    {
        if (self::$css_emitted) return '';
        self::$css_emitted = true;
        return '<style id="ebq-schema-card-css">'
            . '.ebq-card{display:flex;flex-direction:column;gap:12px;border:1px solid #e2e8f0;border-radius:10px;padding:16px;background:#f8fafc;margin:1.5em 0;font-family:inherit;line-height:1.5}'
            . '.ebq-card--recipe{flex-direction:row;flex-wrap:wrap}'
            . '.ebq-card__image{flex:0 0 200px;max-width:100%;border-radius:8px;overflow:hidden}'
            . '.ebq-card__image img{display:block;width:100%;height:auto}'
            . '.ebq-card__body{flex:1;min-width:240px}'
            . '.ebq-card__title{margin:0 0 6px;font-size:1.1em;font-weight:700;color:#0f172a}'
            . '.ebq-card__subtitle{margin:14px 0 6px;font-size:.85em;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#475569}'
            . '.ebq-card__desc{margin:0 0 8px;color:#334155;font-size:.95em}'
            . '.ebq-card__meta{list-style:none;display:flex;flex-wrap:wrap;gap:10px 16px;padding:0;margin:8px 0;font-size:.85em;color:#475569}'
            . '.ebq-card__meta li{margin:0}'
            . '.ebq-card__list{margin:0 0 6px;padding-left:20px;font-size:.95em;color:#1e293b}'
            . '.ebq-card__list li{margin:2px 0}'
            . '.ebq-card__head{display:flex;align-items:baseline;gap:6px;font-size:.9em;color:#475569}'
            . '.ebq-card__stars{color:#f59e0b;font-size:1.1em;letter-spacing:1px}'
            . '.ebq-card__rating{font-weight:700;color:#0f172a;font-size:1.1em}'
            . '.ebq-card__rating-max{color:#94a3b8}'
            . '.ebq-card__byline{margin:6px 0 0;font-style:italic;color:#64748b;font-size:.9em}'
            . '.ebq-card__type{display:inline-block;padding:2px 8px;border-radius:4px;background:#0f172a;color:#fff;font-size:.75em;font-weight:700;letter-spacing:.04em}'
            . '.ebq-card__dl{display:grid;grid-template-columns:max-content 1fr;column-gap:14px;row-gap:4px;margin:6px 0 0;font-size:.9em}'
            . '.ebq-card__dl dt{font-weight:600;color:#475569}'
            . '.ebq-card__dl dd{margin:0;color:#1e293b}'
            . '</style>';
    }

    /**
     * @param  list<array<string, mixed>>  $list
     */
    private function pick_entry(array $list, string $want_id, string $want_type): ?array
    {
        if ($want_id !== '') {
            foreach ($list as $entry) {
                if (is_array($entry) && (string) ($entry['id'] ?? '') === $want_id && ! empty($entry['enabled'])) {
                    return $entry;
                }
            }
            return null;
        }
        if ($want_type !== '') {
            $needle = strtolower($want_type);
            foreach ($list as $entry) {
                if (! is_array($entry) || empty($entry['enabled'])) continue;
                $type = strtolower((string) ($entry['type'] ?? ''));
                $tpl  = strtolower((string) ($entry['template'] ?? ''));
                if ($type === $needle || $tpl === $needle) {
                    return $entry;
                }
            }
            return null;
        }
        // Default preference: Recipe, then Review, then anything enabled.
        $byTemplate = ['recipe' => null, 'review' => null];
        $first = null;
        foreach ($list as $entry) {
            if (! is_array($entry) || empty($entry['enabled'])) continue;
            $tpl = (string) ($entry['template'] ?? '');
            if (array_key_exists($tpl, $byTemplate) && $byTemplate[$tpl] === null) {
                $byTemplate[$tpl] = $entry;
            }
            $first ??= $entry;
        }
        return $byTemplate['recipe'] ?? $byTemplate['review'] ?? $first;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $node
     */
    private function render_card(array $entry, array $node): string
    {
        $template = (string) ($entry['template'] ?? '');

        if ($template === 'recipe') {
            return $this->recipe_card($node);
        }
        if ($template === 'review') {
            return $this->review_card($node);
        }
        return $this->generic_card($node);
    }

    /**
     * @param  array<string, mixed>  $n
     */
    private function recipe_card(array $n): string
    {
        $name = esc_html((string) ($n['name'] ?? ''));
        $desc = esc_html((string) ($n['description'] ?? ''));
        $image = (string) ($n['image'] ?? '');
        $prep = $this->iso_duration_human((string) ($n['prepTime'] ?? ''));
        $cook = $this->iso_duration_human((string) ($n['cookTime'] ?? ''));
        $total = $this->iso_duration_human((string) ($n['totalTime'] ?? ''));
        $yield = esc_html((string) ($n['recipeYield'] ?? ''));
        $ingredients = is_array($n['recipeIngredient'] ?? null) ? $n['recipeIngredient'] : [];
        $instructions = is_array($n['recipeInstructions'] ?? null) ? $n['recipeInstructions'] : [];

        ob_start();
        ?>
        <aside class="ebq-card ebq-card--recipe">
            <?php if ($image !== ''): ?>
                <div class="ebq-card__image"><img src="<?php echo esc_url($image); ?>" alt="<?php echo $name; ?>" loading="lazy" /></div>
            <?php endif; ?>
            <div class="ebq-card__body">
                <?php if ($name !== ''): ?><h3 class="ebq-card__title"><?php echo $name; ?></h3><?php endif; ?>
                <?php if ($desc !== ''): ?><p class="ebq-card__desc"><?php echo $desc; ?></p><?php endif; ?>
                <ul class="ebq-card__meta">
                    <?php if ($prep !== ''):  ?><li><strong><?php esc_html_e('Prep', 'ebq-seo'); ?>:</strong> <?php echo esc_html($prep); ?></li><?php endif; ?>
                    <?php if ($cook !== ''):  ?><li><strong><?php esc_html_e('Cook', 'ebq-seo'); ?>:</strong> <?php echo esc_html($cook); ?></li><?php endif; ?>
                    <?php if ($total !== ''): ?><li><strong><?php esc_html_e('Total', 'ebq-seo'); ?>:</strong> <?php echo esc_html($total); ?></li><?php endif; ?>
                    <?php if ($yield !== ''): ?><li><strong><?php esc_html_e('Yield', 'ebq-seo'); ?>:</strong> <?php echo $yield; ?></li><?php endif; ?>
                </ul>
                <?php if (! empty($ingredients)): ?>
                    <h4 class="ebq-card__subtitle"><?php esc_html_e('Ingredients', 'ebq-seo'); ?></h4>
                    <ul class="ebq-card__list">
                        <?php foreach ($ingredients as $ing): ?>
                            <li><?php echo esc_html((string) $ing); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if (! empty($instructions)): ?>
                    <h4 class="ebq-card__subtitle"><?php esc_html_e('Instructions', 'ebq-seo'); ?></h4>
                    <ol class="ebq-card__list ebq-card__list--ordered">
                        <?php foreach ($instructions as $step): ?>
                            <?php $text = is_array($step) ? (string) ($step['text'] ?? '') : (string) $step; ?>
                            <li><?php echo esc_html($text); ?></li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </aside>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param  array<string, mixed>  $n
     */
    private function review_card(array $n): string
    {
        $itemName = is_array($n['itemReviewed'] ?? null) ? (string) ($n['itemReviewed']['name'] ?? '') : '';
        $rating = is_array($n['reviewRating'] ?? null) ? (float) ($n['reviewRating']['ratingValue'] ?? 0) : 0;
        $best = is_array($n['reviewRating'] ?? null) ? (float) ($n['reviewRating']['bestRating'] ?? 5) : 5;
        $body = (string) ($n['reviewBody'] ?? '');
        $author = is_array($n['author'] ?? null) ? (string) ($n['author']['name'] ?? '') : '';

        $stars = $best > 0 ? max(0, min(5, round($rating / ($best / 5)))) : 0;
        $stars_str = str_repeat('★', (int) $stars) . str_repeat('☆', 5 - (int) $stars);

        ob_start();
        ?>
        <aside class="ebq-card ebq-card--review">
            <header class="ebq-card__head">
                <span class="ebq-card__stars" aria-label="<?php echo esc_attr(sprintf('%s out of %s', $rating, $best)); ?>"><?php echo esc_html($stars_str); ?></span>
                <strong class="ebq-card__rating"><?php echo esc_html((string) $rating); ?></strong>
                <span class="ebq-card__rating-max">/ <?php echo esc_html((string) $best); ?></span>
            </header>
            <?php if ($itemName !== ''): ?>
                <h3 class="ebq-card__title"><?php echo esc_html($itemName); ?></h3>
            <?php endif; ?>
            <?php if ($body !== ''): ?>
                <p class="ebq-card__desc"><?php echo esc_html($body); ?></p>
            <?php endif; ?>
            <?php if ($author !== ''): ?>
                <p class="ebq-card__byline">— <?php echo esc_html($author); ?></p>
            <?php endif; ?>
        </aside>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param  array<string, mixed>  $n
     */
    private function generic_card(array $n): string
    {
        ob_start();
        $type = (string) ($n['@type'] ?? 'Schema');
        ?>
        <aside class="ebq-card ebq-card--generic">
            <header class="ebq-card__head"><span class="ebq-card__type"><?php echo esc_html($type); ?></span></header>
            <dl class="ebq-card__dl">
                <?php foreach ($n as $key => $value): ?>
                    <?php if ($key === '@type' || $key === '@id') continue; ?>
                    <?php if (is_array($value)) continue; // skip nested for the simple card ?>
                    <dt><?php echo esc_html((string) $key); ?></dt>
                    <dd><?php echo esc_html((string) $value); ?></dd>
                <?php endforeach; ?>
            </dl>
        </aside>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Best-effort ISO-8601 duration → human string for recipe times.
     * "PT1H30M" → "1h 30m". Returns the original string if it doesn't parse.
     */
    private function iso_duration_human(string $iso): string
    {
        if ($iso === '') return '';
        if (! preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/i', $iso, $m)) {
            return $iso;
        }
        $parts = [];
        if (! empty($m[1])) $parts[] = $m[1] . 'h';
        if (! empty($m[2])) $parts[] = $m[2] . 'm';
        if (! empty($m[3])) $parts[] = $m[3] . 's';
        return implode(' ', $parts) ?: $iso;
    }
}
