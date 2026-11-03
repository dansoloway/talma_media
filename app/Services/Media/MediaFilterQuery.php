<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\Media;
use Illuminate\Database\Eloquent\Builder;

final class MediaFilterQuery
{
    /**
     * @param  array{
     *     search?: string|null,
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     folder_id?: int|string|null,
     *     program?: string|null,
     *     location_tag?: string|null,
     *     people_tag?: string|null,
     *     activity_tag?: string|null,
     *     setting_tag?: string|null,
     *     media_type?: string|null,
     *     tag_status?: string|null
     * }  $filters
     * @return Builder<Media>
     */
    public function build(array $filters): Builder
    {
        $query = Media::query()
            ->with(['folder', 'mediaTags.tag.category'])
            ->where('status', Media::STATUS_ACTIVE);

        if (filled($filters['search'] ?? null)) {
            $search = trim((string) $filters['search']);
            $like = '%'.$search.'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->whereLike('name', $like)
                    ->orWhereLike('caption', $like)
                    ->orWhereHas('mediaTags.tag', function (Builder $tagQuery) use ($like): void {
                        $tagQuery->whereLike('label_en', $like)
                            ->orWhereLike('label_he', $like)
                            ->orWhereLike('value', $like);
                    });
            });
        }

        if (filled($filters['date_from'] ?? null)) {
            $query->whereDate('taken_at', '>=', $filters['date_from']);
        }

        if (filled($filters['date_to'] ?? null)) {
            $query->whereDate('taken_at', '<=', $filters['date_to']);
        }

        if (filled($filters['folder_id'] ?? null)) {
            $query->where('folder_id', (int) $filters['folder_id']);
        }

        if (filled($filters['program'] ?? null)) {
            $query->whereHas('folder', fn (Builder $q) => $q->where('program', $filters['program']));
        }

        foreach (['people' => 'people_tag', 'activity' => 'activity_tag', 'setting' => 'setting_tag', 'location' => 'location_tag'] as $category => $key) {
            if (filled($filters[$key] ?? null)) {
                $value = (string) $filters[$key];
                $query->whereHas('mediaTags', function (Builder $q) use ($category, $value): void {
                    $q->whereIn('status', ['suggested', 'confirmed'])
                        ->whereHas('tag', function (Builder $tagQuery) use ($category, $value): void {
                            $tagQuery->where('value', $value)
                                ->whereHas('category', fn (Builder $c) => $c->where('key', $category));
                        });
                });
            }
        }

        $mediaType = $filters['media_type'] ?? null;
        if ($mediaType === 'image') {
            $query->where('mime_type', 'like', 'image/%');
        } elseif ($mediaType === 'video') {
            $query->where('mime_type', 'like', 'video/%');
        }

        $tagStatus = $filters['tag_status'] ?? null;
        if ($tagStatus === 'suggested') {
            $query->whereHas('mediaTags', fn (Builder $q) => $q->where('status', 'suggested'));
        } elseif ($tagStatus === 'confirmed') {
            $query->whereHas('mediaTags', fn (Builder $q) => $q->where('status', 'confirmed'))
                ->whereDoesntHave('mediaTags', fn (Builder $q) => $q->where('status', 'suggested'));
        } elseif ($tagStatus === 'untagged') {
            $query->whereDoesntHave('mediaTags');
        }

        return $query->orderByDesc('taken_at')->orderByDesc('id');
    }
}
