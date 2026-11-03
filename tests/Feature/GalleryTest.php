<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\MediaTag;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\TagTaxonomySeeder;
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
