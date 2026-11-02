<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\TagMedia;
use App\Models\Media;
use Illuminate\Console\Command;

class MediaTagCommand extends Command
{
    protected $signature = 'media:tag
                            {--limit= : Max number of photos to queue}
                            {--id= : Tag a single media id}
                            {--force : Re-tag even if caption/prompt_version already set}
                            {--sync : Run jobs inline instead of queueing}';

    protected $description = 'Queue AI tagging jobs for photos that have not been tagged yet';

    public function handle(): int
    {
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

        $query = Media::query()
            ->where('status', Media::STATUS_ACTIVE)
            ->where('mime_type', 'like', 'image/%')
            ->orderBy('id');

        if (! $this->option('force')) {
            $query->where(function ($q): void {
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

    private function dispatchOne(Media $media): void
    {
        if ($this->option('sync')) {
            TagMedia::dispatchSync($media->id);

            return;
        }

        TagMedia::dispatch($media->id);
    }
}
