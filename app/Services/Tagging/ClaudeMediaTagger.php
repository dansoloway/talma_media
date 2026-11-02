<?php

declare(strict_types=1);

namespace App\Services\Tagging;

use App\Models\Media;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\ValueObjects\Media\Image;
use RuntimeException;

final class ClaudeMediaTagger
{
    public function __construct(private readonly Taxonomy $taxonomy) {}

    /**
     * @return array{
     *     structured: array<string, mixed>,
     *     model: string,
     *     prompt_version: string,
     *     input_tokens: int|null,
     *     output_tokens: int|null
     * }
     */
    public function tag(Media $media, string $imageBytes): array
    {
        $model = (string) config('media.ai.model');
        $promptVersion = (string) config('media.ai.prompt_version');
        $provider = (string) config('media.ai.provider', 'anthropic');
        $timeout = (int) config('media.ai.timeout', 90);

        $response = Prism::structured()
            ->using($this->providerEnum($provider), $model)
            ->withSchema($this->schema())
            ->withSystemPrompt($this->systemPrompt())
            ->withPrompt(
                $this->userPrompt($media),
                [Image::fromRawContent($imageBytes, 'image/jpeg')->as('photo.jpg')],
            )
            ->withClientOptions(['timeout' => $timeout])
            ->asStructured();

        $structured = $response->structured;
        if (! is_array($structured)) {
            throw new RuntimeException('Claude returned empty structured output for media #'.$media->id);
        }

        return [
            'structured' => $structured,
            'model' => $model,
            'prompt_version' => $promptVersion,
            'input_tokens' => $response->usage->promptTokens ?? null,
            'output_tokens' => $response->usage->completionTokens ?? null,
        ];
    }

    private function providerEnum(string $provider): Provider
    {
        return match ($provider) {
            'anthropic' => Provider::Anthropic,
            default => throw new RuntimeException('Unsupported media AI provider: '.$provider),
        };
    }

    private function schema(): ObjectSchema
    {
        $tagSchema = new ObjectSchema(
            name: 'tag',
            description: 'A taxonomy tag with confidence',
            properties: [
                new StringSchema('category', 'Taxonomy category key'),
                new StringSchema('value', 'Allowed tag value for that category'),
                new NumberSchema('confidence', 'Confidence from 0 to 1'),
            ],
            requiredFields: ['category', 'value', 'confidence'],
        );

        $suggestionSchema = new ObjectSchema(
            name: 'new_tag_suggestion',
            description: 'A value outside the taxonomy that should be reviewed',
            properties: [
                new StringSchema('category', 'Suggested category key'),
                new StringSchema('value', 'Suggested new tag value'),
                new StringSchema('reason', 'Why this tag may be useful'),
            ],
            requiredFields: ['category', 'value'],
        );

        return new ObjectSchema(
            name: 'media_tags',
            description: 'Caption and taxonomy tags for a TALMA photo',
            properties: [
                new StringSchema('caption', 'One or two sentences describing the photo'),
                new StringSchema('visible_text', 'Readable text on banners or signs, or empty string'),
                new ArraySchema(
                    name: 'tags',
                    description: 'Tags chosen only from the allowed taxonomy',
                    items: $tagSchema,
                ),
                new ArraySchema(
                    name: 'new_tag_suggestions',
                    description: 'Suggested tags outside the taxonomy',
                    items: $suggestionSchema,
                ),
            ],
            requiredFields: ['caption', 'tags'],
        );
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You tag photos for TALMA, an education nonprofit. Return structured JSON only.

Privacy rules (mandatory):
- Describe roles and groups only (students, teachers, adults, children, group size).
- Never name or identify individuals.
- Never invent personal details.

Tagging rules:
- Choose tag values only from the provided taxonomy.
- Prefer setting type (classroom, outdoor, etc.) over city/location — location comes from folder/GPS elsewhere.
- Do not guess program or event names unless clearly visible as text; put uncertain new values in new_tag_suggestions.
- Confidence under 0.7 means you are unsure.
- People role tags (students vs teachers) are often uncertain; keep confidence honest.
PROMPT;
    }

    private function userPrompt(Media $media): string
    {
        $allowed = json_encode($this->taxonomy->aiAllowedValues(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $folder = $media->folder;

        $context = [
            'filename' => $media->name,
            'mime_type' => $media->mime_type,
            'taken_at' => $media->taken_at?->toIso8601String(),
            'taken_at_source' => $media->taken_at_source,
            'folder_path' => $folder?->path,
            'folder_year' => $folder?->year,
            'folder_program' => $folder?->program,
            'folder_place' => $folder?->place,
            'lat' => $media->lat,
            'lng' => $media->lng,
        ];

        $contextJson = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Analyze this photo and return tags from the taxonomy.

Known context (already extracted; fill gaps, do not contradict strong signals):
{$contextJson}

Allowed taxonomy values by category:
{$allowed}
PROMPT;
    }
}
