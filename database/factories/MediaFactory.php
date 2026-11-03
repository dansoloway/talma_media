<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Folder;
use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        return [
            'drive_file_id' => 'file_'.Str::random(16),
            'name' => fake()->unique()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'folder_id' => Folder::factory(),
            'web_view_link' => 'https://drive.google.com/file/d/example/view',
            'thumb_path' => null,
            'md5' => md5(Str::random(8)),
            'width' => 1200,
            'height' => 800,
            'taken_at' => now()->subDays(fake()->numberBetween(1, 100)),
            'taken_at_source' => Media::TAKEN_AT_CAMERA,
            'status' => Media::STATUS_ACTIVE,
            'caption' => fake()->sentence(),
        ];
    }
}
