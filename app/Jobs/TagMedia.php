<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Media;
use App\Models\TagRun;
use App\Services\Tagging\FolderContextTagger;
use App\Services\Tagging\MediaTagWriter;
use App\Services\Tagging\TaggingPreview;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Throwable;

class TagMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 60, 120, 300, 600];

    public int $timeout = 120;

    public function __construct(public int $mediaId) {}

    public function handle(
        FolderContextTagger $folderContextTagger,
        TaggingPreview $taggingPreview,
        MediaTagWriter $writer,
    ): void {
        $media = Media::query()->with('folder')->find($this->mediaId);

        if ($media === null || $media->status === Media::STATUS_TRASHED || ! $media->isImage()) {
            return;
        }

        $folderContextTagger->apply($media);

        $rateKey = 'media-ai-tagging';
        $maxAttempts = max(1, (int) config('media.ai.rate_limit_per_minute', 20));

        if (RateLimiter::tooManyAttempts($rateKey, $maxAttempts)) {
            $this->release(RateLimiter::availableIn($rateKey) ?: 30);

            return;
        }

        RateLimiter::hit($rateKey, 60);

        $tagRun = TagRun::query()->create([
            'media_id' => $media->id,
            'model' => (string) config('media.ai.model'),
            'prompt_version' => (string) config('media.ai.prompt_version'),
            'status' => TagRun::STATUS_PENDING,
        ]);

        try {
            $preview = $taggingPreview->preview($media);

            $writer->writeAiResult(
                media: $media,
                validated: $preview['validated'],
                raw: $preview['structured'],
                promptVersion: $preview['prompt_version'],
                confidenceThreshold: (float) config('media.ai.confidence_threshold', 0.7),
            );

            $tagRun->forceFill([
                'status' => TagRun::STATUS_SUCCEEDED,
                'model' => $preview['model'],
                'prompt_version' => $preview['prompt_version'],
                'input_tokens' => $preview['input_tokens'],
                'output_tokens' => $preview['output_tokens'],
                'error' => null,
            ])->save();
        } catch (PrismRateLimitedException $e) {
            $tagRun->forceFill([
                'status' => TagRun::STATUS_FAILED,
                'error' => 'rate_limited: '.$e->getMessage(),
            ])->save();

            $this->release(60);

            return;
        } catch (Throwable $e) {
            $tagRun->forceFill([
                'status' => TagRun::STATUS_FAILED,
                'error' => $e->getMessage(),
            ])->save();

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('TagMedia failed', [
            'media_id' => $this->mediaId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
