<?php

namespace App\Livewire\Research;

use App\Models\Research\WebsitePage;
use App\Models\Research\WebsitePageKeyword;
use Livewire\Attributes\Url;
use Livewire\Component;

class CoverageScore extends Component
{
    public int $websiteId = 0;

    #[Url(as: 'page')]
    public ?int $pageId = null;

    public function mount(): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
    }

    public function render()
    {
        $pages = $this->websiteId === 0
            ? collect()
            : WebsitePage::query()->where('website_id', $this->websiteId)->orderBy('url')->limit(200)->get(['id', 'url', 'title', 'content_length']);

        $report = null;
        if ($this->pageId !== null) {
            $page = WebsitePage::query()->find($this->pageId);
            if ($page) {
                $kwCount = WebsitePageKeyword::query()->where('page_id', $page->id)->count();
                $headingCount = is_array($page->headings_json) ? count($page->headings_json) : 0;
                $score = min(1.0, ($kwCount / 20) * 0.6 + min(1.0, $headingCount / 10) * 0.4);
                $report = [
                    'url' => $page->url,
                    'title' => $page->title,
                    'word_count' => $page->content_length,
                    'keyword_count' => $kwCount,
                    'heading_count' => $headingCount,
                    'score' => round($score, 2),
                ];
            }
        }

        return view('livewire.research.coverage-score', compact('pages', 'report'));
    }
}
