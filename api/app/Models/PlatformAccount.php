<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAccount extends Model
{
    use HasUuids;

    protected $fillable = [
        'connection_id',
        'platform_account_id',
        'account_name',
        'account_type',
        'account_url',
        'avatar_url',
        'is_selected',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_selected' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SocialConnection::class, 'connection_id');
    }
}
