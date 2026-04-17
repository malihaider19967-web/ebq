<?php

namespace App\Http\Controllers;

use App\Models\PageAuditReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PageAuditController extends Controller
{
    public function show(PageAuditReport $pageAuditReport): View
    {
        $user = Auth::user();
        abort_unless($user && $user->canViewWebsiteId($pageAuditReport->website_id), 403);

        return view('pages.page-audit-detail', [
            'pageAuditReport' => $pageAuditReport,
        ]);
    }

    public function download(Request $request, int $id): Response
    {
        $report = PageAuditReport::query()->findOrFail($id);

        $user = Auth::user();
        abort_unless($user && $user->canViewWebsiteId($report->website_id), 403);

        $html = view('pages.partials.audit-report-export', ['auditReport' => $report])->render();

        $slug = Str::slug(parse_url($report->page, PHP_URL_HOST).'-'.parse_url($report->page, PHP_URL_PATH)) ?: 'page';
        $filename = 'audit-'.$slug.'-'.($report->audited_at?->format('Ymd-Hi') ?? now()->format('Ymd-Hi')).'.html';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
