@props([
    'sidebar' => false,
])

@if ($sidebar)
    <a href="{{ $attributes->get('href', route('gallery')) }}" {{ $attributes->except('href')->merge(['class' => 'flex items-center gap-3', 'wire:navigate' => true]) }}>
        <span class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-talma-sea-deep shadow-sm ring-1 ring-talma-sea/20">
            <x-app-logo-icon class="size-9" />
        </span>
        <span class="min-w-0">
            <span class="block truncate text-sm font-bold tracking-wide text-talma-sea">TALMA</span>
            <span class="block truncate text-[11px] font-medium text-talma-sea-deep/60">{{ __('Media') }}</span>
        </span>
    </a>
@else
    <a href="{{ $attributes->get('href', route('home')) }}" {{ $attributes->except('href')->merge(['class' => 'inline-flex items-center gap-2', 'wire:navigate' => true]) }}>
        <x-app-logo-icon class="size-8 rounded-md" />
        <span class="text-sm font-bold tracking-wide text-talma-sea">TALMA</span>
    </a>
@endif
