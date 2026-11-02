<?php

declare(strict_types=1);

namespace App\Services\Tagging;

final class AiTagResponseValidator
{
    public function __construct(private readonly Taxonomy $taxonomy) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     caption: string|null,
     *     visible_text: string|null,
     *     tags: list<array{category: string, value: string, confidence: float, tag_id: int}>,
     *     new_tag_suggestions: list<array{category: string, value: string, reason: string|null}>,
     *     rejected: list<array{category: string, value: string, reason: string}>
     * }
     */
    public function validate(array $payload): array
    {
        $caption = isset($payload['caption']) && is_string($payload['caption'])
            ? trim($payload['caption'])
            : null;

        $visibleText = isset($payload['visible_text']) && is_string($payload['visible_text'])
            ? trim($payload['visible_text'])
            : null;

        $accepted = [];
        $rejected = [];

        $rawTags = $payload['tags'] ?? [];
        if (! is_array($rawTags)) {
            $rawTags = [];
        }

        foreach ($rawTags as $rawTag) {
            if (! is_array($rawTag)) {
                continue;
            }

            $category = isset($rawTag['category']) ? (string) $rawTag['category'] : '';
            $value = isset($rawTag['value']) ? (string) $rawTag['value'] : '';
            $confidence = isset($rawTag['confidence']) ? (float) $rawTag['confidence'] : 0.0;
            $confidence = max(0.0, min(1.0, $confidence));

            if ($category === '' || $value === '') {
                $rejected[] = [
                    'category' => $category,
                    'value' => $value,
                    'reason' => 'missing_category_or_value',
                ];

                continue;
            }

            $categoryModel = $this->taxonomy->categories()->get($category);
            if ($categoryModel === null || ! $categoryModel->ai_enabled) {
                $rejected[] = [
                    'category' => $category,
                    'value' => $value,
                    'reason' => 'category_not_ai_enabled',
                ];

                continue;
            }

            $tag = $this->taxonomy->resolveTag($category, $value);
            if ($tag === null) {
                $rejected[] = [
                    'category' => $category,
                    'value' => $value,
                    'reason' => 'unknown_tag_value',
                ];

                continue;
            }

            $accepted[] = [
                'category' => $category,
                'value' => $tag->value,
                'confidence' => $confidence,
                'tag_id' => $tag->id,
            ];
        }

        $suggestions = [];
        $rawSuggestions = $payload['new_tag_suggestions'] ?? [];
        if (is_array($rawSuggestions)) {
            foreach ($rawSuggestions as $suggestion) {
                if (! is_array($suggestion)) {
                    continue;
                }

                $category = isset($suggestion['category']) ? (string) $suggestion['category'] : '';
                $value = isset($suggestion['value']) ? (string) $suggestion['value'] : '';
                if ($category === '' || $value === '') {
                    continue;
                }

                $suggestions[] = [
                    'category' => $category,
                    'value' => $value,
                    'reason' => isset($suggestion['reason']) && is_string($suggestion['reason'])
                        ? $suggestion['reason']
                        : null,
                ];
            }
        }

        return [
            'caption' => $caption !== '' ? $caption : null,
            'visible_text' => $visibleText !== '' ? $visibleText : null,
            'tags' => $accepted,
            'new_tag_suggestions' => $suggestions,
            'rejected' => $rejected,
        ];
    }
}
