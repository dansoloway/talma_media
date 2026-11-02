<?php

declare(strict_types=1);

namespace App\Services\Tagging;

use App\Models\Tag;
use App\Models\TagCategory;
use Illuminate\Support\Collection;

final class Taxonomy
{
    /** @var Collection<string, TagCategory>|null */
    private ?Collection $categoriesByKey = null;

    /** @var Collection<string, Tag>|null */
    private ?Collection $tagsByCategoryValue = null;

    /**
     * @return Collection<string, TagCategory>
     */
    public function categories(): Collection
    {
        return $this->categoriesByKey ??= TagCategory::query()
            ->with('tags')
            ->get()
            ->keyBy('key');
    }

    /**
     * @return Collection<int, Tag>
     */
    public function aiEnabledTags(): Collection
    {
        return $this->categories()
            ->filter(fn (TagCategory $category): bool => $category->ai_enabled)
            ->flatMap(fn (TagCategory $category) => $category->tags);
    }

    public function findTag(string $categoryKey, string $value): ?Tag
    {
        $key = $categoryKey.'|'.$value;

        return $this->tagIndex()->get($key);
    }

    public function resolveTag(string $categoryKey, string $value): ?Tag
    {
        $direct = $this->findTag($categoryKey, $value);
        if ($direct !== null) {
            return $direct;
        }

        $category = $this->categories()->get($categoryKey);
        if ($category === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($value));

        foreach ($category->tags as $tag) {
            if (mb_strtolower($tag->value) === $normalized || mb_strtolower($tag->label_en) === $normalized) {
                return $tag;
            }

            foreach ($tag->synonyms ?? [] as $synonym) {
                if (mb_strtolower((string) $synonym) === $normalized) {
                    return $tag;
                }
            }
        }

        return null;
    }

    /**
     * Taxonomy values the AI may choose from, grouped by category key.
     *
     * @return array<string, list<string>>
     */
    public function aiAllowedValues(): array
    {
        $out = [];

        foreach ($this->categories() as $category) {
            if (! $category->ai_enabled) {
                continue;
            }

            $out[$category->key] = $category->tags->pluck('value')->values()->all();
        }

        return $out;
    }

    /**
     * @return Collection<string, Tag>
     */
    private function tagIndex(): Collection
    {
        if ($this->tagsByCategoryValue !== null) {
            return $this->tagsByCategoryValue;
        }

        $index = collect();

        foreach ($this->categories() as $category) {
            foreach ($category->tags as $tag) {
                $index->put($category->key.'|'.$tag->value, $tag);
            }
        }

        return $this->tagsByCategoryValue = $index;
    }
}
