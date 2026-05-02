<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

class ValidRecaptcha implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = (string) config('services.recaptcha.secret_key', '');
        if ($secret === '') {
            return;
        }

        if (! is_string($value) || $value === '') {
            $fail('Please complete the CAPTCHA verification.');

            return;
        }

        $response = Http::asForm()->timeout(10)->post(
            'https://www.google.com/recaptcha/api/siteverify',
            [
                'secret' => $secret,
                'response' => $value,
                'remoteip' => request()->ip(),
            ]
        );

        if (! $response->successful()) {
            $fail('CAPTCHA verification failed. Please try again.');

            return;
        }

        $body = $response->json();
        if (! is_array($body) || empty($body['success'])) {
            $fail('CAPTCHA verification failed. Please try again.');
        }
    }
}
