<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $media_id
 * @property int $tag_id
 * @property string $source
 * @property float|null $confidence
 * @property string $status
 * @property int|null $by_user
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'media_id',
    'tag_id',
    'source',
    'confidence',
    'status',
    'by_user',
])]
class MediaTag extends Model
{
    public const SOURCE_AI = 'ai';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_METADATA = 'metadata';

    public const SOURCE_FOLDER = 'folder';

    public const STATUS_SUGGESTED = 'suggested';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
        ];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'by_user');
    }

    public function isProtectedFromAiOverwrite(): bool
    {
        return $this->source === self::SOURCE_MANUAL
            || $this->status === self::STATUS_REJECTED;
    }
}
