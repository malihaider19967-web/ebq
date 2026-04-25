<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PluginRelease extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ROLLED_BACK = 'rolled_back';

    protected $fillable = [
        'slug',
        'version',
        'channel',
        'status',
        'release_notes',
        'zip_path',
        'publish_at',
        'published_at',
        'rolled_back_at',
        'rollback_of_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rollbackOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rollback_of_id');
    }
}
