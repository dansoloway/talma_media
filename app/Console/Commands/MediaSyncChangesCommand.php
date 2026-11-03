<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GoogleDrive\DriveSyncService;
use Illuminate\Console\Command;
use Throwable;

class MediaSyncChangesCommand extends Command
{
    protected $signature = 'media:sync-changes
                            {--folder= : Override GOOGLE_DRIVE_FOLDER_ID}
                            {--no-thumbnails : Skip thumbnail jobs}
                            {--no-tagging : Skip TagMedia jobs for new photos}';

    protected $description = 'Apply Drive changes since the saved page token (added, moved, renamed, trashed)';

    public function handle(DriveSyncService $driveSync): int
    {
        $folderId = $this->option('folder') ?: null;

        $this->info('Starting Drive changes sync…');
        $this->line('Root folder: '.($folderId ?: (string) config('media.drive_folder_id')));

        try {
            $counts = $driveSync->syncChanges(
                rootFolderId: is_string($folderId) && $folderId !== '' ? $folderId : null,
                dispatchThumbnails: ! (bool) $this->option('no-thumbnails'),
                dispatchTagging: ! (bool) $this->option('no-tagging'),
            );
        } catch (Throwable $e) {
            $this->error('Changes sync failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Changes processed', $counts['processed']],
                ['Media upserted', $counts['media_upserted']],
                ['Media trashed', $counts['media_trashed']],
                ['Folders upserted', $counts['folders_upserted']],
                ['Thumbnail jobs', $counts['thumbnails_dispatched']],
                ['TagMedia jobs', $counts['tags_dispatched']],
            ],
        );

        $this->info('Changes sync complete.');
        $this->comment('Scheduler is not enabled yet — run this manually while AI tagging is tuned.');

        return self::SUCCESS;
    }
}
