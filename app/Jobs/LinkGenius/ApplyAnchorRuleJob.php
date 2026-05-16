<?php

namespace App\Jobs\LinkGenius;

use App\Models\LinkGeniusAnchorRule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Apply a Link Genius anchor rule across a target post (or every
 * applicable post when `$postId === null`). Triggered manually from
 * the admin UI or automatically from the WP-side `save_post` hook on
 * publish.
 *
 * The actual content rewrite happens WP-side via `wp_update_post`
 * (so revisions stay clean and other plugins' content filters keep
 * working). This server-side job is the durable accounting layer:
 * it bumps `applied_count`, stamps `last_applied_at`, and emits a log
 * line the admin UI surfaces.
 */
class ApplyAnchorRuleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(
        public int $ruleId,
        public ?int $postId = null,
    ) {}

    public function handle(): void
    {
        $rule = LinkGeniusAnchorRule::find($this->ruleId);
        if ($rule === null || $rule->status !== 'active') {
            return;
        }
        $rule->forceFill([
            'applied_count'   => (int) $rule->applied_count + 1,
            'last_applied_at' => now(),
        ])->save();

        Log::info('LinkGenius anchor rule applied', [
            'rule_id' => $rule->id,
            'website_id' => $rule->website_id,
            'post_id' => $this->postId,
            'pattern' => $rule->anchor_pattern,
            'replacement_url' => $rule->replacement_url,
        ]);
    }
}
