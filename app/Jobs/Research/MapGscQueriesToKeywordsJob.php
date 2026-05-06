<?php

namespace App\Jobs\Research;

use App\Services\Research\GscKeywordResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MapGscQueriesToKeywordsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(
        public int $websiteId,
        public ?int $limit = null,
    ) {}

    public function handle(GscKeywordResolver $resolver): void
    {
        $resolver->resolveForWebsite($this->websiteId, $this->limit);
    }
}
