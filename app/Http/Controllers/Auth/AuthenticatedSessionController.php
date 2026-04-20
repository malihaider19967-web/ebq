<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();
        $websiteId = (int) session('current_website_id', 0);
        if ($user && $websiteId <= 0) {
            $first = $user->accessibleWebsitesQuery()->select('id')->orderBy('domain')->first();
            if ($first) {
                $websiteId = (int) $first->id;
                session(['current_website_id' => $websiteId]);
            }
        }
        $fallback = $user ? $user->firstAccessibleRoute($websiteId) : 'dashboard';

        return redirect()->intended(route($fallback, absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
