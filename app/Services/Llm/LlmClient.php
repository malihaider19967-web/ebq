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

    /**
     * Run a chat-completion loop with function-calling tools. The dispatcher
     * is invoked for every tool call the model emits; its return value is
     * fed back into the conversation as a `role: tool` message and the loop
     * continues until the model either produces a final reply or the tool
     * round budget is exhausted.
     *
     * The final reply is parsed as JSON (tolerantly — same recovery path as
     * `completeJson`). Implementations MUST cap rounds defensively so a
     * misbehaving model can't burn tokens forever.
     *
     * @param  list<array{role:string, content?:string, tool_calls?:array, tool_call_id?:string, name?:string}>  $messages
     * @param  list<array{type:string, function:array{name:string, description:string, parameters:array<string,mixed>}}>  $tools
     * @param  callable(string, array<string,mixed>): (array<string,mixed>|string)  $dispatcher  Called as ($name, $args) -> tool result
     * @param  array{
     *     temperature?: float,
     *     max_tokens?: int,
     *     model?: string,
     *     timeout?: int,
     *     max_tool_rounds?: int,
     *     tool_choice?: string|array<string,mixed>,
     * }  $options
     *
     * @return array{
     *     ok: bool,
     *     decoded: array<int|string, mixed>|null,  // parsed JSON of the final reply, when ok
     *     content: string,                          // raw final reply text
     *     model: string,
     *     usage: array{prompt:int, completion:int, total:int},
     *     tool_calls: list<array<string, mixed>>,    // log of every tool call made
     *     error?: string,
     * }
     */
    public function completeWithTools(array $messages, array $tools, callable $dispatcher, array $options = []): array;
}
