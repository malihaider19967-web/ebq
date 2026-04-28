<?php

namespace App\Http\Controllers;

use App\Models\GoogleAccount;
use App\Services\ClientActivityLogger;
use App\Services\Google\GoogleCapTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleCapController extends Controller
{
    public function __invoke(Request $request, GoogleCapTokenVerifier $verifier, ClientActivityLogger $logger): JsonResponse
    {
        $jwt = trim((string) $request->input('security_event_token', ''));
        if ($jwt === '') {
            return response()->json(['error' => 'security_event_token is required'], 422);
        }

        try {
            $payload = $verifier->verify($jwt);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }

        $jti = (string) ($payload['jti'] ?? '');
        if ($jti !== '' && ! Cache::add('google_cap_jti:'.sha1($jti), true, now()->addDay())) {
            return response()->json(['status' => 'ok', 'duplicate' => true]);
        }

        $events = $payload['events'] ?? [];
        if (! is_array($events) || $events === []) {
            return response()->json(['status' => 'ok', 'events' => 0]);
        }

        $googleSubs = $this->extractGoogleSubs($payload, $events);
        if ($googleSubs === []) {
            return response()->json(['status' => 'ok', 'events' => count($events), 'affected_users' => 0]);
        }

        $accounts = GoogleAccount::query()
            ->with('user')
            ->whereIn('google_id', $googleSubs)
            ->get();

        $affectedUserIds = $accounts
            ->pluck('user_id')
            ->filter(fn ($v) => is_int($v) || ctype_digit((string) $v))
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        if ($affectedUserIds->isNotEmpty()) {
            DB::table('sessions')->whereIn('user_id', $affectedUserIds->all())->delete();
            DB::table('users')->whereIn('id', $affectedUserIds->all())->update(['remember_token' => Str::random(60)]);

            foreach ($affectedUserIds as $userId) {
                $logger->log('auth.google_cap_protect', userId: $userId, meta: [
                    'ip' => $request->ip(),
                    'event_types' => array_keys($events),
                    'iss' => (string) ($payload['iss'] ?? ''),
                ]);
            }

            if (Auth::check() && in_array((int) Auth::id(), $affectedUserIds->all(), true)) {
                Auth::logout();
            }
        }

        return response()->json([
            'status' => 'ok',
            'events' => count($events),
            'affected_users' => $affectedUserIds->count(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<mixed>  $events
     * @return list<string>
     */
    private function extractGoogleSubs(array $payload, array $events): array
    {
        $subs = [];

        $payloadSub = (string) ($payload['sub'] ?? '');
        if ($payloadSub !== '') {
            $subs[] = $payloadSub;
        }

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }
            $subject = $event['subject'] ?? null;
            if (! is_array($subject)) {
                continue;
            }
            $sub = (string) ($subject['sub'] ?? '');
            if ($sub !== '') {
                $subs[] = $sub;
            }
        }

        return array_values(array_unique(array_filter($subs, fn ($v) => $v !== '')));
    }
}

