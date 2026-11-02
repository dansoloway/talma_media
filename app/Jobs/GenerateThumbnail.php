<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Media;
use App\Services\GoogleDrive\DriveSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

class GenerateThumbnail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public int $mediaId) {}

    public function handle(DriveSyncService $driveSync): void
    {
        $media = Media::query()->find($this->mediaId);

        if ($media === null) {
            return;
        }

        if (! $media->isImage()) {
            return;
        }

        if ($media->status === Media::STATUS_TRASHED) {
            return;
        }

        $bytes = $driveSync->downloadThumbnailBytes($media->drive_file_id);
        $maxWidth = (int) config('media.thumbnails.max_width', 400);

        $image = Image::decodeBinary($bytes)->scaleDown(width: $maxWidth);
        $encoded = $image->encodeUsingMediaType('image/jpeg', quality: 82);

        $disk = (string) config('media.thumbnails.disk', 'thumbnails');
        $directory = trim((string) config('media.thumbnails.path', ''), '/');
        $relativePath = ($directory !== '' ? $directory.'/' : '').$media->drive_file_id.'.jpg';

        Storage::disk($disk)->put($relativePath, (string) $encoded, [
            'visibility' => 'private',
            'ContentType' => 'image/jpeg',
        ]);

        $media->forceFill(['thumb_path' => $relativePath])->save();
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('GenerateThumbnail failed', [
            'media_id' => $this->mediaId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
