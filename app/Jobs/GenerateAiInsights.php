<?php

namespace App\Jobs;

use App\Models\AiInsight;
use App\Models\Website;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class GenerateAiInsights implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $websiteId)
    {
    }

    public function handle(): void
    {
        $website = Website::findOrFail($this->websiteId);

        AiInsight::create([
            'website_id' => $website->id,
            'date' => Carbon::today(),
            'page' => '/',
            'payload' => ['summary' => 'AI insight generation placeholder for declining pages.'],
        ]);
    }
}
