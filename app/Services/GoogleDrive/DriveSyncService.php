<?php

declare(strict_types=1);

namespace App\Services\GoogleDrive;

use App\Jobs\GenerateThumbnail;
use App\Models\Folder;
use App\Models\Media;
use App\Models\SyncState;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;

final class DriveSyncService
{
    private const FOLDER_MIME = 'application/vnd.google-apps.folder';

    private const FILE_FIELDS = 'nextPageToken, files(id, name, mimeType, parents, md5Checksum, webViewLink, createdTime, modifiedTime, trashed, imageMediaMetadata(width,height,time,location), videoMediaMetadata(width,height), thumbnailLink)';

    public function __construct(
        private readonly Drive $drive,
        private readonly FolderNameHints $folderNameHints,
        private readonly MediaMetadataExtractor $metadataExtractor,
    ) {}

    /**
     * Recursively walk the configured Drive folder and upsert folders + media.
     *
     * @return array{folders: int, media: int, thumbnails_dispatched: int}
     */
    public function syncFull(?string $rootFolderId = null, bool $dispatchThumbnails = true): array
    {
        $rootFolderId ??= (string) config('media.drive_folder_id');

        if ($rootFolderId === '') {
            throw new UnexpectedValueException('GOOGLE_DRIVE_FOLDER_ID is not set.');
        }

        $root = $this->drive->files->get($rootFolderId, [
            'fields' => 'id, name, mimeType, parents',
            'supportsAllDrives' => true,
        ]);

        $rootFolder = $this->upsertFolder($root, parent: null, path: $root->getName());

        $counts = [
            'folders' => 1,
            'media' => 0,
            'thumbnails_dispatched' => 0,
        ];

        $this->walkFolder($rootFolder, $counts, $dispatchThumbnails);

        $state = SyncState::current();
        $state->forceFill(['last_run_at' => now()])->save();

        return $counts;
    }

    /**
     * @param  array{folders: int, media: int, thumbnails_dispatched: int}  $counts
     */
    private function walkFolder(Folder $folder, array &$counts, bool $dispatchThumbnails): void
    {
        $pageToken = null;

        do {
            $response = $this->drive->files->listFiles([
                'q' => sprintf("'%s' in parents and trashed = false", $folder->drive_folder_id),
                'spaces' => 'drive',
                'fields' => self::FILE_FIELDS,
                'pageSize' => 100,
                'pageToken' => $pageToken,
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ]);

            /** @var DriveFile $file */
            foreach ($response->getFiles() ?? [] as $file) {
                if ($file->getMimeType() === self::FOLDER_MIME) {
                    $childPath = trim(($folder->path ?? '').'/'.$file->getName(), '/');
                    $child = $this->upsertFolder($file, parent: $folder, path: $childPath);
                    $counts['folders']++;
                    $this->walkFolder($child, $counts, $dispatchThumbnails);

                    continue;
                }

                if (! $this->isIndexableMime((string) $file->getMimeType())) {
                    continue;
                }

                $media = $this->upsertMedia($file, $folder);
                $counts['media']++;

                if ($dispatchThumbnails && $media->isImage() && blank($media->thumb_path)) {
                    GenerateThumbnail::dispatch($media->id);
                    $counts['thumbnails_dispatched']++;
                }
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken);
    }

    private function upsertFolder(DriveFile $file, ?Folder $parent, string $path): Folder
    {
        $hints = $this->folderNameHints->parse((string) $file->getName());

        return Folder::query()->updateOrCreate(
            ['drive_folder_id' => $file->getId()],
            [
                'name' => $file->getName(),
                'path' => $path,
                'parent_id' => $parent?->id,
                'year' => $hints['year'],
                'program' => $hints['program'],
                'place' => $hints['place'],
            ],
        );
    }

    private function upsertMedia(DriveFile $file, Folder $folder): Media
    {
        $meta = $this->metadataExtractor->extract($file, $folder);

        return Media::query()->updateOrCreate(
            ['drive_file_id' => $file->getId()],
            [
                'name' => $file->getName(),
                'mime_type' => $file->getMimeType(),
                'folder_id' => $folder->id,
                'web_view_link' => $file->getWebViewLink(),
                'md5' => $file->getMd5Checksum(),
                'width' => $meta['width'],
                'height' => $meta['height'],
                'taken_at' => $meta['taken_at'],
                'taken_at_source' => $meta['taken_at_source'],
                'lat' => $meta['lat'],
                'lng' => $meta['lng'],
                'status' => Media::STATUS_ACTIVE,
            ],
        );
    }

    private function isIndexableMime(string $mimeType): bool
    {
        $allowed = array_merge(
            config('media.image_mime_types', []),
            config('media.video_mime_types', []),
        );

        return in_array($mimeType, $allowed, true);
    }

    public function downloadOriginalBytes(string $driveFileId): string
    {
        /** @var ResponseInterface|string $response */
        $response = $this->drive->files->get($driveFileId, [
            'alt' => 'media',
            'supportsAllDrives' => true,
        ]);

        if (is_string($response)) {
            return $response;
        }

        return (string) $response->getBody();
    }

    public function downloadThumbnailBytes(string $driveFileId, int $preferredSize = 1600): string
    {
        $file = $this->drive->files->get($driveFileId, [
            'fields' => 'id, mimeType, thumbnailLink',
            'supportsAllDrives' => true,
        ]);

        $thumbnailLink = $file->getThumbnailLink();

        if (filled($thumbnailLink)) {
            $url = preg_replace('/=s\d+$/', '=s'.$preferredSize, (string) $thumbnailLink) ?? (string) $thumbnailLink;

            try {
                $http = $this->drive->getClient()->authorize();
                $response = $http->request('GET', $url);

                if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                    return (string) $response->getBody();
                }

                Log::warning('Drive thumbnailLink download failed; falling back to files.get alt=media', [
                    'drive_file_id' => $driveFileId,
                    'status' => $response->getStatusCode(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Drive thumbnailLink download threw; falling back to files.get alt=media', [
                    'drive_file_id' => $driveFileId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        /** @var ResponseInterface|string $response */
        $response = $this->drive->files->get($driveFileId, [
            'alt' => 'media',
            'supportsAllDrives' => true,
        ]);

        if (is_string($response)) {
            return $response;
        }

        return (string) $response->getBody();
    }
}
