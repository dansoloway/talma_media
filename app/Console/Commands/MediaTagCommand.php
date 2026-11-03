<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\TagMedia;
use App\Models\Media;
use App\Models\MediaTag;
use App\Services\Tagging\TaggingPreview;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class MediaTagCommand extends Command
{
    /**
     * Tune loop: edit prompt/taxonomy in code, bump MEDIA_AI_PROMPT_VERSION in .env,
     * then `php artisan media:tag --stale --limit=20` (or `--dry-run --id=` to compare first).
     */
    protected $signature = 'media:tag
                            {--limit= : Max number of photos to queue}
                            {--id= : Tag a single media id}
                            {--force : Re-tag even if caption/prompt_version already set}
                            {--stale : Only photos with missing or outdated prompt_version}
                            {--dry-run : Preview AI tags without writing (requires --id, or --limit=1)}
                            {--sync : Run jobs inline instead of queueing}';

    protected $description = 'Queue AI tagging jobs for photos (untagged, --force, or --stale). Use --dry-run to compare before writing.';

    public function handle(TaggingPreview $taggingPreview): int
    {
        if ($this->option('dry-run')) {
            return $this->handleDryRun($taggingPreview);
        }

        if ($this->option('id')) {
            $media = Media::query()->findOrFail((int) $this->option('id'));

            if (! $media->isImage()) {
                $this->error('Media #'.$media->id.' is not an image.');

                return self::FAILURE;
            }

            $this->dispatchOne($media);
            $this->info('Tagged / queued media #'.$media->id);

            return self::SUCCESS;
        }

        $query = $this->baseImageQuery();

        if ($this->option('stale')) {
            $this->applyStaleConstraint($query);
        } elseif (! $this->option('force')) {
            $query->where(function (Builder $q): void {
                $q->whereNull('prompt_version')->orWhereNull('caption');
            });
        }

        if ($this->option('limit')) {
            $query->limit((int) $this->option('limit'));
        }

        $count = 0;
        $query->each(function (Media $media) use (&$count): void {
            $this->dispatchOne($media);
            $count++;
        });

        $this->info(($this->option('sync') ? 'Tagged' : 'Queued')." {$count} photo(s).");

        if (! $this->option('sync') && $count > 0) {
            $this->comment('Run `php artisan queue:work` to process TagMedia jobs.');
        }

        return self::SUCCESS;
    }

    private function handleDryRun(TaggingPreview $taggingPreview): int
    {
        $media = $this->resolveDryRunMedia();

        if ($media === null) {
            return self::FAILURE;
        }

        if (! $media->isImage()) {
            $this->error('Media #'.$media->id.' is not an image.');

            return self::FAILURE;
        }

        $media->load(['mediaTags.tag.category', 'folder']);

        $this->info('Dry-run for media #'.$media->id.' ('.$media->name.')');
        $this->line('Current prompt_version: '.($media->prompt_version ?? 'null'));
        $this->line('Current caption: '.($media->caption ?? '(none)'));
        $this->newLine();

        $this->table(
            ['Side', 'Category', 'Value', 'Status', 'Source', 'Confidence'],
            $media->mediaTags->map(fn (MediaTag $mediaTag): array => [
                'current',
                $mediaTag->tag->category->key,
                $mediaTag->tag->value,
                $mediaTag->status,
                $mediaTag->source,
                $mediaTag->confidence !== null ? number_format((float) $mediaTag->confidence, 2) : '—',
            ])->all(),
        );

        $this->comment('Calling AI (no DB write)…');

        $preview = $taggingPreview->preview($media);
        $validated = $preview['validated'];

        $this->newLine();
        $this->info('Proposed prompt_version: '.$preview['prompt_version'].' · model: '.$preview['model']);
        $this->line('Proposed caption: '.($validated['caption'] ?? '(none)'));
        if (filled($validated['visible_text'] ?? null)) {
            $this->line('Visible text: '.$validated['visible_text']);
        }

        $this->table(
            ['Side', 'Category', 'Value', 'Status', 'Source', 'Confidence'],
            collect($validated['tags'])->map(function (array $tag): array {
                $threshold = (float) config('media.ai.confidence_threshold', 0.7);
                $status = $tag['confidence'] >= $threshold ? MediaTag::STATUS_CONFIRMED : MediaTag::STATUS_SUGGESTED;

                return [
                    'proposed',
                    $tag['category'],
                    $tag['value'],
                    $status,
                    MediaTag::SOURCE_AI,
                    number_format($tag['confidence'], 2),
                ];
            })->all(),
        );

        if ($validated['new_tag_suggestions'] !== []) {
            $this->newLine();
            $this->warn('New tag suggestions (outside taxonomy):');
            foreach ($validated['new_tag_suggestions'] as $suggestion) {
                $this->line('- '.$suggestion['category'].': '.$suggestion['value']
                    .($suggestion['reason'] ? ' ('.$suggestion['reason'].')' : ''));
            }
        }

        if ($validated['rejected'] !== []) {
            $this->newLine();
            $this->warn('Rejected by validator: '.count($validated['rejected']));
        }

        $this->newLine();
        $this->comment('No changes were written. Re-run without --dry-run to apply.');

        return self::SUCCESS;
    }

    private function resolveDryRunMedia(): ?Media
    {
        if ($this->option('id')) {
            return Media::query()->findOrFail((int) $this->option('id'));
        }

        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : 1;
        if ($limit !== 1) {
            $this->error('--dry-run without --id requires --limit=1 (got '.$limit.').');

            return null;
        }

        $query = $this->baseImageQuery()->orderBy('id');

        if ($this->option('stale')) {
            $this->applyStaleConstraint($query);
        } elseif (! $this->option('force')) {
            $query->where(function (Builder $q): void {
                $q->whereNull('prompt_version')->orWhereNull('caption');
            });
        }

        $media = $query->first();

        if ($media === null) {
            $this->error('No matching media found for dry-run.');

            return null;
        }

        return $media;
    }

    /**
     * @return Builder<Media>
     */
    private function baseImageQuery(): Builder
    {
        return Media::query()
            ->where('status', Media::STATUS_ACTIVE)
            ->where('mime_type', 'like', 'image/%')
            ->orderBy('id');
    }

    /**
     * @param  Builder<Media>  $query
     */
    private function applyStaleConstraint(Builder $query): void
    {
        $currentVersion = (string) config('media.ai.prompt_version');

        $query->where(function (Builder $q) use ($currentVersion): void {
            $q->whereNull('prompt_version')
                ->orWhere('prompt_version', '!=', $currentVersion);
        });
    }

    private function dispatchOne(Media $media): void
    {
        if ($this->option('sync')) {
            TagMedia::dispatchSync($media->id);

            return;
        }

        TagMedia::dispatch($media->id);
    }
}
