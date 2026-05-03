<?php

namespace App\AiTools\Contracts;

/**
 * One field in a tool's input form. The plugin renders forms generically
 * from a list of these — no per-tool UI code is needed for the common
 * case.
 *
 * Field types:
 *   - text          single-line input
 *   - textarea      multi-line input
 *   - url           URL input (validated client-side)
 *   - number        integer
 *   - select        dropdown (uses `options`)
 *   - tags          comma-separated chips → string[]
 *   - post_picker   pick an existing WP post (returns { id, url, title })
 *   - rich_text     long-form HTML (rendered as a contentEditable in the plugin)
 */
final class InputField
{
    /**
     * @param  list<array{value: string, label: string}>|null  $options
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type = 'text',
        public readonly bool $required = false,
        public readonly string $placeholder = '',
        public readonly string $help = '',
        public readonly ?int $maxLength = null,
        public readonly ?array $options = null,
        public readonly mixed $default = null,
    ) {
    }

    /**
     * Serialised shape sent to the plugin. Naming matches the plugin's
     * generic form renderer — keep it kebab-cased for JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'required' => $this->required,
            'placeholder' => $this->placeholder !== '' ? $this->placeholder : null,
            'help' => $this->help !== '' ? $this->help : null,
            'max_length' => $this->maxLength,
            'options' => $this->options,
            'default' => $this->default,
        ], static fn ($v) => $v !== null);
    }
}
