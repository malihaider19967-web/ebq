<?php

namespace App\Services\Ai;

/**
 * Convert raw LLM text into typed values matching the tool's declared
 * `output_type`. Returns `null` when the text can't be coerced.
 *
 * Tolerant by design — the LLM occasionally wraps JSON in ```json
 * fences, prepends "Sure! Here's…" preambles, or returns prose where
 * we asked for a list. This class handles the common recoveries so
 * tools don't repeat parsing code.
 */
class OutputNormalizer
{
    public function parse(string $outputType, string $raw): mixed
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        return match ($outputType) {
            'text' => $this->stripFences($raw),
            'html' => $this->cleanHtml($raw),
            'titles' => $this->parseTitles($raw),
            'list' => $this->parseList($raw),
            'table' => $this->parseTable($raw),
            'links' => $this->parseLinks($raw),
            'schema' => $this->parseSchema($raw),
            'faq' => $this->parseFaq($raw),
            'json' => $this->decodeJson($raw),
            default => $raw,
        };
    }

    private function stripFences(string $s): string
    {
        $s = (string) preg_replace('/^```[a-z]*\s*/m', '', $s);
        $s = (string) preg_replace('/\s*```\s*$/m', '', $s);
        return trim($s);
    }

    private function cleanHtml(string $s): string
    {
        $s = $this->stripFences($s);
        // Remove any leaked markdown headers — model sometimes mixes them in.
        $s = (string) preg_replace('/^#{1,6}\s+(.+)$/m', '<h2>$1</h2>', $s);
        return trim($s);
    }

    /** @return list<string>|null */
    private function parseTitles(string $s): ?array
    {
        $list = $this->parseList($s);
        if ($list === null) {
            return null;
        }
        // Strip surrounding quotes — model often quotes title strings.
        return array_values(array_map(
            static fn (string $t) => trim($t, " \t\n\r\0\x0B\"'`"),
            $list,
        ));
    }

    /** @return list<string>|null */
    private function parseList(string $s): ?array
    {
        // Try JSON array first. `unwrapArray` handles json_object-mode
        // responses like `{"items":[...]}` that the LLM produces when
        // the tool requested strict JSON output.
        $decoded = $this->unwrapArray($this->decodeJson($s));
        if (is_array($decoded)) {
            $items = is_array($decoded[0] ?? null) ? null : $decoded;
            if (is_array($items)) {
                return array_values(array_filter(array_map(
                    static fn ($v) => is_string($v) ? trim($v) : '',
                    $items,
                ), static fn ($v) => $v !== ''));
            }
        }

        // Otherwise split by lines, strip bullets / numbering.
        $lines = preg_split('/\r?\n/', $s) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $line = (string) preg_replace('/^\s*(?:[-*•·]|\d+[.)])\s+/u', '', $line);
            $line = trim($line);
            if ($line !== '') {
                $out[] = $line;
            }
        }
        return $out;
    }

    /** @return array{headers: list<string>, rows: list<list<string>>}|null */
    private function parseTable(string $s): ?array
    {
        $decoded = $this->decodeJson($s);
        if (is_array($decoded) && is_array($decoded['headers'] ?? null) && is_array($decoded['rows'] ?? null)) {
            $headers = array_values(array_map('strval', $decoded['headers']));
            $rows = [];
            foreach ($decoded['rows'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rows[] = array_values(array_map('strval', $row));
            }
            return ['headers' => $headers, 'rows' => $rows];
        }
        return null;
    }

    /** @return list<array{url:string,anchor:string,rationale?:string}>|null */
    private function parseLinks(string $s): ?array
    {
        $decoded = $this->unwrapArray($this->decodeJson($s));
        if (! is_array($decoded)) {
            return null;
        }
        $out = [];
        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }
            $url = trim((string) ($item['url'] ?? ''));
            $anchor = trim((string) ($item['anchor'] ?? ''));
            if ($url === '' || $anchor === '') {
                continue;
            }
            $entry = ['url' => $url, 'anchor' => $anchor];
            if (! empty($item['rationale']) && is_string($item['rationale'])) {
                $entry['rationale'] = trim($item['rationale']);
            }
            $out[] = $entry;
        }
        return $out;
    }

    /** @return list<array{type:string,json_ld:array<string,mixed>}>|null */
    private function parseSchema(string $s): ?array
    {
        $decoded = $this->unwrapArray($this->decodeJson($s));
        if (! is_array($decoded)) {
            return null;
        }
        $out = [];
        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }
            $type = trim((string) ($item['type'] ?? ''));
            $jsonLd = is_array($item['json_ld'] ?? null) ? $item['json_ld'] : null;
            if ($type === '' || $jsonLd === null) {
                continue;
            }
            $out[] = ['type' => $type, 'json_ld' => $jsonLd];
        }
        return $out;
    }

    /** @return list<array{question:string,answer:string}>|null */
    private function parseFaq(string $s): ?array
    {
        $decoded = $this->unwrapArray($this->decodeJson($s));
        if (! is_array($decoded)) {
            return null;
        }
        $out = [];
        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }
            $q = trim((string) ($item['question'] ?? ''));
            $a = trim((string) ($item['answer'] ?? ''));
            if ($q === '' || $a === '') {
                continue;
            }
            $out[] = ['question' => $q, 'answer' => $a];
        }
        return $out;
    }

    /**
     * Some tools ask the LLM for a JSON array, but their `expectsJson`
     * setting puts the LLM in `response_format: json_object` mode,
     * which forbids top-level arrays. Mistral / OpenAI then wrap the
     * array in an object, e.g. `{"items":[...]}`, `{"faqs":[...]}`,
     * `{"results":[...]}`. This helper unwraps such wrappers so the
     * downstream parsers (parseFaq, parseLinks, parseSchema, parseList)
     * see the bare array they expect.
     *
     * Rules:
     *   - Already-list arrays pass through.
     *   - Object with EXACTLY ONE value that is itself an array: return
     *     that inner array.
     *   - Object with multiple keys but a single array-valued candidate
     *     under a known wrapper key (data/items/results/list/array/
     *     faqs/questions/links/suggestions/headlines/descriptions/
     *     keywords/queries): return that array.
     *   - Otherwise return the input untouched (downstream may still
     *     coerce on a best-effort basis).
     *
     * @param  array<int|string, mixed>|null  $decoded
     * @return array<int|string, mixed>|null
     */
    public function unwrapArray(?array $decoded): ?array
    {
        if ($decoded === null) {
            return null;
        }
        // Already a list (sequential numeric keys starting at 0).
        if (array_is_list($decoded)) {
            return $decoded;
        }

        // Single-key wrapper, regardless of name.
        if (count($decoded) === 1) {
            $only = reset($decoded);
            if (is_array($only)) {
                return $only;
            }
        }

        // Multi-key object with a known wrapper.
        $wrapperKeys = [
            'data', 'items', 'results', 'list', 'array',
            'faqs', 'questions',
            'links', 'suggestions',
            'headlines', 'descriptions',
            'keywords', 'queries', 'kws',
            'schemas', 'schema',
            'titles',
        ];
        foreach ($wrapperKeys as $k) {
            if (isset($decoded[$k]) && is_array($decoded[$k])) {
                return $decoded[$k];
            }
        }

        return $decoded;
    }

    /**
     * Tolerant JSON decode — strips fences, isolates the first {...}
     * or [...] block when prose surrounds it.
     *
     * @return array<int|string, mixed>|null
     */
    public function decodeJson(string $s): ?array
    {
        $s = $this->stripFences($s);
        $decoded = json_decode($s, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        if (preg_match('/(\[.*\]|\{.*\})/s', $s, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return null;
    }
}
