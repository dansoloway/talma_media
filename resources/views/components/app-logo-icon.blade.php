@props([
    'variant' => 'mark',
])

@php
    $src = match ($variant) {
        'wordmark' => asset('brand/wordmark.png'),
        'wordmark-logo' => asset('brand/wordmark-logo.png'),
        default => asset('brand/blue-a.png'),
    };
@endphp

<img
    src="{{ $src }}"
    alt="TALMA"
    {{ $attributes->class('object-contain') }}
>
