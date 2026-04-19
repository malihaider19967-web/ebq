<?php

namespace App\Livewire\Settings;

use App\Support\Timezones;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class ProfileSettings extends Component
{
    public string $name = '';

    public string $email = '';

    public string $timezone = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $profileSaved = false;

    public bool $passwordSaved = false;

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
        $tz = $user->timezone;
        $this->timezone = (is_string($tz) && in_array($tz, timezone_identifiers_list(), true))
            ? $tz
            : (string) config('app.timezone');
    }

    public function updateProfile(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.Auth::id()],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
        ]);

        Auth::user()->update([
            'name' => $this->name,
            'email' => $this->email,
            'timezone' => $this->timezone,
        ]);

        $this->profileSaved = true;
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        Auth::user()->update([
            'password' => Hash::make($this->password),
        ]);

        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->passwordSaved = true;
    }

    public function render()
    {
        return view('livewire.settings.profile-settings', [
            'timezoneGroups' => Timezones::groupedIdentifiers(),
        ]);
    }
}
