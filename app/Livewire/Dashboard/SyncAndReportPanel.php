<?php

namespace App\Livewire\Dashboard;

use App\Mail\GrowthReportMail;
use App\Models\Website;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

class SyncAndReportPanel extends Component
{
    public int $websiteId = 0;

    public ?string $sendError = null;

    public ?string $sendSuccess = null;

    public function mount(): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
    }

    #[On('website-changed')]
    public function switchWebsite(int $websiteId): void
    {
        $this->websiteId = $websiteId;
        $this->sendError = null;
        $this->sendSuccess = null;
    }

    public function sendReport(): void
    {
        $this->sendError = null;
        $this->sendSuccess = null;

        $user = Auth::user();
        if (! $user) {
            return;
        }

        if (! $this->websiteId || ! $user->canViewWebsiteId($this->websiteId)) {
            $this->sendError = 'Select a website to send a report.';

            return;
        }

        $website = Website::query()->find($this->websiteId);
        if (! $website) {
            $this->sendError = 'Website not found.';

            return;
        }

        $key = 'send-growth-report:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->sendError = 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.';

            return;
        }

        try {
            $reportDate = Carbon::yesterday(config('app.timezone'));
            Mail::to($user->email)->send(new GrowthReportMail($user, $website, $reportDate));
            RateLimiter::hit($key, 3600);
            $this->sendSuccess = 'Report for '.$website->domain.' sent to '.$user->email.'.';
        } catch (Throwable $e) {
            $this->sendError = 'Could not send the report. Check your mail configuration.';
            report($e);
        }
    }

    public function render()
    {
        $user = Auth::user()?->fresh();
        $website = null;

        if ($this->websiteId && $user?->canViewWebsiteId($this->websiteId)) {
            $website = Website::query()->find($this->websiteId);
        }

        return view('livewire.dashboard.sync-and-report-panel', [
            'website' => $website,
            'user' => $user,
        ]);
    }
}
