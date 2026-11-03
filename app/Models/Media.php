<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $drive_file_id
 * @property string $name
 * @property string $mime_type
 * @property int|null $folder_id
 * @property string|null $web_view_link
 * @property string|null $thumb_path
 * @property string|null $md5
 * @property int|null $width
 * @property int|null $height
 * @property Carbon|null $taken_at
 * @property string|null $taken_at_source
 * @property string|null $lat
 * @property string|null $lng
 * @property string $status
 * @property string|null $caption
 * @property array<string, mixed>|null $ai_raw
 * @property string|null $prompt_version
 * @property string|null $embedding
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'drive_file_id',
    'name',
    'mime_type',
    'folder_id',
    'web_view_link',
    'thumb_path',
    'md5',
    'width',
    'height',
    'taken_at',
    'taken_at_source',
    'lat',
    'lng',
    'status',
    'caption',
    'ai_raw',
    'prompt_version',
    'embedding',
])]
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_TRASHED = 'trashed';

    public const TAKEN_AT_CAMERA = 'camera';

    public const TAKEN_AT_FILENAME = 'filename';

    public const TAKEN_AT_FOLDER = 'folder';

    public const TAKEN_AT_UPLOAD = 'upload';

    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'ai_raw' => 'array',
            'width' => 'integer',
            'height' => 'integer',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function mediaTags(): HasMany
    {
        return $this->hasMany(MediaTag::class);
    }

    public function tagRuns(): HasMany
    {
        return $this->hasMany(TagRun::class);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }
}
