<?php

use App\Models\MediaTag;
use App\Services\Tagging\ManualTagService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Review queue')] class extends Component {
    use WithPagination;

    public function confirmTag(int $mediaTagId, ManualTagService $manualTags): void
    {
        $mediaTag = MediaTag::query()->findOrFail($mediaTagId);
        $manualTags->confirm($mediaTag, Auth::user());
        Flux::toast(variant: 'success', text: __('Tag confirmed.'));
    }

    public function rejectTag(int $mediaTagId, ManualTagService $manualTags): void
    {
        $mediaTag = MediaTag::query()->findOrFail($mediaTagId);
        $manualTags->reject($mediaTag, Auth::user());
        Flux::toast(variant: 'success', text: __('Tag rejected.'));
    }

    #[Computed]
    public function suggestions()
    {
        return MediaTag::query()
            ->with(['media', 'tag.category'])
            ->where('status', MediaTag::STATUS_SUGGESTED)
            ->where('source', MediaTag::SOURCE_AI)
            ->latest()
            ->paginate(30);
    }

    #[Computed]
    public function newTagSuggestions()
    {
        return \App\Models\Media::query()
            ->whereNotNull('ai_raw')
            ->latest('updated_at')
            ->limit(50)
            ->get()
            ->flatMap(function ($media) {
                $suggestions = data_get($media->ai_raw, 'validated.new_tag_suggestions', data_get($media->ai_raw, 'response.new_tag_suggestions', []));

                return collect($suggestions)->map(fn ($suggestion) => [
                    'media' => $media,
                    'category' => $suggestion['category'] ?? '',
                    'value' => $suggestion['value'] ?? '',
                    'reason' => $suggestion['reason'] ?? null,
                ]);
            })
            ->filter(fn ($row) => filled($row['value']))
            ->values();
    }
}; ?>

<div class="space-y-8">
    <div>
        <flux:heading size="xl">{{ __('Review queue') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Confirm or reject low-confidence AI tags. Anyone on staff can review.') }}</flux:text>
    </div>

    <section class="space-y-3">
        <flux:heading size="lg">{{ __('Suggested tags') }}</flux:heading>

        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-900">
                    <tr>
                        <th class="px-4 py-3 text-start font-medium">{{ __('Photo') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ __('Tag') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ __('Confidence') }}</th>
                        <th class="px-4 py-3 text-end font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->suggestions as $mediaTag)
                        <tr wire:key="review-{{ $mediaTag->id }}">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    @if ($mediaTag->media?->thumb_path)
                                        <img src="{{ route('media.thumbnail', $mediaTag->media) }}" alt="" class="size-12 rounded object-cover" />
                                    @endif
                                    <div>
                                        <div class="font-medium">{{ $mediaTag->media?->name }}</div>
                                        <div class="text-xs text-zinc-500">#{{ $mediaTag->media_id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                {{ $mediaTag->tag->category->label_en }}: {{ $mediaTag->tag->label_en }}
                            </td>
                            <td class="px-4 py-3">
                                {{ $mediaTag->confidence !== null ? number_format((float) $mediaTag->confidence * 100).'%' : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" variant="primary" wire:click="confirmTag({{ $mediaTag->id }})">{{ __('Confirm') }}</flux:button>
                                    <flux:button size="sm" variant="danger" wire:click="rejectTag({{ $mediaTag->id }})">{{ __('Reject') }}</flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-zinc-500">{{ __('No suggested tags waiting.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $this->suggestions->links() }}</div>
    </section>

    <section class="space-y-3">
        <flux:heading size="lg">{{ __('New tag suggestions') }}</flux:heading>
        <flux:text>{{ __('Values the model proposed outside the taxonomy. Add them under Admin when useful.') }}</flux:text>

        <div class="space-y-2">
            @forelse ($this->newTagSuggestions as $index => $row)
                <div wire:key="suggestion-{{ $index }}" class="rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <div class="font-medium">{{ $row['category'] }}: {{ $row['value'] }}</div>
                    <div class="text-sm text-zinc-500">
                        {{ $row['media']->name }}
                        @if ($row['reason'])
                            · {{ $row['reason'] }}
                        @endif
                    </div>
                </div>
            @empty
                <flux:text>{{ __('No out-of-taxonomy suggestions right now.') }}</flux:text>
            @endforelse
        </div>
    </section>
</div>
