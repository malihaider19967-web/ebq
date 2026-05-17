<?php

namespace App\Livewire\Pages;

use App\Mail\PageAuditReportMail;
use App\Models\PageAuditReport;
use App\Support\AuditConfig;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

class PageAuditDetail extends Component
{
    public PageAuditReport $pageAuditReport;

    public string $auditEmail = '';

    public ?string $auditMessage = null;

    public string $auditMessageKind = 'info';

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user && $user->canViewWebsiteId($this->pageAuditReport->website_id), 403);
    }

    public function emailAuditReport(): void
    {
        $this->auditMessage = null;

        $user = Auth::user();
        if (! $user || ! $user->canViewWebsiteId($this->pageAuditReport->website_id)) {
            $this->setAuditMessage('You do not have permission to email this report.', 'error');

            return;
        }

        $rateKey = 'email-audit:'.$user->id;
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);
            $this->setAuditMessage("Too many emails. Try again in {$seconds}s.", 'error');

            return;
        }

        $validator = Validator::make(['email' => $this->auditEmail], ['email' => ['required', 'email']]);
        if ($validator->fails()) {
            $this->setAuditMessage('Please enter a valid email address.', 'error');

            return;
        }

        try {
            RateLimiter::hit($rateKey, 300);
            Mail::to($this->auditEmail)->send(new PageAuditReportMail($this->pageAuditReport, $user));
            $this->setAuditMessage("Audit report emailed to {$this->auditEmail}.", 'success');
            $this->auditEmail = '';
        } catch (\Throwable $e) {
            $this->setAuditMessage('Failed to send email: '.$e->getMessage(), 'error');
        }
    }

    public function render()
    {
        // Stale-while-revalidate: if this audit's competitor domains aren't
        // cached yet, queue a background refresh so the backlinks disclosure
        // populates on the next view. Safe to call every render — the service
        // no-ops for domains that are already fresh.
        $competitors = data_get($this->pageAuditReport->result, 'benchmark.competitors', []);
        if (AuditConfig::competitorKeywordsEverywhereEnabled() && is_array($competitors) && $competitors !== []) {
            $domains = [];
            foreach ($competitors as $row) {
                if (isset($row['url']) && is_string($row['url'])) {
                    $d = \App\Models\CompetitorBacklink::extractDomain($row['url']);
                    if ($d !== '') $domains[$d] = true;
                }
            }
            if ($domains !== []) {
                app(\App\Services\CompetitorBacklinkService::class)->queueRefresh(
                    array_keys($domains),
                    websiteId: $this->pageAuditReport->website_id,
                    ownerUserId: \Illuminate\Support\Facades\Auth::id(),
                );
            }
        }

        return view('livewire.pages.page-audit-detail');
    }

    private function setAuditMessage(string $message, string $kind = 'info'): void
    {
        $this->auditMessage = $message;
        $this->auditMessageKind = in_array($kind, ['success', 'info', 'error'], true) ? $kind : 'info';
    }
}
