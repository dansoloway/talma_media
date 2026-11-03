<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Media;
use App\Services\GoogleDrive\DriveSyncService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaDownloadController extends Controller
{
    public function __invoke(Request $request, Media $media, DriveSyncService $driveSync): StreamedResponse
    {
        abort_unless($request->user() !== null, 403);

        $bytes = $driveSync->downloadOriginalBytes($media->drive_file_id);

        return response()->streamDownload(
            function () use ($bytes): void {
                echo $bytes;
            },
            $media->name,
            [
                'Content-Type' => $media->mime_type,
                'Cache-Control' => 'private, no-store',
            ],
        );
    }
}
