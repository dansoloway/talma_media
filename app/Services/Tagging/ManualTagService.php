<?php

declare(strict_types=1);

namespace App\Services\Tagging;

use App\Models\Media;
use App\Models\MediaTag;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Collection;

final class ManualTagService
{
    public function confirm(MediaTag $mediaTag, User $user): void
    {
        $mediaTag->forceFill([
            'status' => MediaTag::STATUS_CONFIRMED,
            'source' => MediaTag::SOURCE_MANUAL,
            'by_user' => $user->id,
            'confidence' => max((float) ($mediaTag->confidence ?? 0), 1.0),
        ])->save();
    }

    public function reject(MediaTag $mediaTag, User $user): void
    {
        $mediaTag->forceFill([
            'status' => MediaTag::STATUS_REJECTED,
            'source' => MediaTag::SOURCE_MANUAL,
            'by_user' => $user->id,
        ])->save();
    }

    public function add(Media $media, Tag $tag, User $user): MediaTag
    {
        return MediaTag::query()->updateOrCreate(
            [
                'media_id' => $media->id,
                'tag_id' => $tag->id,
            ],
            [
                'source' => MediaTag::SOURCE_MANUAL,
                'confidence' => 1.0,
                'status' => MediaTag::STATUS_CONFIRMED,
                'by_user' => $user->id,
            ],
        );
    }

    /**
     * @param  Collection<int, int>|list<int>  $mediaIds
     */
    public function bulkAdd(Collection|array $mediaIds, Tag $tag, User $user): int
    {
        $count = 0;

        foreach (collect($mediaIds) as $mediaId) {
            $media = Media::query()->find($mediaId);
            if ($media === null) {
                continue;
            }

            $this->add($media, $tag, $user);
            $count++;
        }

        return $count;
    }

    public function confirmAllSuggested(Media $media, User $user): int
    {
        $count = 0;

        $suggested = MediaTag::query()
            ->where('media_id', $media->id)
            ->where('status', MediaTag::STATUS_SUGGESTED)
            ->get();

        foreach ($suggested as $mediaTag) {
            $this->confirm($mediaTag, $user);
            $count++;
        }

        return $count;
    }
}
