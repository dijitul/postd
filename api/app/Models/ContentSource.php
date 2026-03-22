<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentSource extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'business_id',
        'type',
        'source_url',
        'raw_data',
        'structured_data',
        'sentiment_score',
        'processed',
        'scraped_at',
    ];

    protected function casts(): array
    {
        return [
            'structured_data' => 'array',
            'sentiment_score' => 'float',
            'processed' => 'boolean',
            'scraped_at' => 'datetime',
        ];
    }

    // Relationships

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function contentBriefs(): HasMany
    {
        return $this->hasMany(ContentBrief::class, 'source_id');
    }

    // Scopes

    public function scopeUnprocessed($query)
    {
        return $query->where('processed', false);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // Constants

    public const TYPE_REVIEW = 'review';
    public const TYPE_WEBSITE = 'website';
    public const TYPE_NEWS = 'news';
    public const TYPE_MANUAL = 'manual';
}
