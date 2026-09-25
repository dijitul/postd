<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One photo in a business's library. See ImageLibraryService.
 */
class BusinessImage extends Model
{
    use HasUuids;

    public const SOURCE_WEBSITE = 'website';
    public const SOURCE_GOOGLE = 'google';
    public const SOURCE_UPLOAD = 'upload';
    public const SOURCE_AI = 'ai';

    // What ImageVetter found the picture to be. Only photos and illustrations
    // are ever put on a post.
    public const KIND_PHOTO = 'photo';
    public const KIND_ILLUSTRATION = 'illustration';
    public const USABLE_KINDS = [self::KIND_PHOTO, self::KIND_ILLUSTRATION];

    public const SOURCES = [
        self::SOURCE_WEBSITE,
        self::SOURCE_GOOGLE,
        self::SOURCE_UPLOAD,
        self::SOURCE_AI,
    ];

    protected $fillable = [
        'business_id',
        'source',
        'source_url',
        'page_url',
        'storage_path',
        'url',
        'thumbnail_path',
        'thumbnail_url',
        'width',
        'height',
        'content_hash',
        'perceptual_hash',
        'google_media_name',
        'google_category',
        'kind',
        'description',
        'vetted_at',
        'vetting_note',
        'is_enabled',
        'last_used_at',
        'use_count',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'is_enabled' => 'boolean',
            'last_used_at' => 'datetime',
            'vetted_at' => 'datetime',
            'use_count' => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
