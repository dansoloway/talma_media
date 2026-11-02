<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $category_id
 * @property string $value
 * @property string $label_en
 * @property string $label_he
 * @property array<int, string>|null $synonyms
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'category_id',
    'value',
    'label_en',
    'label_he',
    'synonyms',
])]
class Tag extends Model
{
    protected function casts(): array
    {
        return [
            'synonyms' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TagCategory::class, 'category_id');
    }

    public function mediaTags(): HasMany
    {
        return $this->hasMany(MediaTag::class);
    }
}
