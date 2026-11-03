<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-talma-sun text-talma-sand antialiased">
        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div class="bg-talma-hero relative hidden h-full flex-col justify-between p-10 text-white lg:flex">
                <a href="{{ route('home') }}" class="relative z-20" wire:navigate>
                    <img
                        src="{{ asset('brand/wordmark.png') }}"
                        alt="TALMA"
                        class="h-10 w-auto object-contain"
                    >
                </a>

                <div class="relative z-20 mt-auto space-y-3">
                    <p class="text-2xl font-bold tracking-tight">{{ __('English for Change') }}</p>
                    <p class="max-w-sm text-sm text-talma-sky">
                        {{ __('Find, tag, and share TALMA moments from Drive — built for staff.') }}
                    </p>
                </div>
            </div>

            <div class="w-full lg:p-8">
                <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <a href="{{ route('home') }}" class="z-20 flex flex-col items-center gap-2 lg:hidden" wire:navigate>
                        <img
                            src="{{ asset('brand/wordmark-logo.png') }}"
                            alt="TALMA"
                            class="h-14 w-auto object-contain"
                        >
                    </a>
                    {{ $slot }}
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
