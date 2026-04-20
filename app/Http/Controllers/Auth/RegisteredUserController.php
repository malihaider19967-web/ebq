<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebsiteInvitation;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        $inviteToken = (string) $request->query('invite', '');
        $invitationEmail = '';
        if ($inviteToken !== '') {
            $invitation = WebsiteInvitation::findValidByPlainToken($inviteToken);
            if ($invitation) {
                $invitationEmail = $invitation->email;
            }
        }

        return view('auth.register', [
            'inviteToken' => $inviteToken,
            'invitationEmail' => $invitationEmail,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::defaults()],
            'invite' => ['nullable', 'string', 'max:128'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $inviteToken = $request->input('invite');
        if (is_string($inviteToken) && $inviteToken !== '') {
            $invitation = WebsiteInvitation::findValidByPlainToken($inviteToken);
            if ($invitation && Str::lower($invitation->email) === Str::lower($validated['email'])) {
                $invitation->acceptFor($user);
            }
        }

        event(new Registered($user));

        Auth::login($user);

        $websiteId = (int) session('current_website_id', 0);
        if ($websiteId <= 0) {
            $first = $user->accessibleWebsitesQuery()->select('id')->orderBy('domain')->first();
            if ($first) {
                $websiteId = (int) $first->id;
                session(['current_website_id' => $websiteId]);
            }
        }
        $fallback = $user->firstAccessibleRoute($websiteId);

        return redirect()->intended(route($fallback, absolute: false));
    }
}
