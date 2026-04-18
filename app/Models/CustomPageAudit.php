<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomPageAudit extends Model
{
    public const SOURCE_CUSTOM = 'custom';

    public const SOURCE_PAGE_DETAIL = 'page_detail';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

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
        'queued_at',
        'started_at',
        'finished_at',
        'attempts',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'attempts' => 'int',
    ];

    /**
     * Enqueue a new audit. Called from the UI; the job runs the actual work.
     */
    public static function queue(
        int $websiteId,
        int $userId,
        string $pageUrl,
        string $targetKeyword,
        ?string $serpSampleGl,
        string $source = self::SOURCE_CUSTOM,
    ): self {
        $kw = trim($targetKeyword);
        $gl = is_string($serpSampleGl) ? strtolower(trim($serpSampleGl)) : null;
        if ($gl !== null && (strlen($gl) !== 2 || ! ctype_alpha($gl))) {
            $gl = null;
        }

        return self::query()->create([
            'website_id' => $websiteId,
            'user_id' => $userId,
            'source' => in_array($source, [self::SOURCE_CUSTOM, self::SOURCE_PAGE_DETAIL], true)
                ? $source
                : self::SOURCE_CUSTOM,
            'page_url' => $pageUrl,
            'page_url_hash' => hash('sha256', $pageUrl),
            'target_keyword' => $kw !== '' ? mb_substr($kw, 0, 200) : '',
            'serp_sample_gl' => $gl,
            'status' => self::STATUS_QUEUED,
            'queued_at' => now(),
            'attempts' => 0,
        ]);
    }

    /**
     * Look for an already-queued-or-running audit for the same (website, url, user).
     * Returning a row means we should *not* queue a duplicate and paid-API-spend twice.
     */
    public static function findActiveFor(int $websiteId, string $pageUrl, int $userId): ?self
    {
        return self::query()
            ->where('website_id', $websiteId)
            ->where('user_id', $userId)
            ->where('page_url_hash', hash('sha256', $pageUrl))
            ->whereIn('status', [self::STATUS_QUEUED, self::STATUS_RUNNING])
            ->latest('id')
            ->first();
    }

    public function markRunning(): void
    {
        $this->forceFill([
            'status' => self::STATUS_RUNNING,
            'started_at' => now(),
            'attempts' => $this->attempts + 1,
            'error_message' => null,
        ])->save();
    }

    public function markCompleted(PageAuditReport $report): void
    {
        $this->forceFill([
            'status' => self::STATUS_COMPLETED,
            'page_audit_report_id' => $report->id,
            'serp_sample_gl' => self::serpSampleGlFromReportResult($report) ?? $this->serp_sample_gl,
            'finished_at' => now(),
            'error_message' => null,
        ])->save();
    }

    public function markFailed(string $reason, ?PageAuditReport $report = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'page_audit_report_id' => $report?->id ?? $this->page_audit_report_id,
            'finished_at' => now(),
            'error_message' => mb_substr($reason, 0, 1000),
        ])->save();
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_QUEUED, self::STATUS_RUNNING]);
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function canRetry(): bool
    {
        return $this->isFailed();
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
