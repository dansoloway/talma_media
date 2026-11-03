<?php

declare(strict_types=1);

namespace App\Services\Tagging;

use App\Models\Media;
use App\Services\GoogleDrive\DriveSyncService;

class TaggingPreview
{
    public function __construct(
        private readonly DriveSyncService $driveSync,
        private readonly ClaudeMediaTagger $claudeMediaTagger,
        private readonly AiTagResponseValidator $validator,
    ) {}

    /**
     * Run AI tagging without writing to the database.
     *
     * @return array{
     *     media: Media,
     *     structured: array<string, mixed>,
     *     validated: array{
     *         caption: string|null,
     *         visible_text: string|null,
     *         tags: list<array{category: string, value: string, confidence: float, tag_id: int}>,
     *         new_tag_suggestions: list<array{category: string, value: string, reason: string|null}>,
     *         rejected: list<array{category: string, value: string, reason: string}>
     *     },
     *     model: string,
     *     prompt_version: string,
     *     input_tokens: int|null,
     *     output_tokens: int|null
     * }
     */
    public function preview(Media $media): array
    {
        $imageBytes = $this->driveSync->downloadThumbnailBytes(
            $media->drive_file_id,
            (int) config('media.ai.vision_max_edge', 1600),
        );

        $result = $this->claudeMediaTagger->tag($media, $imageBytes);
        $validated = $this->validator->validate($result['structured']);

        return [
            'media' => $media,
            'structured' => $result['structured'],
            'validated' => $validated,
            'model' => $result['model'],
            'prompt_version' => $result['prompt_version'],
            'input_tokens' => $result['input_tokens'],
            'output_tokens' => $result['output_tokens'],
        ];
    }
}
