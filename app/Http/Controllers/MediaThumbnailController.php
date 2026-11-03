<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaThumbnailController extends Controller
{
    public function __invoke(Request $request, Media $media): StreamedResponse
    {
        abort_unless($request->user() !== null, 403);
        abort_if(blank($media->thumb_path), 404);

        $disk = Storage::disk((string) config('media.thumbnails.disk', 'thumbnails'));

        abort_unless($disk->exists($media->thumb_path), 404);

        return $disk->response($media->thumb_path, headers: [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
