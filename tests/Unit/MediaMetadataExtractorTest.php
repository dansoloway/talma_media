<?php

declare(strict_types=1);

use App\Models\Folder;
use App\Models\Media;
use App\Services\GoogleDrive\MediaMetadataExtractor;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\DriveFileImageMediaMetadata;
use Google\Service\Drive\DriveFileImageMediaMetadataLocation;

it('prefers camera date from Drive image metadata', function () {
    $meta = new DriveFileImageMediaMetadata;
    $meta->setTime('2023-05-14T12:00:00.000Z');
    $meta->setWidth(4000);
    $meta->setHeight(3000);

    $location = new DriveFileImageMediaMetadataLocation;
    $location->setLatitude(32.0853);
    $location->setLongitude(34.7818);
    $meta->setLocation($location);

    $file = new DriveFile;
    $file->setName('photo.jpg');
    $file->setImageMediaMetadata($meta);
    $file->setCreatedTime('2024-01-01T00:00:00.000Z');

    $result = (new MediaMetadataExtractor)->extract($file);

    expect($result['taken_at_source'])->toBe(Media::TAKEN_AT_CAMERA)
        ->and($result['taken_at']?->toDateString())->toBe('2023-05-14')
        ->and($result['lat'])->toBe(32.0853)
        ->and($result['lng'])->toBe(34.7818)
        ->and($result['width'])->toBe(4000)
        ->and($result['height'])->toBe(3000);
});

it('falls back to WhatsApp-style filename dates', function () {
    $file = new DriveFile;
    $file->setName('IMG-20230514-WA0001.jpg');
    $file->setCreatedTime('2024-01-01T00:00:00.000Z');

    $result = (new MediaMetadataExtractor)->extract($file);

    expect($result['taken_at_source'])->toBe(Media::TAKEN_AT_FILENAME)
        ->and($result['taken_at']?->toDateString())->toBe('2023-05-14');
});

it('falls back to folder year when filename and camera date are missing', function () {
    $folder = new Folder(['year' => 2026, 'name' => '2026 Summer SF']);

    $file = new DriveFile;
    $file->setName('group-shot.jpg');
    $file->setCreatedTime('2024-01-01T00:00:00.000Z');

    $result = (new MediaMetadataExtractor)->extract($file, $folder);

    expect($result['taken_at_source'])->toBe(Media::TAKEN_AT_FOLDER)
        ->and($result['taken_at']?->year)->toBe(2026);
});
