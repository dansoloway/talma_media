<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Folder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Folder>
 */
class FolderFactory extends Factory
{
    protected $model = Folder::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'drive_folder_id' => 'folder_'.Str::random(16),
            'name' => $name,
            'path' => $name,
            'parent_id' => null,
            'year' => 2026,
            'program' => 'Summer',
            'place' => 'SF',
        ];
    }
}
