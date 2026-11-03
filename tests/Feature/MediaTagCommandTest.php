<?php

declare(strict_types=1);

use App\Jobs\TagMedia;
use App\Models\Media;
use App\Models\MediaTag;
use App\Models\Tag;
use App\Services\Tagging\TaggingPreview;
use Database\Seeders\TagTaxonomySeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(TagTaxonomySeeder::class);
});

it('queues only stale prompt versions with --stale', function () {
    Queue::fake();
    config(['media.ai.prompt_version' => 'v2']);

    $stale = Media::factory()->create([
        'prompt_version' => 'v1',
        'caption' => 'Old caption',
    ]);
    $current = Media::factory()->create([
        'prompt_version' => 'v2',
        'caption' => 'Current caption',
    ]);
    $untagged = Media::factory()->create([
        'prompt_version' => null,
        'caption' => null,
    ]);

    $this->artisan('media:tag', ['--stale' => true])
        ->assertSuccessful();

    Queue::assertPushed(TagMedia::class, fn (TagMedia $job): bool => $job->mediaId === $stale->id);
    Queue::assertPushed(TagMedia::class, fn (TagMedia $job): bool => $job->mediaId === $untagged->id);
    Queue::assertNotPushed(TagMedia::class, fn (TagMedia $job): bool => $job->mediaId === $current->id);
});

it('dry-runs without writing tags', function () {
    $media = Media::factory()->create([
        'prompt_version' => 'v1',
        'caption' => 'Before caption',
    ]);

    $students = Tag::query()->where('value', 'students')->firstOrFail();

    MediaTag::query()->create([
        'media_id' => $media->id,
        'tag_id' => $students->id,
        'source' => MediaTag::SOURCE_AI,
        'confidence' => 0.8,
        'status' => MediaTag::STATUS_CONFIRMED,
    ]);

    $this->mock(TaggingPreview::class, function ($mock) use ($media): void {
        $mock->shouldReceive('preview')
            ->once()
            ->andReturn([
                'media' => $media,
                'structured' => [
                    'caption' => 'Proposed caption',
                    'tags' => [
                        ['category' => 'activity', 'value' => 'lesson', 'confidence' => 0.9],
                    ],
                ],
                'validated' => [
                    'caption' => 'Proposed caption',
                    'visible_text' => null,
                    'tags' => [
                        [
                            'category' => 'activity',
                            'value' => 'lesson',
                            'confidence' => 0.9,
                            'tag_id' => Tag::query()->where('value', 'lesson')->value('id'),
                        ],
                    ],
                    'new_tag_suggestions' => [],
                    'rejected' => [],
                ],
                'model' => 'test-model',
                'prompt_version' => 'v2',
                'input_tokens' => 10,
                'output_tokens' => 5,
            ]);
    });

    $this->artisan('media:tag', [
        '--dry-run' => true,
        '--id' => $media->id,
    ])->assertSuccessful();

    $media->refresh();

    expect($media->caption)->toBe('Before caption')
        ->and($media->prompt_version)->toBe('v1')
        ->and($media->mediaTags()->count())->toBe(1)
        ->and($media->mediaTags()->first()?->tag_id)->toBe($students->id);
});
