<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GoogleDrive\DriveSyncService;
use Illuminate\Console\Command;
use Throwable;

class MediaSyncFullCommand extends Command
{
    protected $signature = 'media:sync-full
                            {--folder= : Override GOOGLE_DRIVE_FOLDER_ID}
                            {--no-thumbnails : Upsert index only; skip thumbnail jobs}';

    protected $description = 'Walk the Drive folder tree, upsert media + folders, and queue 400px thumbnails';

    public function handle(DriveSyncService $driveSync): int
    {
        $folderId = $this->option('folder') ?: null;
        $dispatchThumbnails = ! (bool) $this->option('no-thumbnails');

        $this->info('Starting full Drive sync…');
        $this->line('Root folder: '.($folderId ?: (string) config('media.drive_folder_id')));

        try {
            $counts = $driveSync->syncFull(
                rootFolderId: is_string($folderId) && $folderId !== '' ? $folderId : null,
                dispatchThumbnails: $dispatchThumbnails,
            );
        } catch (Throwable $e) {
            $this->error('Sync failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Folders upserted', $counts['folders']],
                ['Media upserted', $counts['media']],
                ['Thumbnail jobs dispatched', $counts['thumbnails_dispatched']],
            ],
        );

        $this->info('Full sync complete.');

        if ($dispatchThumbnails && $counts['thumbnails_dispatched'] > 0) {
            $this->comment('Run `php artisan queue:work` to generate thumbnails.');
        }

        return self::SUCCESS;
    }
}
