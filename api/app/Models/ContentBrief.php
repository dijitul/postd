<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentBrief extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'business_id',
        'source_id',
        'theme',
        'key_messages',
        'tone_notes',
        'source_type',
        'reference_data',
        'posts_generated',
        'posts_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'reference_data' => 'array',
            'posts_generated' => 'boolean',
            'posts_generated_at' => 'datetime',
        ];
    }

    // Relationships

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ContentSource::class, 'source_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'brief_id');
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('posts_generated', false);
    }
}
