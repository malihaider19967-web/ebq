<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomPageAudit extends Model
{
    public const SOURCE_CUSTOM = 'custom';

    public const SOURCE_PAGE_DETAIL = 'page_detail';

    protected $fillable = [
        'website_id',
        'user_id',
        'source',
        'page_url',
        'page_url_hash',
        'target_keyword',
        'serp_sample_gl',
        'page_audit_report_id',
        'status',
        'error_message',
    ];

    /**
     * Log a completed or failed audit run (custom flow or “Audit this page”).
     */
    public static function recordRun(
        int $websiteId,
        int $userId,
        string $pageUrlAsAudited,
        PageAuditReport $report,
        ?string $targetKeyword,
        string $source,
    ): self {
        $kw = $targetKeyword !== null ? trim($targetKeyword) : '';

        return self::query()->create([
            'website_id' => $websiteId,
            'user_id' => $userId,
            'source' => in_array($source, [self::SOURCE_CUSTOM, self::SOURCE_PAGE_DETAIL], true)
                ? $source
                : self::SOURCE_CUSTOM,
            'page_url' => $pageUrlAsAudited,
            'page_url_hash' => hash('sha256', $pageUrlAsAudited),
            'target_keyword' => $kw !== '' ? mb_substr($kw, 0, 200) : '',
            'serp_sample_gl' => self::serpSampleGlFromReportResult($report),
            'page_audit_report_id' => $report->id,
            'status' => $report->status,
            'error_message' => $report->error_message,
        ]);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pageAuditReport(): BelongsTo
    {
        return $this->belongsTo(PageAuditReport::class);
    }

    /**
     * Google {@code gl} used for the Serper snapshot (user-chosen or inferred), for list/history display.
     */
    public static function serpSampleGlFromReportResult(PageAuditReport $report): ?string
    {
        $result = is_array($report->result) ? $report->result : null;
        $pl = is_array($result['page_locale'] ?? null) ? $result['page_locale'] : null;
        if (! is_array($pl)) {
            return null;
        }
        foreach (['serp_gl_user_chosen', 'serp_gl_effective'] as $key) {
            if (! isset($pl[$key]) || ! is_string($pl[$key])) {
                continue;
            }
            $c = strtolower(trim($pl[$key]));
            if (strlen($c) === 2 && ctype_alpha($c)) {
                return $c;
            }
        }

        return null;
    }
}
