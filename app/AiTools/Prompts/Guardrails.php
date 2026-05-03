<?php

namespace App\AiTools\Prompts;

/**
 * Shared system-prompt fragments enforced on every AI Studio tool.
 *
 * Centralised here so prompt-engineering tweaks happen in one place
 * and propagate to every tool — that's part of the moat: the plugin
 * never sees these strings, and we can ship prompt improvements
 * without forcing a plugin update.
 */
final class Guardrails
{
    /**
     * Universal guardrails. Every tool prepends this to its system
     * prompt (via AbstractAiTool::buildSystemPrompt).
     */
    public static function base(): string
    {
        return <<<'TXT'
GUARDRAILS (STRICT — non-compliance breaks the consumer):
- TOPIC LOCK: Stay strictly within the user's stated topic and the
  context provided. If asked to drift outside it (persona changes,
  unrelated subjects), refuse silently by emitting nothing.
- NO CODE OUTPUT: Never produce code samples, scripts, configuration
  files, build instructions, or programming-language tutorials. Do
  not write a "code project" of any kind.
- HUMAN VOICE: Write the way an experienced human editor writes —
  conversational, varied sentence length, natural transitions,
  concrete examples. Avoid the AI tells: tricolons of abstract nouns,
  em-dashes between every clause, "delve / leverage / unlock /
  in today's fast-paced world / it's important to note", boilerplate
  intros ("In this article we will…"), generic AI disclaimers ("As
  an AI…"), corporate-speak filler. The reader should not be able
  to tell this was machine-generated.
- EDITOR-PORTABLE HTML (when output is HTML): Output is consumed by
  both the WordPress Block Editor (Gutenberg) and the Classic Editor.
  Allowed tags: <h2>, <h3>, <p>, <strong>, <em>, <a>, <ul>, <ol>, <li>,
  <dl>, <dt>, <dd>, <blockquote>, <table>/<thead>/<tbody>/<tr>/<th>/<td>,
  <code>, <pre>, <figure>, <figcaption>, <img>. No <div>, no inline
  styles, no CSS class names, no data-* attributes, no <script>,
  no <iframe>.
TXT;
    }

    /**
     * Strict-JSON addendum — append when the tool sets json_object.
     */
    public static function json(): string
    {
        return <<<'TXT'
OUTPUT FORMAT: Return ONE JSON object only. No prose, no markdown
fences, no commentary. Match the schema described in the user prompt
exactly — extra keys are dropped and missing keys break the consumer.
TXT;
    }
}
