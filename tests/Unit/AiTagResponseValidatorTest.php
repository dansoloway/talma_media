<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\MediaTag;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Services\Tagging\AiTagResponseValidator;
use App\Services\Tagging\MediaTagWriter;
use Database\Seeders\TagTaxonomySeeder;

beforeEach(function () {
    $this->seed(TagTaxonomySeeder::class);
});

it('accepts taxonomy tags and rejects unknown values', function () {
    $validated = app(AiTagResponseValidator::class)->validate([
        'caption' => 'Students in a classroom.',
        'visible_text' => 'Welcome',
        'tags' => [
            ['category' => 'people', 'value' => 'students', 'confidence' => 0.9],
            ['category' => 'setting', 'value' => 'classroom', 'confidence' => 0.8],
            ['category' => 'activity', 'value' => 'not-a-real-tag', 'confidence' => 0.9],
            ['category' => 'location', 'value' => 'sf', 'confidence' => 0.9],
        ],
        'new_tag_suggestions' => [
            ['category' => 'event', 'value' => 'Graduation 2026', 'reason' => 'banner text'],
        ],
    ]);

    expect($validated['caption'])->toBe('Students in a classroom.')
        ->and($validated['tags'])->toHaveCount(2)
        ->and(collect($validated['tags'])->pluck('value')->all())->toEqualCanonicalizing(['students', 'classroom'])
        ->and($validated['rejected'])->toHaveCount(2)
        ->and($validated['new_tag_suggestions'])->toHaveCount(1);
});

it('resolves synonyms to canonical tag values', function () {
    $validated = app(AiTagResponseValidator::class)->validate([
        'caption' => 'A large group.',
        'tags' => [
            ['category' => 'people', 'value' => '20+', 'confidence' => 0.75],
        ],
    ]);

    expect($validated['tags'])->toHaveCount(1)
        ->and($validated['tags'][0]['value'])->toBe('group_20_plus');
});

it('never overwrites manual or rejected tags when writing AI results', function () {
    $media = Media::query()->create([
        'drive_file_id' => 'test-file',
        'name' => 'test.jpg',
        'mime_type' => 'image/jpeg',
        'status' => Media::STATUS_ACTIVE,
    ]);

    $peopleId = TagCategory::query()->where('key', 'people')->value('id');
    $settingId = TagCategory::query()->where('key', 'setting')->value('id');

    $students = Tag::query()
        ->where('category_id', $peopleId)
        ->where('value', 'students')
        ->firstOrFail();

    $classroom = Tag::query()
        ->where('category_id', $settingId)
        ->where('value', 'classroom')
        ->firstOrFail();

    MediaTag::query()->create([
        'media_id' => $media->id,
        'tag_id' => $students->id,
        'source' => MediaTag::SOURCE_MANUAL,
        'confidence' => 1,
        'status' => MediaTag::STATUS_CONFIRMED,
    ]);

    MediaTag::query()->create([
        'media_id' => $media->id,
        'tag_id' => $classroom->id,
        'source' => MediaTag::SOURCE_AI,
        'confidence' => 0.4,
        'status' => MediaTag::STATUS_REJECTED,
    ]);

    $validated = app(AiTagResponseValidator::class)->validate([
        'caption' => 'Updated caption',
        'tags' => [
            ['category' => 'people', 'value' => 'students', 'confidence' => 0.95],
            ['category' => 'setting', 'value' => 'classroom', 'confidence' => 0.95],
            ['category' => 'activity', 'value' => 'lesson', 'confidence' => 0.95],
        ],
    ]);

    app(MediaTagWriter::class)->writeAiResult(
        media: $media,
        validated: $validated,
        raw: ['tags' => []],
        promptVersion: 'v1',
        confidenceThreshold: 0.7,
    );

    $media->refresh();

    expect($media->caption)->toBe('Updated caption')
        ->and($media->mediaTags()->count())->toBe(3);

    $manual = $media->mediaTags()->where('tag_id', $students->id)->first();
    $rejected = $media->mediaTags()->where('tag_id', $classroom->id)->first();
    $lesson = $media->mediaTags()->whereHas('tag', fn ($q) => $q->where('value', 'lesson'))->first();

    expect($manual?->source)->toBe(MediaTag::SOURCE_MANUAL)
        ->and($rejected?->status)->toBe(MediaTag::STATUS_REJECTED)
        ->and($lesson?->source)->toBe(MediaTag::SOURCE_AI)
        ->and($lesson?->status)->toBe(MediaTag::STATUS_CONFIRMED);
});
