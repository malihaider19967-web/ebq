<?php

namespace App\Livewire;

use App\Models\Website;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Prominent "we're crawling your site" banner for the current website. Drop it
 * at the top of any page whose data the first crawl builds (dashboard, pages,
 * link structure, keywords, competitor discovery, rank tracking). The view polls
 * so it appears when a crawl starts and disappears when it finishes.
 *
 * On the dashboard it also drives the crawl-derived widgets: when the running
 * state flips it emits `crawl-state-changed`, which Site Health / the action
 * queue listen for to hide during the first crawl and reappear once it's done.
 * On other pages that event simply has no listeners.
 */
class CrawlBanner extends Component
{
    public int $websiteId = 0;

    /** Id of the running crawl we last observed (0 = none), to detect changes. */
    public int $seenCrawlId = 0;

    public function mount(): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
    }

    #[On('website-changed')]
    public function onWebsiteChanged(int $websiteId): void
    {
        $this->websiteId = $websiteId;
        $this->seenCrawlId = 0;
    }

    private function website(): ?Website
    {
        if ($this->websiteId <= 0 || ! Auth::user()?->canViewWebsiteId($this->websiteId)) {
            return null;
        }

        return Website::find($this->websiteId);
    }

    public function render()
    {
        $website = $this->website();
        $crawl = $website?->runningCrawl();

        // When the running crawl appears or clears, notify any crawl-derived
        // widgets on the page to re-evaluate (hide on start, reappear on finish).
        $currentId = $crawl?->id ?? 0;
        if ($currentId !== $this->seenCrawlId) {
            $this->seenCrawlId = $currentId;
            $this->dispatch('crawl-state-changed');
        }

        return view('livewire.crawl-banner', [
            'crawl' => $crawl,
            // Progress is shown relative to THIS user's plan cap (a domain crawled
            // once is shared; a cap-1000 user sees up to 1,000 of the same run).
            'cap' => $website?->crawlPageCap() ?? 0,
            // Poll fast (10s) while a crawl is in flight; slow (30s) when idle. We
            // still poll when idle so the banner appears when a crawl starts, but the
            // old unconditional 10s poll hammered every page for every user even when
            // nothing was running.
            'pollInterval' => $crawl ? 10 : 30,
        ]);
    }
}
