<?php

declare(strict_types=1);

use App\Jobs\GenerateThumbnail;
use App\Models\Folder;
use App\Models\Media;
use App\Services\GoogleDrive\DriveSyncService;
use App\Services\GoogleDrive\FolderNameHints;
use App\Services\GoogleDrive\MediaMetadataExtractor;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\FileList;
use Google\Service\Drive\Resource\Files;
use Illuminate\Support\Facades\Queue;

it('upserts folders and media then dispatches thumbnail jobs', function () {
    Queue::fake();

    $root = new DriveFile;
    $root->setId('root-drive-id');
    $root->setName('Test Archive');
    $root->setMimeType('application/vnd.google-apps.folder');

    $childFolder = new DriveFile;
    $childFolder->setId('folder-2026');
    $childFolder->setName('2026 Summer SF');
    $childFolder->setMimeType('application/vnd.google-apps.folder');

    $photo = new DriveFile;
    $photo->setId('photo-1');
    $photo->setName('IMG-20230514-WA0001.jpg');
    $photo->setMimeType('image/jpeg');
    $photo->setWebViewLink('https://drive.google.com/file/d/photo-1/view');
    $photo->setMd5Checksum('abc123def456abc123def456abc123de');
    $photo->setCreatedTime('2023-05-14T12:00:00.000Z');

    $video = new DriveFile;
    $video->setId('video-1');
    $video->setName('clip.mp4');
    $video->setMimeType('video/mp4');
    $video->setWebViewLink('https://drive.google.com/file/d/video-1/view');
    $video->setCreatedTime('2023-05-15T12:00:00.000Z');

    $rootListing = new FileList;
    $rootListing->setFiles([$childFolder]);

    $childListing = new FileList;
    $childListing->setFiles([$photo, $video]);

    $files = Mockery::mock(Files::class);
    $files->shouldReceive('get')
        ->once()
        ->with('root-drive-id', Mockery::type('array'))
        ->andReturn($root);

    $files->shouldReceive('listFiles')
        ->twice()
        ->andReturn($rootListing, $childListing);

    $drive = Mockery::mock(Drive::class);
    $drive->files = $files;

    $this->app->instance(Drive::class, $drive);
    $this->app->instance(DriveSyncService::class, new DriveSyncService(
        drive: $drive,
        folderNameHints: new FolderNameHints,
        metadataExtractor: new MediaMetadataExtractor,
    ));

    $this->artisan('media:sync-full', ['--folder' => 'root-drive-id'])
        ->assertSuccessful();

    expect(Folder::query()->count())->toBe(2)
        ->and(Media::query()->count())->toBe(2);

    $media = Media::query()->where('drive_file_id', 'photo-1')->first();

    expect($media)->not->toBeNull()
        ->and($media->taken_at_source)->toBe(Media::TAKEN_AT_FILENAME)
        ->and($media->folder?->year)->toBe(2026)
        ->and($media->folder?->place)->toBe('SF');

    Queue::assertPushed(GenerateThumbnail::class, fn (GenerateThumbnail $job) => $job->mediaId === $media->id);
});
