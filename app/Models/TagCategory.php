<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property string $label_en
 * @property string $label_he
 * @property bool $multi
 * @property bool $ai_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'key',
    'label_en',
    'label_he',
    'multi',
    'ai_enabled',
])]
class TagCategory extends Model
{
    protected function casts(): array
    {
        return [
            'multi' => 'boolean',
            'ai_enabled' => 'boolean',
        ];
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class, 'category_id');
    }
}
