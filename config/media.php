<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Google Drive root folder
    |--------------------------------------------------------------------------
    |
    | The Shared Drive or My Drive folder shared with the service account as
    | Viewer. Full sync walks this folder and every subfolder.
    |
    */

    'drive_folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),

    /*
    |--------------------------------------------------------------------------
    | Thumbnails
    |--------------------------------------------------------------------------
    */

    'thumbnails' => [
        'disk' => env('THUMBNAILS_DISK', 'thumbnails'),
        'path' => '',
        'max_width' => 400,
    ],

    /*
    |--------------------------------------------------------------------------
    | Indexed MIME types
    |--------------------------------------------------------------------------
    */

    'image_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/heic',
        'image/heif',
        'image/tiff',
        'image/bmp',
    ],

    'video_mime_types' => [
        'video/mp4',
        'video/quicktime',
        'video/x-msvideo',
        'video/x-matroska',
        'video/webm',
        'video/mpeg',
    ],

];
