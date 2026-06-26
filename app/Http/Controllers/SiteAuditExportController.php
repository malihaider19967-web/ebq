<?php

namespace App\Http\Controllers;

use App\Models\ReportBranding;
use App\Models\Website;
use App\Services\Crawler\CrawlReportService;
use App\Services\Reports\CrawlAuditPdfRenderer;
use App\Services\Reports\ReportBrandingResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * On-demand branded PDF export of the crawler's full Site Audit (the
 * crawl-derived equivalent of Semrush's "Site Audit: Issues" export) for the
 * currently-selected website. Unlike the Growth Report PDF (email-attachment
 * only), this is an immediate in-browser download — no queue.
 */
class SiteAuditExportController extends Controller
{
    public function download(Request $request): Response
    {
        $user = Auth::user();
        $websiteId = session('current_website_id');
        abort_unless($websiteId !== null && $websiteId !== '' && $user?->canViewWebsiteId($websiteId), 403);

        $website = Website::find($websiteId);
        abort_unless($website !== null, 404);

        // `whitelabel=0` lets a subscriber whose plan DOES allow it still pull
        // the plain EBQ-branded copy on demand (e.g. to send to EBQ support).
        // The resolver already enforces the plan gate either way, so a plan
        // without `report_whitelabel` always gets the EBQ default regardless
        // of this flag.
        $useWhitelabel = $request->boolean('whitelabel', true);
        $branding = $useWhitelabel
            ? app(ReportBrandingResolver::class)->for($website->owner ?? $user, $website)
            : ReportBranding::ebqDefault();

        $audit = app(CrawlReportService::class)->auditExport($website->id);
        $renderer = app(CrawlAuditPdfRenderer::class);
        $bytes = $renderer->render($website, $branding, $audit);
        $filename = $renderer->filenameFor($website, $branding);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
