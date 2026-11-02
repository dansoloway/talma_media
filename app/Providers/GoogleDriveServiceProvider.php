<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\GoogleDrive\DriveSyncService;
use App\Services\GoogleDrive\FolderNameHints;
use App\Services\GoogleDrive\GoogleDriveClientFactory;
use App\Services\GoogleDrive\MediaMetadataExtractor;
use Google\Service\Drive;
use Illuminate\Support\ServiceProvider;

class GoogleDriveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GoogleDriveClientFactory::class);

        $this->app->singleton(Drive::class, function ($app): Drive {
            return $app->make(GoogleDriveClientFactory::class)->make();
        });

        $this->app->singleton(DriveSyncService::class, function ($app): DriveSyncService {
            return new DriveSyncService(
                drive: $app->make(Drive::class),
                folderNameHints: $app->make(FolderNameHints::class),
                metadataExtractor: $app->make(MediaMetadataExtractor::class),
            );
        });
    }
}
