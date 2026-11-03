<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-talma-hero text-white antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-md flex-col gap-6">
                <a href="{{ route('home') }}" class="flex flex-col items-center" wire:navigate>
                    <img
                        src="{{ asset('brand/wordmark-logo.png') }}"
                        alt="TALMA — English for Change"
                        class="h-16 w-auto max-w-[16rem] object-contain"
                    >
                </a>

                <div class="rounded-2xl border border-white/15 bg-white/95 text-talma-sand shadow-2xl shadow-talma-sea-deep/30">
                    <div class="px-8 py-8 sm:px-10">{{ $slot }}</div>
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
