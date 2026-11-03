<?php

declare(strict_types=1);

namespace App\Services\GoogleDrive;

use App\Jobs\GenerateThumbnail;
use App\Jobs\TagMedia;
use App\Models\Folder;
use App\Models\Media;
use App\Models\MediaTag;
use App\Models\SyncState;
use Google\Service\Drive;
use Google\Service\Drive\Change;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;

final class DriveSyncService
{
    private const FOLDER_MIME = 'application/vnd.google-apps.folder';

    private const FILE_FIELDS = 'nextPageToken, files(id, name, mimeType, parents, md5Checksum, webViewLink, createdTime, modifiedTime, trashed, imageMediaMetadata(width,height,time,location), videoMediaMetadata(width,height), thumbnailLink)';

    private const CHANGE_FILE_FIELDS = 'id, name, mimeType, parents, md5Checksum, webViewLink, createdTime, modifiedTime, trashed, imageMediaMetadata(width,height,time,location), videoMediaMetadata(width,height), thumbnailLink';

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
        $updates = ['last_run_at' => now()];

        if (blank($state->page_token)) {
            try {
                $updates['page_token'] = $this->fetchStartPageToken($rootFolderId);
            } catch (\Throwable $e) {
                Log::warning('Could not seed Drive changes page token after full sync', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $state->forceFill($updates)->save();

        return $counts;
    }

    /**
     * Apply Drive changes since the saved page token.
     *
     * @return array{
     *     processed: int,
     *     media_upserted: int,
     *     media_trashed: int,
     *     folders_upserted: int,
     *     thumbnails_dispatched: int,
     *     tags_dispatched: int
     * }
     */
    public function syncChanges(
        ?string $rootFolderId = null,
        bool $dispatchThumbnails = true,
        bool $dispatchTagging = true,
    ): array {
        $rootFolderId ??= (string) config('media.drive_folder_id');

        if ($rootFolderId === '') {
            throw new UnexpectedValueException('GOOGLE_DRIVE_FOLDER_ID is not set.');
        }

        $this->ensureRootFolder($rootFolderId);

        $state = SyncState::current();
        $pageToken = $state->page_token ?: $this->fetchStartPageToken($rootFolderId);

        $counts = [
            'processed' => 0,
            'media_upserted' => 0,
            'media_trashed' => 0,
            'folders_upserted' => 0,
            'thumbnails_dispatched' => 0,
            'tags_dispatched' => 0,
        ];

        $newStartToken = null;

        do {
            $response = $this->drive->changes->listChanges($pageToken, [
                'driveId' => $rootFolderId,
                'spaces' => 'drive',
                'pageSize' => 100,
                'includeItemsFromAllDrives' => true,
                'supportsAllDrives' => true,
                'includeRemoved' => true,
                'fields' => 'nextPageToken,newStartPageToken,changes(changeType,fileId,removed,file('.self::CHANGE_FILE_FIELDS.'))',
            ]);

            /** @var Change $change */
            foreach ($response->getChanges() ?? [] as $change) {
                $counts['processed']++;
                $this->applyChange($change, $rootFolderId, $counts, $dispatchThumbnails, $dispatchTagging);
            }

            if ($response->getNextPageToken()) {
                $pageToken = $response->getNextPageToken();
            } else {
                $newStartToken = $response->getNewStartPageToken() ?: $pageToken;
                $pageToken = null;
            }
        } while ($pageToken);

        $state->forceFill([
            'page_token' => $newStartToken,
            'last_run_at' => now(),
        ])->save();

        return $counts;
    }

    /**
     * @param  array{
     *     processed: int,
     *     media_upserted: int,
     *     media_trashed: int,
     *     folders_upserted: int,
     *     thumbnails_dispatched: int,
     *     tags_dispatched: int
     * }  $counts
     */
    private function applyChange(
        Change $change,
        string $rootFolderId,
        array &$counts,
        bool $dispatchThumbnails,
        bool $dispatchTagging,
    ): void {
        $fileId = (string) ($change->getFileId() ?? '');

        if ($fileId === '' || $change->getChangeType() === 'drive') {
            return;
        }

        if ($change->getRemoved()) {
            $this->trashByDriveId($fileId, $counts);

            return;
        }

        $file = $change->getFile();
        if ($file === null) {
            return;
        }

        if ($file->getTrashed()) {
            $this->trashByDriveId($fileId, $counts);

            return;
        }

        if ($file->getMimeType() === self::FOLDER_MIME) {
            $folder = $this->upsertFolderFromChange($file, $rootFolderId);
            if ($folder !== null) {
                $counts['folders_upserted']++;
            }

            return;
        }

        if (! $this->isIndexableMime((string) $file->getMimeType())) {
            return;
        }

        $folder = $this->resolveParentFolder($file, $rootFolderId);
        if ($folder === null) {
            return;
        }

        $existing = Media::query()->where('drive_file_id', $file->getId())->first();
        $isNewOrRestored = $existing === null || $existing->status === Media::STATUS_TRASHED;

        $media = $this->upsertMedia($file, $folder);
        $counts['media_upserted']++;

        if ($dispatchThumbnails && $media->isImage() && blank($media->thumb_path)) {
            GenerateThumbnail::dispatch($media->id);
            $counts['thumbnails_dispatched']++;
        }

        if ($dispatchTagging && $media->isImage() && $isNewOrRestored) {
            TagMedia::dispatch($media->id);
            $counts['tags_dispatched']++;
        }
    }

    /**
     * @param  array{media_trashed: int}  $counts
     */
    private function trashByDriveId(string $driveId, array &$counts): void
    {
        $media = Media::query()->where('drive_file_id', $driveId)->first();
        if ($media !== null) {
            $this->trashMedia($media);
            $counts['media_trashed']++;
        }

        Folder::query()->where('drive_folder_id', $driveId)->delete();
    }

    private function trashMedia(Media $media): void
    {
        if (filled($media->thumb_path)) {
            Storage::disk((string) config('media.thumbnails.disk', 'thumbnails'))
                ->delete($media->thumb_path);
        }

        MediaTag::query()->where('media_id', $media->id)->delete();

        $media->forceFill([
            'status' => Media::STATUS_TRASHED,
            'thumb_path' => null,
        ])->save();
    }

    private function upsertFolderFromChange(DriveFile $file, string $rootFolderId): ?Folder
    {
        if ($file->getId() === $rootFolderId) {
            return $this->upsertFolder($file, parent: null, path: (string) $file->getName());
        }

        $parent = $this->resolveParentFolder($file, $rootFolderId);
        if ($parent === null) {
            return null;
        }

        $path = trim(($parent->path ?? '').'/'.$file->getName(), '/');

        return $this->upsertFolder($file, parent: $parent, path: $path);
    }

    private function resolveParentFolder(DriveFile $file, string $rootFolderId): ?Folder
    {
        $parents = $file->getParents() ?? [];
        $parentDriveId = $parents[0] ?? null;

        if (! is_string($parentDriveId) || $parentDriveId === '') {
            return null;
        }

        return $this->ensureFolderChain($parentDriveId, $rootFolderId);
    }

    private function ensureFolderChain(string $driveFolderId, string $rootFolderId): ?Folder
    {
        $existing = Folder::query()->where('drive_folder_id', $driveFolderId)->first();
        if ($existing !== null) {
            return $existing;
        }

        if ($driveFolderId === $rootFolderId) {
            return $this->ensureRootFolder($rootFolderId);
        }

        try {
            $remote = $this->drive->files->get($driveFolderId, [
                'fields' => 'id, name, mimeType, parents',
                'supportsAllDrives' => true,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not resolve Drive folder for change sync', [
                'drive_folder_id' => $driveFolderId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($remote->getMimeType() !== self::FOLDER_MIME) {
            return null;
        }

        $parents = $remote->getParents() ?? [];
        $parentDriveId = $parents[0] ?? null;

        if (! is_string($parentDriveId) || $parentDriveId === '') {
            return null;
        }

        $parent = $this->ensureFolderChain($parentDriveId, $rootFolderId);
        if ($parent === null) {
            return null;
        }

        $path = trim(($parent->path ?? '').'/'.$remote->getName(), '/');

        return $this->upsertFolder($remote, parent: $parent, path: $path);
    }

    private function ensureRootFolder(string $rootFolderId): Folder
    {
        $existing = Folder::query()->where('drive_folder_id', $rootFolderId)->first();
        if ($existing !== null) {
            return $existing;
        }

        $root = $this->drive->files->get($rootFolderId, [
            'fields' => 'id, name, mimeType, parents',
            'supportsAllDrives' => true,
        ]);

        return $this->upsertFolder($root, parent: null, path: (string) $root->getName());
    }

    private function fetchStartPageToken(string $driveId): string
    {
        $token = $this->drive->changes->getStartPageToken([
            'driveId' => $driveId,
            'supportsAllDrives' => true,
        ]);

        $start = (string) $token->getStartPageToken();

        if ($start === '') {
            throw new UnexpectedValueException('Drive did not return a start page token.');
        }

        return $start;
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

        return $this->downloadOriginalBytes($driveFileId);
    }
}
