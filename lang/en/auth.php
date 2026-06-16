<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Authentication Language Lines
|--------------------------------------------------------------------------
|
| Laravel 11 ships no PHP lang files by default, so dotted keys like
| `auth.failed` / `auth.throttle` were rendering as their raw key strings in
| the login error banner (see App\Http\Requests\Auth\LoginRequest). These are
| the standard Laravel lines, restored so login errors read as real sentences.
|
*/

return [
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
];
