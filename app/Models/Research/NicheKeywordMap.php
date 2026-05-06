<?php

namespace App\Models\Research;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NicheKeywordMap extends Model
{
    protected $table = 'niche_keyword_map';
    protected $primaryKey = null;
    public $incrementing = false;

    protected $fillable = [
        'niche_id',
        'keyword_id',
        'relevance_score',
    ];

    protected function casts(): array
    {
        return [
            'relevance_score' => 'float',
        ];
    }

    public function niche(): BelongsTo
    {
        return $this->belongsTo(Niche::class);
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }
}
