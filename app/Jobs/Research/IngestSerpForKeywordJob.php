<?php

namespace App\Jobs\Research;

use App\Models\Research\Keyword;
use App\Models\Website;
use App\Services\Research\SerpIngestionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class IngestSerpForKeywordJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;
    public int $tries = 2;

    public function __construct(
        public int $keywordId,
        public string $device = 'desktop',
        public ?string $location = null,
        public ?int $websiteId = null,
    ) {}

    public function handle(SerpIngestionService $service): void
    {
        $keyword = Keyword::query()->find($this->keywordId);
        if ($keyword === null) {
            return;
        }

        $website = $this->websiteId !== null ? Website::query()->find($this->websiteId) : null;

        try {
            $service->ingest($keyword, $this->device, $this->location, $website);
        } catch (\Throwable $e) {
            Log::warning('IngestSerpForKeywordJob failed: '.$e->getMessage(), [
                'keyword_id' => $this->keywordId,
            ]);
            throw $e;
        }
    }
}
