<?php

declare(strict_types=1);

namespace App\Services\Tagging;

use App\Models\Media;
use App\Models\MediaTag;
use Illuminate\Support\Facades\DB;

final class MediaTagWriter
{
    /**
     * @param  array{
     *     caption: string|null,
     *     visible_text: string|null,
     *     tags: list<array{category: string, value: string, confidence: float, tag_id: int}>,
     *     new_tag_suggestions: list<array{category: string, value: string, reason: string|null}>,
     *     rejected: list<array{category: string, value: string, reason: string}>
     * }  $validated
     * @param  array<string, mixed>  $raw
     */
    public function writeAiResult(
        Media $media,
        array $validated,
        array $raw,
        string $promptVersion,
        float $confidenceThreshold,
    ): void {
        DB::transaction(function () use ($media, $validated, $raw, $promptVersion, $confidenceThreshold): void {
            $caption = $validated['caption'];
            if (filled($validated['visible_text'])) {
                $caption = trim(($caption ?? '')."\n\nVisible text: ".$validated['visible_text']);
            }

            $media->forceFill([
                'caption' => $caption,
                'ai_raw' => [
                    'response' => $raw,
                    'validated' => $validated,
                ],
                'prompt_version' => $promptVersion,
            ])->save();

            foreach ($validated['tags'] as $tag) {
                $existing = MediaTag::query()
                    ->where('media_id', $media->id)
                    ->where('tag_id', $tag['tag_id'])
                    ->first();

                if ($existing !== null && $existing->isProtectedFromAiOverwrite()) {
                    continue;
                }

                $status = $tag['confidence'] >= $confidenceThreshold
                    ? MediaTag::STATUS_CONFIRMED
                    : MediaTag::STATUS_SUGGESTED;

                MediaTag::query()->updateOrCreate(
                    [
                        'media_id' => $media->id,
                        'tag_id' => $tag['tag_id'],
                    ],
                    [
                        'source' => MediaTag::SOURCE_AI,
                        'confidence' => $tag['confidence'],
                        'status' => $status,
                        'by_user' => null,
                    ],
                );
            }
        });
    }
}
