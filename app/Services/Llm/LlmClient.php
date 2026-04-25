<?php

namespace App\Services\Llm;

/**
 * Provider-agnostic LLM contract — every concrete client (Mistral, OpenAI,
 * Anthropic, Gemini, Groq) implements this so service code can be written
 * once and swapped per task by binding a different implementation in the
 * service container.
 *
 * Today: only `MistralClient` implements it (Mistral Small 3.2 default).
 * Phase 2 — add a Claude / GPT-4o mini client for copywriting-heavy
 * endpoints (title rewrites, meta description suggestions) where output
 * polish matters more than per-call cost.
 */
interface LlmClient
{
    /**
     * Run a chat-style completion and return the assistant's text.
     *
     * @param  list<array{role:string, content:string}>  $messages  OpenAI-style chat messages
     * @param  array{
     *     temperature?: float,
     *     max_tokens?: int,
     *     json_object?: bool,        // ask the model to return strict JSON
     *     model?: string,            // override the client's default model
     *     timeout?: int,             // seconds; clamped to a sensible upper bound
     * }  $options
     *
     * @return array{
     *     ok: bool,
     *     content: string,           // raw assistant text (already extracted)
     *     model: string,             // resolved model id
     *     usage: array{prompt:int, completion:int, total:int},
     *     error?: string,            // populated when ok=false
     * }
     */
    public function complete(array $messages, array $options = []): array;

    /**
     * Convenience: ask for strictly-JSON output and decode it. Returns null
     * on parse failure so callers can fall back gracefully (we never throw
     * inside an editor save path).
     *
     * @param  list<array{role:string, content:string}>  $messages
     * @param  array<string, mixed>  $options
     * @return array<int|string, mixed>|null
     */
    public function completeJson(array $messages, array $options = []): ?array;

    /** True when the client is properly configured (api key set, etc.). */
    public function isAvailable(): bool;
}
