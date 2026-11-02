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

    /*
    |--------------------------------------------------------------------------
    | AI tagging
    |--------------------------------------------------------------------------
    */

    'ai' => [
        'provider' => env('MEDIA_AI_PROVIDER', 'anthropic'),
        'model' => env('MEDIA_AI_MODEL', 'claude-haiku-4-5-20251001'),
        'prompt_version' => env('MEDIA_AI_PROMPT_VERSION', 'v1'),
        'confidence_threshold' => (float) env('MEDIA_AI_CONFIDENCE_THRESHOLD', 0.7),
        'vision_max_edge' => (int) env('MEDIA_AI_VISION_MAX_EDGE', 1600),
        'rate_limit_per_minute' => (int) env('MEDIA_AI_RATE_LIMIT_PER_MINUTE', 20),
        'timeout' => (int) env('MEDIA_AI_TIMEOUT', 90),
    ],

];
