<?php

use App\Models\Place;
use App\Models\SyncState;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagRun;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Admin')] class extends Component {
    public string $categoryKey = '';

    public string $categoryLabelEn = '';

    public string $categoryLabelHe = '';

    public bool $categoryMulti = true;

    public bool $categoryAiEnabled = true;

    public string $tagCategoryId = '';

    public string $tagValue = '';

    public string $tagLabelEn = '';

    public string $tagLabelHe = '';

    public string $placeName = '';

    public string $placeLat = '';

    public string $placeLng = '';

    public string $placeRadius = '500';

    public function saveCategory(): void
    {
        $validated = $this->validate([
            'categoryKey' => ['required', 'alpha_dash', 'max:50'],
            'categoryLabelEn' => ['required', 'string', 'max:100'],
            'categoryLabelHe' => ['required', 'string', 'max:100'],
            'categoryMulti' => ['boolean'],
            'categoryAiEnabled' => ['boolean'],
        ]);

        TagCategory::query()->updateOrCreate(
            ['key' => $validated['categoryKey']],
            [
                'label_en' => $validated['categoryLabelEn'],
                'label_he' => $validated['categoryLabelHe'],
                'multi' => $validated['categoryMulti'],
                'ai_enabled' => $validated['categoryAiEnabled'],
            ],
        );

        $this->reset(['categoryKey', 'categoryLabelEn', 'categoryLabelHe']);
        $this->categoryMulti = true;
        $this->categoryAiEnabled = true;
        unset($this->categories);
        Flux::toast(variant: 'success', text: __('Category saved.'));
    }

    public function saveTag(): void
    {
        $validated = $this->validate([
            'tagCategoryId' => ['required', 'exists:tag_categories,id'],
            'tagValue' => ['required', 'alpha_dash', 'max:80'],
            'tagLabelEn' => ['required', 'string', 'max:100'],
            'tagLabelHe' => ['required', 'string', 'max:100'],
        ]);

        Tag::query()->updateOrCreate(
            [
                'category_id' => (int) $validated['tagCategoryId'],
                'value' => $validated['tagValue'],
            ],
            [
                'label_en' => $validated['tagLabelEn'],
                'label_he' => $validated['tagLabelHe'],
            ],
        );

        $this->reset(['tagCategoryId', 'tagValue', 'tagLabelEn', 'tagLabelHe']);
        unset($this->categories);
        Flux::toast(variant: 'success', text: __('Tag saved.'));
    }

    public function savePlace(): void
    {
        $validated = $this->validate([
            'placeName' => ['required', 'string', 'max:120'],
            'placeLat' => ['required', 'numeric'],
            'placeLng' => ['required', 'numeric'],
            'placeRadius' => ['required', 'integer', 'min:1'],
        ]);

        Place::query()->create([
            'name' => $validated['placeName'],
            'lat' => $validated['placeLat'],
            'lng' => $validated['placeLng'],
            'radius_m' => (int) $validated['placeRadius'],
        ]);

        $this->reset(['placeName', 'placeLat', 'placeLng']);
        $this->placeRadius = '500';
        unset($this->places);
        Flux::toast(variant: 'success', text: __('Place saved.'));
    }

    #[Computed]
    public function categories()
    {
        return TagCategory::query()->with('tags')->orderBy('key')->get();
    }

    #[Computed]
    public function places()
    {
        return Place::query()->orderBy('name')->get();
    }

    #[Computed]
    public function syncState(): ?SyncState
    {
        return SyncState::query()->latest('id')->first();
    }

    #[Computed]
    public function recentErrors()
    {
        return TagRun::query()
            ->with('media')
            ->where('status', TagRun::STATUS_FAILED)
            ->latest()
            ->limit(20)
            ->get();
    }
}; ?>

<div class="space-y-10">
    <div>
        <flux:heading size="xl">{{ __('Admin') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Taxonomy, places, and sync health.') }}</flux:text>
    </div>

    <section class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Add / update category') }}</flux:heading>
            <flux:input wire:model="categoryKey" :label="__('Key')" placeholder="program" />
            <flux:input wire:model="categoryLabelEn" :label="__('Label (EN)')" />
            <flux:input wire:model="categoryLabelHe" :label="__('Label (HE)')" />
            <flux:checkbox wire:model="categoryMulti" :label="__('Allow multiple tags')" />
            <flux:checkbox wire:model="categoryAiEnabled" :label="__('AI enabled')" />
            <flux:button variant="primary" wire:click="saveCategory">{{ __('Save category') }}</flux:button>
        </div>

        <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Add / update tag') }}</flux:heading>
            <flux:select wire:model="tagCategoryId" :label="__('Category')">
                <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                @foreach ($this->categories as $category)
                    <flux:select.option value="{{ $category->id }}">{{ $category->label_en }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="tagValue" :label="__('Value')" placeholder="summer" />
            <flux:input wire:model="tagLabelEn" :label="__('Label (EN)')" />
            <flux:input wire:model="tagLabelHe" :label="__('Label (HE)')" />
            <flux:button variant="primary" wire:click="saveTag">{{ __('Save tag') }}</flux:button>
        </div>
    </section>

    <section class="space-y-3">
        <flux:heading size="lg">{{ __('Current taxonomy') }}</flux:heading>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->categories as $category)
                <div wire:key="cat-{{ $category->id }}" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="font-semibold">{{ $category->label_en }} <span class="text-zinc-500">({{ $category->label_he }})</span></div>
                    <div class="mt-1 text-xs text-zinc-500">{{ $category->key }} · {{ $category->ai_enabled ? 'AI' : 'manual' }}</div>
                    <ul class="mt-3 space-y-1 text-sm">
                        @forelse ($category->tags as $tag)
                            <li>{{ $tag->label_en }} / {{ $tag->label_he }} <span class="text-zinc-400">({{ $tag->value }})</span></li>
                        @empty
                            <li class="text-zinc-500">{{ __('No tags') }}</li>
                        @endforelse
                    </ul>
                </div>
            @endforeach
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Known places') }}</flux:heading>
            <flux:input wire:model="placeName" :label="__('Name')" />
            <div class="grid grid-cols-3 gap-2">
                <flux:input wire:model="placeLat" :label="__('Lat')" />
                <flux:input wire:model="placeLng" :label="__('Lng')" />
                <flux:input wire:model="placeRadius" :label="__('Radius m')" />
            </div>
            <flux:button variant="primary" wire:click="savePlace">{{ __('Add place') }}</flux:button>

            <ul class="space-y-2 text-sm">
                @forelse ($this->places as $place)
                    <li wire:key="place-{{ $place->id }}">{{ $place->name }} ({{ $place->lat }}, {{ $place->lng }}) · {{ $place->radius_m }}m</li>
                @empty
                    <li class="text-zinc-500">{{ __('No places yet.') }}</li>
                @endforelse
            </ul>
        </div>

        <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Sync & job status') }}</flux:heading>
            <flux:text>
                {{ __('Last sync:') }}
                {{ $this->syncState?->last_run_at?->diffForHumans() ?? __('never') }}
            </flux:text>
            <flux:text class="break-all text-xs">
                {{ __('Page token:') }}
                {{ $this->syncState?->page_token ? \Illuminate\Support\Str::limit($this->syncState->page_token, 40) : '—' }}
            </flux:text>

            <flux:heading size="sm">{{ __('Recent tagging errors') }}</flux:heading>
            <ul class="space-y-2 text-sm">
                @forelse ($this->recentErrors as $run)
                    <li wire:key="err-{{ $run->id }}" class="rounded border border-red-200 px-3 py-2 dark:border-red-900">
                        #{{ $run->media_id }} {{ $run->media?->name }} — {{ $run->error }}
                    </li>
                @empty
                    <li class="text-zinc-500">{{ __('No recent errors.') }}</li>
                @endforelse
            </ul>
        </div>
    </section>
</div>
