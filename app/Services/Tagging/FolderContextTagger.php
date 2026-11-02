<?php

declare(strict_types=1);

namespace App\Services\Tagging;

use App\Models\Media;
use App\Models\MediaTag;

final class FolderContextTagger
{
    public function __construct(private readonly Taxonomy $taxonomy) {}

    /**
     * Apply cheap folder-derived tags (program / place) without touching manual/rejected rows.
     */
    public function apply(Media $media): void
    {
        $folder = $media->folder;
        if ($folder === null) {
            return;
        }

        if (filled($folder->program)) {
            $tag = $this->taxonomy->resolveTag('program', (string) $folder->program);
            if ($tag !== null) {
                $this->upsertFolderTag($media, $tag->id);
            }
        }

        if (filled($folder->place)) {
            $tag = $this->taxonomy->resolveTag('location', (string) $folder->place);
            if ($tag !== null) {
                $this->upsertFolderTag($media, $tag->id);
            }
        }
    }

    private function upsertFolderTag(Media $media, int $tagId): void
    {
        $existing = MediaTag::query()
            ->where('media_id', $media->id)
            ->where('tag_id', $tagId)
            ->first();

        if ($existing !== null && $existing->isProtectedFromAiOverwrite()) {
            return;
        }

        if ($existing !== null && $existing->source === MediaTag::SOURCE_MANUAL) {
            return;
        }

        MediaTag::query()->updateOrCreate(
            [
                'media_id' => $media->id,
                'tag_id' => $tagId,
            ],
            [
                'source' => MediaTag::SOURCE_FOLDER,
                'confidence' => 1.0,
                'status' => MediaTag::STATUS_CONFIRMED,
                'by_user' => null,
            ],
        );
    }
}
