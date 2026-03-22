<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostAttempt extends Model
{
    use HasUuids;

    protected $fillable = [
        'post_id',
        'attempted_at',
        'succeeded',
        'response_code',
        'response_body',
        'error_type',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
            'succeeded' => 'boolean',
            'duration_ms' => 'float',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
