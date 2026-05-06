<?php

namespace App\Livewire\Research;

use App\Models\Research\WebsiteInternalLink;
use App\Models\Research\WebsitePage;
use Livewire\Attributes\Url;
use Livewire\Component;

class InternalLinkSuggestions extends Component
{
    public int $websiteId = 0;

    #[Url(as: 'page')]
    public ?int $pageId = null;

    public function mount(): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
    }

    public function accept(int $linkId): void
    {
        WebsiteInternalLink::query()->where('id', $linkId)->where('website_id', $this->websiteId)->update(['status' => 'accepted']);
    }

    public function reject(int $linkId): void
    {
        WebsiteInternalLink::query()->where('id', $linkId)->where('website_id', $this->websiteId)->update(['status' => 'rejected']);
    }

    public function render()
    {
        $pages = $this->websiteId === 0
            ? collect()
            : WebsitePage::query()->where('website_id', $this->websiteId)->orderBy('url')->limit(200)->get(['id', 'url', 'title']);

        $links = collect();
        if ($this->pageId !== null) {
            $links = WebsiteInternalLink::query()
                ->with(['fromPage:id,url,title', 'toPage:id,url,title'])
                ->where('website_id', $this->websiteId)
                ->where(fn ($q) => $q->where('to_page_id', $this->pageId)->orWhere('from_page_id', $this->pageId))
                ->orderByDesc('id')
                ->limit(100)
                ->get();
        }

        return view('livewire.research.internal-link-suggestions', compact('pages', 'links'));
    }
}
