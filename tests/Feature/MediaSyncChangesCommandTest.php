<?php

declare(strict_types=1);

use App\Jobs\GenerateThumbnail;
use App\Jobs\TagMedia;
use App\Models\Folder;
use App\Models\Media;
use App\Models\SyncState;
use App\Services\GoogleDrive\DriveSyncService;
use App\Services\GoogleDrive\FolderNameHints;
use App\Services\GoogleDrive\MediaMetadataExtractor;
use Google\Service\Drive;
use Google\Service\Drive\Change;
use Google\Service\Drive\ChangeList;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Resource\Changes;
use Google\Service\Drive\Resource\Files;
use Google\Service\Drive\StartPageToken;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('applies Drive changes: add, rename, trash, and queues tagging', function () {
    Queue::fake();
    Storage::fake('thumbnails');
    config(['media.thumbnails.disk' => 'thumbnails']);

    $root = Folder::factory()->create([
        'drive_folder_id' => 'root-drive-id',
        'name' => 'Archive',
        'path' => 'Archive',
    ]);

    SyncState::query()->create([
        'page_token' => 'token-1',
        'last_run_at' => now()->subHour(),
    ]);

    $existing = Media::factory()->create([
        'drive_file_id' => 'old-photo',
        'name' => 'old-name.jpg',
        'folder_id' => $root->id,
        'thumb_path' => 'old-photo.jpg',
        'status' => Media::STATUS_ACTIVE,
    ]);
    Storage::disk('thumbnails')->put('old-photo.jpg', 'thumb');

    $newPhoto = new DriveFile;
    $newPhoto->setId('new-photo');
    $newPhoto->setName('fresh.jpg');
    $newPhoto->setMimeType('image/jpeg');
    $newPhoto->setParents(['root-drive-id']);
    $newPhoto->setWebViewLink('https://drive.google.com/file/d/new-photo/view');
    $newPhoto->setTrashed(false);

    $renamed = new DriveFile;
    $renamed->setId('old-photo');
    $renamed->setName('renamed.jpg');
    $renamed->setMimeType('image/jpeg');
    $renamed->setParents(['root-drive-id']);
    $renamed->setTrashed(false);

    $addChange = new Change;
    $addChange->setChangeType('file');
    $addChange->setFileId('new-photo');
    $addChange->setRemoved(false);
    $addChange->setFile($newPhoto);

    $renameChange = new Change;
    $renameChange->setChangeType('file');
    $renameChange->setFileId('old-photo');
    $renameChange->setRemoved(false);
    $renameChange->setFile($renamed);

    $trashChange = new Change;
    $trashChange->setChangeType('file');
    $trashChange->setFileId('gone-photo');
    $trashChange->setRemoved(true);

    $gone = Media::factory()->create([
        'drive_file_id' => 'gone-photo',
        'folder_id' => $root->id,
        'thumb_path' => 'gone-photo.jpg',
    ]);
    Storage::disk('thumbnails')->put('gone-photo.jpg', 'gone');

    $list = new ChangeList;
    $list->setChanges([$addChange, $renameChange, $trashChange]);
    $list->setNewStartPageToken('token-2');

    $changes = Mockery::mock(Changes::class);
    $changes->shouldReceive('listChanges')
        ->once()
        ->with('token-1', Mockery::type('array'))
        ->andReturn($list);

    $files = Mockery::mock(Files::class);
    $files->shouldReceive('get')->never();

    $drive = Mockery::mock(Drive::class);
    $drive->changes = $changes;
    $drive->files = $files;

    $this->app->instance(Drive::class, $drive);
    $this->app->instance(DriveSyncService::class, new DriveSyncService(
        drive: $drive,
        folderNameHints: new FolderNameHints,
        metadataExtractor: new MediaMetadataExtractor,
    ));

    $this->artisan('media:sync-changes', ['--folder' => 'root-drive-id'])
        ->assertSuccessful();

    expect(Media::query()->where('drive_file_id', 'new-photo')->exists())->toBeTrue()
        ->and($existing->fresh()->name)->toBe('renamed.jpg')
        ->and($gone->fresh()->status)->toBe(Media::STATUS_TRASHED)
        ->and($gone->fresh()->thumb_path)->toBeNull()
        ->and(Storage::disk('thumbnails')->exists('gone-photo.jpg'))->toBeFalse()
        ->and(SyncState::current()->page_token)->toBe('token-2');

    Queue::assertPushed(GenerateThumbnail::class);
    Queue::assertPushed(TagMedia::class);
});

it('bootstraps a start page token when sync_state has none', function () {
    Folder::factory()->create([
        'drive_folder_id' => 'root-drive-id',
        'path' => 'Archive',
    ]);

    $start = new StartPageToken;
    $start->setStartPageToken('fresh-token');

    $list = new ChangeList;
    $list->setChanges([]);
    $list->setNewStartPageToken('fresh-token');

    $changes = Mockery::mock(Changes::class);
    $changes->shouldReceive('getStartPageToken')->once()->andReturn($start);
    $changes->shouldReceive('listChanges')->once()->andReturn($list);

    $files = Mockery::mock(Files::class);

    $drive = Mockery::mock(Drive::class);
    $drive->changes = $changes;
    $drive->files = $files;

    $this->app->instance(Drive::class, $drive);
    $this->app->instance(DriveSyncService::class, new DriveSyncService(
        drive: $drive,
        folderNameHints: new FolderNameHints,
        metadataExtractor: new MediaMetadataExtractor,
    ));

    $this->artisan('media:sync-changes', [
        '--folder' => 'root-drive-id',
        '--no-thumbnails' => true,
        '--no-tagging' => true,
    ])->assertSuccessful();

    expect(SyncState::current()->page_token)->toBe('fresh-token');
});
