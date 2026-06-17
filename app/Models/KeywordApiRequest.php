<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * Lifecycle + result record for one asynchronous call to the self-hosted
 * keyword API. Created when we dispatch ({@see \App\Services\KeywordFinder\KeywordFinderPool}),
 * completed when the server posts back to `/webhooks/keyword-finder`.
 *
 * @property string $request_id
 * @property ?int $keyword_api_server_id
 * @property string $type
 * @property ?string $mode
 * @property ?array $payload
 * @property string $status
 * @property ?array $result
 * @property ?string $error
 */
class KeywordApiRequest extends Model
{
    use HasUlids;
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const TYPE_IDEAS = 'ideas';
    public const TYPE_VOLUME = 'volume';

    protected $fillable = [
        'request_id',
        'keyword_api_server_id',
        'type',
        'mode',
        'payload',
        'status',
        'result',
        'error',
        'user_id',
        'website_id',
        'dispatched_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'result' => 'array',
            'dispatched_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'request_id';
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(KeywordApiServer::class, 'keyword_api_server_id');
    }

    public function markRunning(): void
    {
        $this->forceFill(['status' => self::STATUS_RUNNING])->save();
    }

    /** @param array<string, mixed> $result */
    public function markCompleted(array $result): void
    {
        $this->forceFill([
            'status' => self::STATUS_COMPLETED,
            'result' => $result,
            'error' => null,
            'completed_at' => now(),
        ])->save();
    }

    public function markFailed(string $message): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'error' => mb_substr($message, 0, 250),
            'completed_at' => now(),
        ])->save();
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }
}
