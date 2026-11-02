<?php

declare(strict_types=1);

namespace App\Services\GoogleDrive;

use App\Models\Folder;
use App\Models\Media;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Carbon;

final class MediaMetadataExtractor
{
    /**
     * @return array{
     *     taken_at: Carbon|null,
     *     taken_at_source: string|null,
     *     lat: float|null,
     *     lng: float|null,
     *     width: int|null,
     *     height: int|null
     * }
     */
    public function extract(DriveFile $file, ?Folder $folder = null): array
    {
        [$takenAt, $source] = $this->resolveTakenAt($file, $folder);
        [$lat, $lng] = $this->resolveLocation($file);
        [$width, $height] = $this->resolveDimensions($file);

        return [
            'taken_at' => $takenAt,
            'taken_at_source' => $source,
            'lat' => $lat,
            'lng' => $lng,
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * @return array{0: Carbon|null, 1: string|null}
     */
    private function resolveTakenAt(DriveFile $file, ?Folder $folder): array
    {
        $imageMeta = $file->getImageMediaMetadata();
        if ($imageMeta !== null && filled($imageMeta->getTime())) {
            $parsed = $this->parseFlexibleDate((string) $imageMeta->getTime());
            if ($parsed !== null) {
                return [$parsed, Media::TAKEN_AT_CAMERA];
            }
        }

        $fromName = $this->dateFromFilename((string) $file->getName());
        if ($fromName !== null) {
            return [$fromName, Media::TAKEN_AT_FILENAME];
        }

        if ($folder?->year !== null) {
            return [
                Carbon::create($folder->year, 1, 1)?->startOfDay(),
                Media::TAKEN_AT_FOLDER,
            ];
        }

        if (filled($file->getCreatedTime())) {
            $parsed = $this->parseFlexibleDate((string) $file->getCreatedTime());
            if ($parsed !== null) {
                return [$parsed, Media::TAKEN_AT_UPLOAD];
            }
        }

        return [null, null];
    }

    /**
     * @return array{0: float|null, 1: float|null}
     */
    private function resolveLocation(DriveFile $file): array
    {
        $imageMeta = $file->getImageMediaMetadata();
        $location = $imageMeta?->getLocation();

        if ($location === null) {
            return [null, null];
        }

        $lat = $location->getLatitude();
        $lng = $location->getLongitude();

        if ($lat === null || $lng === null) {
            return [null, null];
        }

        return [(float) $lat, (float) $lng];
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function resolveDimensions(DriveFile $file): array
    {
        $imageMeta = $file->getImageMediaMetadata();
        if ($imageMeta !== null) {
            return [
                $imageMeta->getWidth() !== null ? (int) $imageMeta->getWidth() : null,
                $imageMeta->getHeight() !== null ? (int) $imageMeta->getHeight() : null,
            ];
        }

        $videoMeta = $file->getVideoMediaMetadata();
        if ($videoMeta !== null) {
            return [
                $videoMeta->getWidth() !== null ? (int) $videoMeta->getWidth() : null,
                $videoMeta->getHeight() !== null ? (int) $videoMeta->getHeight() : null,
            ];
        }

        return [null, null];
    }

    private function dateFromFilename(string $name): ?Carbon
    {
        // WhatsApp: IMG-20230514-WA0001, VID-20230514-WA0001
        if (preg_match('/(?:IMG|VID|PXL)[-_]?(\d{4})(\d{2})(\d{2})/i', $name, $matches) === 1) {
            return $this->safeDate((int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        // Generic: 2023-05-14 or 20230514
        if (preg_match('/\b(20\d{2}|19\d{2})[-_]?(\d{2})[-_]?(\d{2})\b/', $name, $matches) === 1) {
            return $this->safeDate((int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        return null;
    }

    private function safeDate(int $year, int $month, int $day): ?Carbon
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return Carbon::create($year, $month, $day)?->startOfDay();
    }

    private function parseFlexibleDate(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
