<?php

namespace App\Jobs\Research;

use App\Models\Research\Keyword;
use App\Models\Website;
use App\Services\Research\KeywordEnrichmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnrichKeywordJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;
    public int $tries = 2;

    public function __construct(
        public int $keywordId,
        public ?int $websiteId = null,
    ) {}

    public function handle(KeywordEnrichmentService $service): void
    {
        $keyword = Keyword::query()->find($this->keywordId);
        if ($keyword === null) {
            return;
        }

        $website = $this->websiteId !== null ? Website::query()->find($this->websiteId) : null;
        $service->enrich($keyword, $website);
    }
}
