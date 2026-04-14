<?php

namespace App\Livewire\Websites;

use App\Mail\WebsiteAccessGrantedMail;
use App\Mail\WebsiteTeamInvitationMail;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteInvitation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;

class WebsiteTeam extends Component
{
    public int $websiteId;

    public string $inviteEmail = '';

    public function mount(int $websiteId): void
    {
        $this->websiteId = $websiteId;
        Gate::authorize('update', Website::findOrFail($websiteId));
    }

    public function inviteMember(): void
    {
        $website = Website::findOrFail($this->websiteId);
        Gate::authorize('update', $website);

        $this->validate([
            'inviteEmail' => ['required', 'string', 'lowercase', 'email', 'max:255'],
        ]);

        $email = Str::lower(trim($this->inviteEmail));

        if (Str::lower($website->user->email) === $email) {
            $this->addError('inviteEmail', 'This email belongs to the site owner.');

            return;
        }

        $target = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($target) {
            if ($website->members()->whereKey($target->id)->exists()) {
                $this->addError('inviteEmail', 'This user is already a team member.');

                return;
            }

            WebsiteInvitation::query()
                ->where('website_id', $website->id)
                ->where('email', $email)
                ->delete();

            $website->members()->syncWithoutDetaching([$target->id]);

            Mail::to($target)->send(new WebsiteAccessGrantedMail($website, $target));
        } else {
            WebsiteInvitation::query()
                ->where('website_id', $website->id)
                ->where('email', $email)
                ->delete();

            [$invitation, $plain] = WebsiteInvitation::issue($website, $email, (int) Auth::id());
            Mail::to($email)->send(new WebsiteTeamInvitationMail($invitation, $plain));
        }

        $this->reset('inviteEmail');
        $this->dispatch('website-team-updated');
    }

    public function revokeMember(int $userId): void
    {
        $website = Website::findOrFail($this->websiteId);
        Gate::authorize('update', $website);

        if ($userId === (int) $website->user_id) {
            return;
        }

        $website->members()->detach($userId);
        $this->dispatch('website-team-updated');
    }

    public function cancelInvitation(int $invitationId): void
    {
        $invitation = WebsiteInvitation::findOrFail($invitationId);
        Gate::authorize('update', $invitation->website);

        if ((int) $invitation->website_id !== $this->websiteId) {
            return;
        }

        $invitation->delete();
        $this->dispatch('website-team-updated');
    }

    public function render()
    {
        $website = Website::query()
            ->with(['members', 'invitations'])
            ->findOrFail($this->websiteId);

        return view('livewire.websites.website-team', compact('website'));
    }
}
