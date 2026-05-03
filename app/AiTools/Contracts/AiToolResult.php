<?php

namespace App\AiTools\Contracts;

/**
 * Tool execution result — typed by `outputType` from the tool's meta()
 * so the plugin knows which renderer to use.
 *
 * `output_type` legend:
 *   - text       string
 *   - html       valid HTML using the editor-portable tag palette
 *   - titles     list<string> (5 candidate titles)
 *   - list       list<string>
 *   - table      { headers: string[], rows: string[][] }
 *   - links      list<{ url: string, anchor: string, rationale?: string }>
 *   - schema     list<{ type: string, json_ld: object }>
 *   - faq        list<{ question: string, answer: string }>
 *   - json       arbitrary structured payload (last-resort)
 */
final class AiToolResult
{
    /**
     * @param  mixed  $value           the tool's primary output, shape determined by $outputType
     * @param  array{prompt:int, completion:int, total:int}  $usage
     * @param  array<string, mixed>  $diagnostics  optional extra info for the plugin to display
     */
    public function __construct(
        public readonly bool $ok,
        public readonly string $outputType,
        public readonly mixed $value,
        public readonly array $usage = ['prompt' => 0, 'completion' => 0, 'total' => 0],
        public readonly bool $cached = false,
        public readonly string $model = '',
        public readonly string $generatedAt = '',
        public readonly array $diagnostics = [],
        public readonly ?string $error = null,
        public readonly ?string $message = null,
    ) {
    }

    public static function fail(string $error, string $message = '', string $outputType = 'text'): self
    {
        return new self(
            ok: false,
            outputType: $outputType,
            value: null,
            error: $error,
            message: $message !== '' ? $message : $error,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'ok' => $this->ok,
            'output_type' => $this->outputType,
            'value' => $this->value,
            'usage' => $this->usage,
            'cached' => $this->cached,
            'model' => $this->model,
            'generated_at' => $this->generatedAt,
            'diagnostics' => $this->diagnostics !== [] ? $this->diagnostics : null,
            'error' => $this->error,
            'message' => $this->message,
        ], static fn ($v) => $v !== null);
    }
}
