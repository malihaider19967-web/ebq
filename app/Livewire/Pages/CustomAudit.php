<?php

namespace App\Livewire\Pages;

use App\Models\CustomPageAudit;
use App\Models\Website;
use App\Services\PageAuditService;
use App\Support\Audit\SerpGlCatalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\On;
use Livewire\Component;

class CustomAudit extends Component
{
    public int $websiteId = 0;

    public string $pageUrl = '';

    public string $targetKeyword = '';

    public ?string $message = null;

    public string $messageKind = 'info';

    public bool $running = false;

    public bool $awaitingSerpCountryChoice = false;

    public string $serpCountryGl = 'us';

    public ?string $serpCountryRecommendationHint = null;

    public function mount(): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
    }

    #[On('website-changed')]
    public function switchWebsite(int $websiteId): void
    {
        $this->websiteId = $websiteId;
        $this->awaitingSerpCountryChoice = false;
        $this->serpCountryRecommendationHint = null;
    }

    public function updatedPageUrl(): void
    {
        $this->awaitingSerpCountryChoice = false;
        $this->serpCountryRecommendationHint = null;
    }

    public function updatedTargetKeyword(): void
    {
        $this->awaitingSerpCountryChoice = false;
        $this->serpCountryRecommendationHint = null;
    }

    public function cancelSerpCountryStep(): void
    {
        $this->awaitingSerpCountryChoice = false;
        $this->serpCountryRecommendationHint = null;
        $this->message = null;
    }

    public function runAudit(PageAuditService $pageAuditService): void
    {
        $this->message = null;
        $this->messageKind = 'info';

        $user = Auth::user();
        if (! $user || ! $user->canViewWebsiteId($this->websiteId)) {
            $this->setMessage('You do not have permission to run an audit for this website.', 'error');

            return;
        }

        $normalizedUrl = $this->normalizePageUrl($this->pageUrl);
        $keyword = trim($this->targetKeyword);

        Validator::make(
            [
                'pageUrl' => $normalizedUrl,
                'targetKeyword' => $keyword,
            ],
            [
                'pageUrl' => ['required', 'string', 'max:2000'],
                'targetKeyword' => ['required', 'string', 'min:2', 'max:200'],
            ],
            [
                'pageUrl.required' => 'Enter a page URL.',
                'targetKeyword.required' => 'Enter the keyword to use for the SERP benchmark.',
            ],
            [],
            [
                'pageUrl' => 'page URL',
                'targetKeyword' => 'target keyword',
            ]
        )->validate();

        $website = Website::query()->find($this->websiteId);
        if (! $website instanceof Website) {
            $this->setMessage('Select a website first.', 'error');

            return;
        }

        if (! $website->isAuditUrlForThisSite($normalizedUrl)) {
            $this->addError(
                'pageUrl',
                'The URL must use your website domain ('.$website->domain.') or a subdomain of it.'
            );

            return;
        }

        $serpGlOverride = null;
        if ($this->awaitingSerpCountryChoice) {
            if (! SerpGlCatalog::isAllowedGl($this->serpCountryGl)) {
                $this->setMessage('Pick a valid country for the SERP sample.', 'error');

                return;
            }
            $serpGlOverride = strtolower(trim($this->serpCountryGl));
            $this->awaitingSerpCountryChoice = false;
            $this->serpCountryRecommendationHint = null;
        } else {
            $peek = $pageAuditService->peekSerpCountryChoiceNeeded($this->websiteId, $normalizedUrl, true);
            if (! ($peek['ok'] ?? false)) {
                $this->setMessage($peek['error'] ?? 'Could not read the page to detect locale.', 'error');

                return;
            }
            $this->serpCountryGl = (string) ($peek['recommended_gl'] ?? 'us');
            if (! SerpGlCatalog::isAllowedGl($this->serpCountryGl)) {
                $this->serpCountryGl = 'us';
            }
            $this->serpCountryRecommendationHint = (string) ($peek['recommendation_hint'] ?? '');
            $this->awaitingSerpCountryChoice = true;
            $this->setMessage('Confirm the Google SERP country below (pre-selected from your page), then click “Run audit” again.', 'info');

            return;
        }

        $rateKey = 'custom-audit:'.$user->id;
        if (RateLimiter::tooManyAttempts($rateKey, 8)) {
            $seconds = RateLimiter::availableIn($rateKey);
            $this->setMessage("Too many audits. Try again in {$seconds}s.", 'error');

            return;
        }
        RateLimiter::hit($rateKey, 120);

        $this->running = true;
        try {
            $report = $pageAuditService->audit($this->websiteId, $normalizedUrl, $keyword, true, $serpGlOverride);
        } catch (\Throwable $e) {
            $this->running = false;
            $this->setMessage('Audit failed: '.$e->getMessage(), 'error');

            return;
        }
        $this->running = false;

        CustomPageAudit::recordRun(
            $this->websiteId,
            $user->id,
            $normalizedUrl,
            $report,
            $keyword,
            CustomPageAudit::SOURCE_CUSTOM,
        );

        if ($report->status === 'completed') {
            $this->redirect(route('page-audits.show', $report), navigate: true);

            return;
        }

        $this->setMessage('Audit failed: '.($report->error_message ?? 'Unknown error'), 'error');
    }

    private function normalizePageUrl(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (! preg_match('#^https?://#i', $raw)) {
            $raw = 'https://'.$raw;
        }

        return $raw;
    }

    private function setMessage(string $text, string $kind): void
    {
        $this->message = $text;
        $this->messageKind = $kind;
    }

    public function render()
    {
        $recentAudits = collect();
        $website = null;

        if ($this->websiteId > 0 && Auth::check() && Auth::user()->canViewWebsiteId($this->websiteId)) {
            $website = Website::query()->find($this->websiteId);
            $recentAudits = CustomPageAudit::query()
                ->where('website_id', $this->websiteId)
                ->where('source', CustomPageAudit::SOURCE_CUSTOM)
                ->with(['user:id,name', 'pageAuditReport'])
                ->latest()
                ->limit(50)
                ->get();
        }

        return view('livewire.pages.custom-audit', [
            'recentAudits' => $recentAudits,
            'website' => $website,
        ]);
    }
}
