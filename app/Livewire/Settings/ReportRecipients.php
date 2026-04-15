<?php

namespace App\Livewire\Settings;

use App\Models\Website;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class ReportRecipients extends Component
{
    public int $websiteId = 0;

    /** @var array<int, bool> */
    public array $selected = [];

    public bool $saved = false;

    public function mount(): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
        $this->loadSelected();
    }

    #[On('website-changed')]
    public function switchWebsite(int $websiteId): void
    {
        $this->websiteId = $websiteId;
        $this->saved = false;
        $this->loadSelected();
    }

    public function save(): void
    {
        $this->saved = false;

        $user = Auth::user();
        $website = $this->getWebsite();

        if (! $website || (int) $website->user_id !== $user->id) {
            return;
        }

        $ids = collect($this->selected)
            ->filter()
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->toArray();

        $website->update(['report_recipients' => $ids ?: null]);
        $this->saved = true;
    }

    public function render()
    {
        $user = Auth::user();
        $website = $this->getWebsite();
        $team = collect();
        $isOwner = false;

        if ($website) {
            $isOwner = (int) $website->user_id === $user->id;

            $owner = $website->owner;
            $members = $website->members()->get();

            $team = collect([$owner])
                ->merge($members)
                ->unique('id')
                ->values();
        }

        return view('livewire.settings.report-recipients', [
            'website' => $website,
            'team' => $team,
            'isOwner' => $isOwner,
        ]);
    }

    private function getWebsite(): ?Website
    {
        if (! $this->websiteId) {
            return null;
        }

        $user = Auth::user();

        if (! $user?->canViewWebsiteId($this->websiteId)) {
            return null;
        }

        return Website::find($this->websiteId);
    }

    private function loadSelected(): void
    {
        $this->selected = [];

        $website = $this->getWebsite();

        if (! $website) {
            return;
        }

        $recipientIds = $website->report_recipients ?? [];

        if (empty($recipientIds)) {
            $this->selected[$website->user_id] = true;

            return;
        }

        $owner = $website->owner;
        $members = $website->members()->get();
        $allTeam = collect([$owner])->merge($members)->unique('id');

        foreach ($allTeam as $member) {
            $this->selected[$member->id] = in_array($member->id, $recipientIds);
        }
    }
}
