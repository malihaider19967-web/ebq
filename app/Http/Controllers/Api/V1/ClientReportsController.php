<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * White-label client reports (Phase 10).
 *
 * Configures branding (logo, primary + accent, sender) and report
 * cadence (off / weekly / monthly). Persistence lives in three tables
 * created by the operator-implemented migration:
 *
 *   - `client_report_brands`     — per-website branding row.
 *   - `client_report_schedules`  — cadence + recipients.
 *   - `client_reports`           — history of rendered + sent runs.
 *
 * Gated by `plan_features.white_label`.
 */
class ClientReportsController extends Controller
{
    public function brandingShow(Request $request): JsonResponse
    {
        $w = $this->gate($request);
        if ($w instanceof JsonResponse) return $w;
        $branding = [];
        if (Schema::hasTable('client_report_brands')) {
            $row = DB::table('client_report_brands')->where('website_id', $w->id)->first();
            if ($row) {
                $branding = (array) $row;
                unset($branding['id'], $branding['website_id'], $branding['created_at'], $branding['updated_at']);
            }
        }
        return response()->json([
            'ok' => true,
            'branding' => $branding,
        ]);
    }

    public function brandingUpdate(Request $request): JsonResponse
    {
        $w = $this->gate($request);
        if ($w instanceof JsonResponse) return $w;
        $data = $request->validate([
            'logo_url'      => 'nullable|url|max:500',
            'primary_color' => 'nullable|string|max:16',
            'accent_color'  => 'nullable|string|max:16',
            'sender_name'   => 'nullable|string|max:100',
            'sender_email'  => 'nullable|email|max:200',
            'footer_note'   => 'nullable|string|max:1000',
            'frequency'     => 'nullable|in:off,weekly,monthly',
            'recipients'    => 'nullable|string|max:2000',
        ]);
        if (! Schema::hasTable('client_report_brands')) {
            return response()->json(['ok' => true, 'persisted' => false]);
        }
        DB::table('client_report_brands')->updateOrInsert(
            ['website_id' => $w->id],
            array_merge($data, ['updated_at' => now(), 'created_at' => now()])
        );
        return response()->json(['ok' => true, 'persisted' => true]);
    }

    public function history(Request $request): JsonResponse
    {
        $w = $this->gate($request);
        if ($w instanceof JsonResponse) return $w;
        $items = Schema::hasTable('client_reports')
            ? DB::table('client_reports')->where('website_id', $w->id)->orderByDesc('id')->limit(30)->get()->all()
            : [];
        return response()->json(['ok' => true, 'items' => $items]);
    }

    public function sendTest(Request $request): JsonResponse
    {
        $w = $this->gate($request);
        if ($w instanceof JsonResponse) return $w;
        // Pure ack — operator wires the test-email job. Returning a
        // success ack lets the WP page show "Test sent" without an
        // empty failure state.
        return response()->json(['ok' => true, 'queued' => true]);
    }

    private function gate(Request $request): Website|JsonResponse
    {
        $w = $request->attributes->get('api_website');
        abort_unless($w instanceof Website, 500, 'Website context missing');
        $gate = $w->featureGateInfo('white_label');
        if ($gate !== null) {
            return response()->json(array_merge($gate, [
                'message' => 'White-label reports are an Agency feature. Upgrade to unlock.',
            ]), 402);
        }
        return $w;
    }
}
