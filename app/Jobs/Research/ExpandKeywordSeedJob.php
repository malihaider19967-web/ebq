<?php

namespace App\Jobs\Research;

use App\Models\Website;
use App\Services\Research\KeywordExpansionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ExpandKeywordSeedJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;
    public int $tries = 2;

    public function __construct(
        public string $seed,
        public string $country = 'us',
        public ?int $websiteId = null,
    ) {}

    public function handle(KeywordExpansionService $service): void
    {
        $website = $this->websiteId !== null ? Website::query()->find($this->websiteId) : null;

        try {
            $service->expand($this->seed, $this->country, $website);
        } catch (\Throwable $e) {
            Log::warning('ExpandKeywordSeedJob failed: '.$e->getMessage(), [
                'seed' => mb_substr($this->seed, 0, 120),
                'country' => $this->country,
            ]);
            throw $e;
        }
    }
}
