<?php

use App\Models\Folder;
use App\Models\Media;
use App\Models\MediaTag;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Services\Media\MediaFilterQuery;
use App\Services\Tagging\ManualTagService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Gallery')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $dateFrom = '';

    #[Url(except: '')]
    public string $dateTo = '';

    #[Url(except: '')]
    public string $folderId = '';

    #[Url(except: '')]
    public string $program = '';

    #[Url(except: '')]
    public string $peopleTag = '';

    #[Url(except: '')]
    public string $activityTag = '';

    #[Url(except: '')]
    public string $settingTag = '';

    #[Url(except: '')]
    public string $locationTag = '';

    #[Url(except: '')]
    public string $mediaType = '';

    #[Url(except: '')]
    public string $tagStatus = '';

    public int $perPage = 24;

    public ?int $selectedMediaId = null;

    public bool $showDetail = false;

    /** @var list<int> */
    public array $selectedIds = [];

    public string $bulkTagId = '';

    public string $addTagId = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->perPage = 24;
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
        $this->perPage = 24;
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
        $this->perPage = 24;
    }

    public function updatedFolderId(): void
    {
        $this->resetPage();
        $this->perPage = 24;
    }

    public function updatedProgram(): void
    {
        $this->resetPage();
        $this->perPage = 24;
    }

    public function updatedPeopleTag(): void
    {
        $this->resetPage();
        $this->perPage = 24;
    }

    public function updatedActivityTag(): void
    {
        $this->resetPage();
        $this->perPage = 24;
    }

    public function updatedSettingTag(): void
    {
        $this->resetPage();
        $this->perPage = 24;
    }

    public function updatedLocationTag(): void
    {
        $this->resetPage();
        $this->perPage = 24;
    }

    public function updatedMediaType(): void
    {
        $this->resetPage();
        $this->perPage = 24;
    }

    public function updatedTagStatus(): void
    {
        $this->resetPage();
        $this->perPage = 24;
    }

    public function clearFilters(): void
    {
        $this->reset([
            'search', 'dateFrom', 'dateTo', 'folderId', 'program',
            'peopleTag', 'activityTag', 'settingTag', 'locationTag',
            'mediaType', 'tagStatus',
        ]);
        $this->resetPage();
        $this->perPage = 24;
    }

    public function loadMore(): void
    {
        $this->perPage += 24;
    }

    public function openMedia(int $mediaId): void
    {
        $this->selectedMediaId = $mediaId;
        $this->addTagId = '';
        $this->showDetail = true;
    }

    public function closeMedia(): void
    {
        $this->selectedMediaId = null;
        $this->showDetail = false;
    }

    public function updatedShowDetail(bool $value): void
    {
        if (! $value) {
            $this->selectedMediaId = null;
        }
    }

    public function toggleSelect(int $mediaId): void
    {
        if (in_array($mediaId, $this->selectedIds, true)) {
            $this->selectedIds = array_values(array_filter(
                $this->selectedIds,
                fn (int $id): bool => $id !== $mediaId,
            ));

            return;
        }

        $this->selectedIds[] = $mediaId;
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];
        $this->bulkTagId = '';
    }

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

    public function addTagToSelectedMedia(ManualTagService $manualTags): void
    {
        if ($this->selectedMediaId === null || $this->addTagId === '') {
            return;
        }

        $media = Media::query()->findOrFail($this->selectedMediaId);
        $tag = Tag::query()->findOrFail((int) $this->addTagId);
        $manualTags->add($media, $tag, Auth::user());
        $this->addTagId = '';
        Flux::toast(variant: 'success', text: __('Tag added.'));
    }

    public function applyBulkTag(ManualTagService $manualTags): void
    {
        if ($this->bulkTagId === '' || $this->selectedIds === []) {
            return;
        }

        $tag = Tag::query()->findOrFail((int) $this->bulkTagId);
        $count = $manualTags->bulkAdd($this->selectedIds, $tag, Auth::user());
        $this->clearSelection();
        Flux::toast(variant: 'success', text: __(':count photos tagged.', ['count' => $count]));
    }

    #[Computed]
    public function mediaItems()
    {
        return app(MediaFilterQuery::class)->build([
            'search' => $this->search,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'folder_id' => $this->folderId,
            'program' => $this->program,
            'people_tag' => $this->peopleTag,
            'activity_tag' => $this->activityTag,
            'setting_tag' => $this->settingTag,
            'location_tag' => $this->locationTag,
            'media_type' => $this->mediaType,
            'tag_status' => $this->tagStatus,
        ])->paginate($this->perPage);
    }

    #[Computed]
    public function selectedMedia(): ?Media
    {
        if ($this->selectedMediaId === null) {
            return null;
        }

        return Media::query()
            ->with(['folder', 'mediaTags.tag.category'])
            ->find($this->selectedMediaId);
    }

    #[Computed]
    public function folders()
    {
        return Folder::query()->orderBy('path')->get(['id', 'name', 'path', 'program', 'place', 'year']);
    }

    #[Computed]
    public function programs()
    {
        return Folder::query()
            ->whereNotNull('program')
            ->distinct()
            ->orderBy('program')
            ->pluck('program');
    }

    #[Computed]
    public function tagsByCategory()
    {
        return TagCategory::query()
            ->with(['tags' => fn ($q) => $q->orderBy('label_en')])
            ->orderBy('key')
            ->get()
            ->keyBy('key');
    }

    #[Computed]
    public function allTags()
    {
        return Tag::query()->with('category')->orderBy('label_en')->get();
    }
}; ?>

<div class="flex flex-col gap-4 lg:flex-row">
    <aside class="w-full shrink-0 space-y-4 lg:w-72">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('Filters') }}</flux:heading>
            <flux:button size="sm" variant="ghost" wire:click="clearFilters">{{ __('Clear') }}</flux:button>
        </div>

        <flux:input wire:model.live.debounce.400ms="search" :label="__('Search')" placeholder="{{ __('Caption, filename, tags…') }}" />

        <div class="grid grid-cols-2 gap-3">
            <flux:input type="date" wire:model.live="dateFrom" :label="__('From')" />
            <flux:input type="date" wire:model.live="dateTo" :label="__('To')" />
        </div>

        <flux:select wire:model.live="folderId" :label="__('Folder')">
            <flux:select.option value="">{{ __('Any folder') }}</flux:select.option>
            @foreach ($this->folders as $folder)
                <flux:select.option value="{{ $folder->id }}">{{ $folder->path ?? $folder->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="program" :label="__('Program')">
            <flux:select.option value="">{{ __('Any program') }}</flux:select.option>
            @foreach ($this->programs as $programOption)
                <flux:select.option value="{{ $programOption }}">{{ $programOption }}</flux:select.option>
            @endforeach
        </flux:select>

        @foreach ([
            'peopleTag' => ['people', __('People')],
            'activityTag' => ['activity', __('Activity')],
            'settingTag' => ['setting', __('Setting')],
            'locationTag' => ['location', __('Location')],
        ] as $property => [$categoryKey, $label])
            <flux:select wire:model.live="{{ $property }}" :label="$label">
                <flux:select.option value="">{{ __('Any') }}</flux:select.option>
                @foreach ($this->tagsByCategory->get($categoryKey)?->tags ?? [] as $tag)
                    <flux:select.option value="{{ $tag->value }}">{{ $tag->label_en }}</flux:select.option>
                @endforeach
            </flux:select>
        @endforeach

        <flux:select wire:model.live="mediaType" :label="__('Media type')">
            <flux:select.option value="">{{ __('Photos & videos') }}</flux:select.option>
            <flux:select.option value="image">{{ __('Photos') }}</flux:select.option>
            <flux:select.option value="video">{{ __('Videos') }}</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="tagStatus" :label="__('Tag status')">
            <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
            <flux:select.option value="suggested">{{ __('Has AI suggestions') }}</flux:select.option>
            <flux:select.option value="confirmed">{{ __('Confirmed only') }}</flux:select.option>
            <flux:select.option value="untagged">{{ __('Untagged') }}</flux:select.option>
        </flux:select>
    </aside>

    <div class="min-w-0 flex-1 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('Gallery') }}</flux:heading>
                <flux:text class="mt-1">{{ __(':count items', ['count' => $this->mediaItems->total()]) }}</flux:text>
            </div>

            @if (count($selectedIds) > 0)
                <div class="flex flex-wrap items-end gap-2 rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:text>{{ __(':count selected', ['count' => count($selectedIds)]) }}</flux:text>
                    <flux:select wire:model="bulkTagId" class="min-w-48">
                        <flux:select.option value="">{{ __('Choose tag…') }}</flux:select.option>
                        @foreach ($this->allTags as $tag)
                            <flux:select.option value="{{ $tag->id }}">{{ $tag->category->label_en }}: {{ $tag->label_en }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:button variant="primary" wire:click="applyBulkTag">{{ __('Apply tag') }}</flux:button>
                    <flux:button variant="ghost" wire:click="clearSelection">{{ __('Clear') }}</flux:button>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
            @forelse ($this->mediaItems as $item)
                <div
                    wire:key="media-{{ $item->id }}"
                    class="group relative overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900 {{ in_array($item->id, $selectedIds, true) ? 'ring-2 ring-accent' : '' }}"
                >
                    <button
                        type="button"
                        class="absolute start-2 top-2 z-10 rounded bg-white/90 px-2 py-1 text-xs font-medium shadow dark:bg-zinc-900/90"
                        wire:click.stop="toggleSelect({{ $item->id }})"
                    >
                        {{ in_array($item->id, $selectedIds, true) ? __('Selected') : __('Select') }}
                    </button>

                    <button type="button" class="block w-full text-start" wire:click="openMedia({{ $item->id }})">
                        <div class="aspect-square overflow-hidden bg-zinc-200 dark:bg-zinc-800">
                            @if ($item->thumb_path)
                                <img
                                    src="{{ route('media.thumbnail', $item) }}"
                                    alt="{{ $item->name }}"
                                    class="size-full object-cover transition group-hover:scale-[1.02]"
                                    loading="lazy"
                                />
                            @else
                                <div class="flex size-full items-center justify-center text-sm text-zinc-500">
                                    {{ $item->isVideo() ? __('Video') : __('No thumb') }}
                                </div>
                            @endif
                        </div>
                        <div class="space-y-1 p-3">
                            <div class="truncate text-sm font-medium">{{ $item->name }}</div>
                            <div class="truncate text-xs text-zinc-500">
                                {{ $item->taken_at?->toFormattedDateString() ?? __('No date') }}
                                @if ($item->folder?->place)
                                    · {{ $item->folder->place }}
                                @endif
                            </div>
                        </div>
                    </button>
                </div>
            @empty
                <div class="col-span-full rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
                    <flux:heading size="lg">{{ __('No media matched') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('Try clearing filters or syncing Drive again.') }}</flux:text>
                </div>
            @endforelse
        </div>

        @if ($this->mediaItems->hasMorePages())
            <div
                x-data
                x-intersect.margin.200px="$wire.loadMore()"
                class="flex justify-center py-6"
            >
                <flux:text>{{ __('Loading more…') }}</flux:text>
            </div>
        @endif
    </div>

    <flux:modal wire:model="showDetail" class="max-w-5xl">
        @if ($this->selectedMedia)
            @php($media = $this->selectedMedia)
            <div class="space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ $media->name }}</flux:heading>
                        <flux:text class="mt-1">
                            {{ $media->taken_at?->toDayDateTimeString() ?? __('Unknown date') }}
                            @if ($media->folder?->path)
                                · {{ $media->folder->path }}
                            @endif
                        </flux:text>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if ($media->web_view_link)
                            <flux:button href="{{ $media->web_view_link }}" target="_blank" icon="arrow-top-right-on-square">
                                {{ __('Open in Drive') }}
                            </flux:button>
                        @endif
                        <flux:button href="{{ route('media.download', $media) }}" icon="arrow-down-tray">
                            {{ __('Download') }}
                        </flux:button>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="overflow-hidden rounded-xl bg-zinc-100 dark:bg-zinc-900">
                        @if ($media->thumb_path)
                            <img src="{{ route('media.thumbnail', $media) }}" alt="{{ $media->name }}" class="max-h-[28rem] w-full object-contain" />
                        @else
                            <div class="flex aspect-video items-center justify-center text-zinc-500">{{ __('No preview') }}</div>
                        @endif
                    </div>

                    <div class="space-y-4">
                        @if ($media->caption)
                            <div>
                                <flux:heading size="sm">{{ __('Caption') }}</flux:heading>
                                <flux:text class="mt-1 whitespace-pre-wrap">{{ $media->caption }}</flux:text>
                            </div>
                        @endif

                        <div>
                            <flux:heading size="sm">{{ __('Tags') }}</flux:heading>
                            <div class="mt-2 space-y-2">
                                @forelse ($media->mediaTags->sortBy(fn ($t) => $t->tag->category->key.'-'.$t->tag->label_en) as $mediaTag)
                                    <div wire:key="tag-{{ $mediaTag->id }}" class="flex items-center justify-between gap-2 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                                        <div>
                                            <div class="text-sm font-medium">
                                                {{ $mediaTag->tag->category->label_en }}:
                                                {{ $mediaTag->tag->label_en }}
                                            </div>
                                            <div class="text-xs text-zinc-500">
                                                {{ $mediaTag->source }}
                                                · {{ $mediaTag->status }}
                                                @if ($mediaTag->confidence !== null)
                                                    · {{ number_format((float) $mediaTag->confidence * 100) }}%
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex gap-1">
                                            @if ($mediaTag->status !== \App\Models\MediaTag::STATUS_CONFIRMED)
                                                <flux:button size="sm" variant="primary" wire:click="confirmTag({{ $mediaTag->id }})">{{ __('Confirm') }}</flux:button>
                                            @endif
                                            @if ($mediaTag->status !== \App\Models\MediaTag::STATUS_REJECTED)
                                                <flux:button size="sm" variant="danger" wire:click="rejectTag({{ $mediaTag->id }})">{{ __('Reject') }}</flux:button>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <flux:text>{{ __('No tags yet.') }}</flux:text>
                                @endforelse
                            </div>
                        </div>

                        <div class="flex items-end gap-2">
                            <flux:select wire:model="addTagId" :label="__('Add tag')" class="flex-1">
                                <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                                @foreach ($this->allTags as $tag)
                                    <flux:select.option value="{{ $tag->id }}">{{ $tag->category->label_en }}: {{ $tag->label_en }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:button variant="primary" wire:click="addTagToSelectedMedia">{{ __('Add') }}</flux:button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
