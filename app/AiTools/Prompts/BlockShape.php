<?php

namespace App\AiTools\Prompts;

/**
 * Block-shape constraint — keeps tool output compatible with the
 * Gutenberg block it will be inserted into. Without this, running
 * "Change Tone" on a heading returns multi-sentence prose, which
 * gets stuffed into the heading element and renders as a giant
 * unreadable blob.
 *
 * Read from `input.block_name` (set by the plugin's prefill helper).
 * Returns an empty string for paragraph / list / unknown blocks —
 * those already match the tool's default output shape.
 */
final class BlockShape
{
    public static function from(array $input): string
    {
        $block = (string) ($input['block_name'] ?? '');
        if ($block === '') {
            return '';
        }

        return match ($block) {
            'core/heading' => <<<'TXT'
BLOCK SHAPE — HEADING:
- The output goes into a Gutenberg heading element. Return a SINGLE
  line ≤80 characters. No periods at the end (headings don't end
  with periods). No multi-sentence prose, no list, no leading bullet
  or numbering. Just the heading phrase.
TXT,

            'core/button' => <<<'TXT'
BLOCK SHAPE — BUTTON LABEL:
- The output is a button label. Return ONE short phrase, ≤4 words,
  in title case or sentence case. No trailing period. No quotes,
  no markdown.
TXT,

            'core/quote', 'core/pullquote' => <<<'TXT'
BLOCK SHAPE — QUOTE BLOCK:
- The output goes into a quote block. Return 1–2 sentences max
  that read like a quote — terse, punchy, voice-led. Don't add
  attribution unless the source asked for it.
TXT,

            'core/list', 'core/list-item' => <<<'TXT'
BLOCK SHAPE — LIST ITEM:
- The output is a single list item. Return ONE clause, ≤16 words.
  No leading bullet character — Gutenberg adds the bullet itself.
  No trailing period unless the item is a complete sentence.
TXT,

            'core/code', 'core/preformatted' => <<<'TXT'
BLOCK SHAPE — CODE / PREFORMATTED:
- The output goes into a code or preformatted block. Preserve
  whitespace and line breaks intact. No markdown fences.
TXT,

            default => '',
        };
    }
}
