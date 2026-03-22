<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemHealthLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'business_id',
        'connection_id',
        'check_type',
        'status',
        'message',
        'metadata',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SocialConnection::class, 'connection_id');
    }

    public const STATUS_OK = 'ok';
    public const STATUS_WARNING = 'warning';
    public const STATUS_ERROR = 'error';
    public const STATUS_CRITICAL = 'critical';
}
