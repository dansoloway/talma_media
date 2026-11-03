<?php

declare(strict_types=1);

use App\Jobs\TagMedia;
use App\Models\Media;
use App\Models\MediaTag;
use App\Models\Tag;
use App\Models\User;
use App\Services\Media\MediaFilterQuery;
use Database\Seeders\TagTaxonomySeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(TagTaxonomySeeder::class);
});

it('redirects guests from the gallery to login', function () {
    $this->get(route('gallery'))->assertRedirect(route('login'));
});

it('shows media in the gallery for authenticated staff', function () {
    $user = User::factory()->create();
    $media = Media::factory()->create(['name' => 'chanukah-party.jpg', 'caption' => 'Kids lighting candles']);

    $this->actingAs($user)
        ->get(route('gallery'))
        ->assertOk()
        ->assertSee('chanukah-party.jpg');

    Livewire::actingAs($user)
        ->test('pages::media.gallery')
        ->set('search', 'candles')
        ->assertSee('chanukah-party.jpg');
});

it('lets staff confirm and reject tags from the gallery', function () {
    $user = User::factory()->create();
    $media = Media::factory()->create();
    $tag = Tag::query()->where('value', 'students')->firstOrFail();

    $mediaTag = MediaTag::query()->create([
        'media_id' => $media->id,
        'tag_id' => $tag->id,
        'source' => MediaTag::SOURCE_AI,
        'confidence' => 0.55,
        'status' => MediaTag::STATUS_SUGGESTED,
    ]);

    Livewire::actingAs($user)
        ->test('pages::media.gallery')
        ->call('openMedia', $media->id)
        ->call('confirmTag', $mediaTag->id)
        ->assertSet('showDetail', true);

    expect($mediaTag->fresh()->status)->toBe(MediaTag::STATUS_CONFIRMED)
        ->and($mediaTag->fresh()->source)->toBe(MediaTag::SOURCE_MANUAL)
        ->and($mediaTag->fresh()->by_user)->toBe($user->id);
});

it('serves thumbnails only to authenticated users', function () {
    $media = Media::factory()->create(['thumb_path' => 'example.jpg']);
    $disk = Storage::fake('thumbnails');
    config(['media.thumbnails.disk' => 'thumbnails']);
    $disk->put('example.jpg', 'fake-image-bytes');

    $this->get(route('media.thumbnail', $media))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('media.thumbnail', $media))
        ->assertOk();
});

it('queues a re-tag job from the gallery detail', function () {
    Queue::fake();
    $user = User::factory()->create();
    $media = Media::factory()->create(['mime_type' => 'image/jpeg']);

    Livewire::actingAs($user)
        ->test('pages::media.gallery')
        ->call('openMedia', $media->id)
        ->call('retagSelected')
        ->assertSet('retagQueued', true);

    Queue::assertPushed(TagMedia::class, fn (TagMedia $job): bool => $job->mediaId === $media->id);
});

it('filters outdated and untagged AI photos', function () {
    config(['media.ai.prompt_version' => 'v2']);

    $stale = Media::factory()->create([
        'name' => 'stale.jpg',
        'prompt_version' => 'v1',
        'mime_type' => 'image/jpeg',
    ]);
    $current = Media::factory()->create([
        'name' => 'current.jpg',
        'prompt_version' => 'v2',
        'mime_type' => 'image/jpeg',
    ]);
    $untagged = Media::factory()->create([
        'name' => 'untagged.jpg',
        'prompt_version' => null,
        'mime_type' => 'image/jpeg',
    ]);

    $ids = app(MediaFilterQuery::class)
        ->build(['tag_status' => 'stale'])
        ->pluck('id')
        ->all();

    expect($ids)->toContain($stale->id, $untagged->id)
        ->and($ids)->not->toContain($current->id);

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::media.gallery')
        ->set('tagStatus', 'stale')
        ->assertSee('stale.jpg')
        ->assertSee('untagged.jpg')
        ->assertDontSee('current.jpg');
});
