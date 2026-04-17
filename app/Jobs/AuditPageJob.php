<?php

namespace App\Jobs;

use App\Services\PageAuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

class AuditPageJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;
    public int $tries = 1;

    public function __construct(
        public int $websiteId,
        public string $pageUrl,
    ) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->lockKey()))->expireAfter(300)->dontRelease()];
    }

    public function handle(PageAuditService $service): void
    {
        $service->audit($this->websiteId, $this->pageUrl);
    }

    public function failed(\Throwable $e): void
    {
        Log::warning("AuditPageJob failed for website {$this->websiteId} page {$this->pageUrl}: {$e->getMessage()}");
    }

    private function lockKey(): string
    {
        return 'audit-page:' . $this->websiteId . ':' . sha1($this->pageUrl);
    }
}
