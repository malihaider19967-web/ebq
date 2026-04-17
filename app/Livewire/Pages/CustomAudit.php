<?php

namespace App\Livewire\Pages;

use App\Models\Website;
use App\Services\PageAuditService;
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

    public function mount(): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
    }

    #[On('website-changed')]
    public function switchWebsite(int $websiteId): void
    {
        $this->websiteId = $websiteId;
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

        $validator = Validator::make(
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
            ]
        );

        if ($validator->fails()) {
            $this->setMessage($validator->errors()->first(), 'error');

            return;
        }

        $website = Website::query()->find($this->websiteId);
        if (! $website instanceof Website) {
            $this->setMessage('Select a website first.', 'error');

            return;
        }

        if (! $website->isAuditUrlForThisSite($normalizedUrl)) {
            $this->setMessage('The URL must use your website domain ('.$website->domain.') or a subdomain of it.', 'error');

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
            $report = $pageAuditService->audit($this->websiteId, $normalizedUrl, $keyword, true);
        } catch (\Throwable $e) {
            $this->running = false;
            $this->setMessage('Audit failed: '.$e->getMessage(), 'error');

            return;
        }
        $this->running = false;

        if ($report->status === 'completed') {
            $this->redirect(route('pages.show', ['id' => rawurlencode($normalizedUrl)]), navigate: true);

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
        return view('livewire.pages.custom-audit');
    }
}
