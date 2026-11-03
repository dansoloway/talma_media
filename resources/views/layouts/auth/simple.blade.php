<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-talma-hero text-white antialiased">
        <div class="relative flex min-h-svh flex-col items-center justify-center gap-8 p-6 md:p-10">
            <div class="pointer-events-none absolute inset-0 overflow-hidden">
                <div class="absolute -start-24 top-16 size-72 rounded-full bg-talma-sky/20 blur-3xl"></div>
                <div class="absolute -end-16 bottom-10 size-80 rounded-full bg-talma-coral/20 blur-3xl"></div>
            </div>

            <div class="relative z-10 flex w-full max-w-md flex-col items-center gap-8">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-3" wire:navigate>
                    <img
                        src="{{ asset('brand/wordmark-logo.png') }}"
                        alt="TALMA — English for Change"
                        class="h-16 w-auto max-w-[16rem] object-contain drop-shadow-lg sm:h-20"
                    >
                </a>

                <div class="w-full rounded-2xl border border-white/15 bg-white/95 p-6 text-talma-sand shadow-2xl shadow-talma-sea-deep/30 backdrop-blur-sm sm:p-8">
                    {{ $slot }}
                </div>

                <p class="text-center text-xs font-medium tracking-wide text-talma-sky/90">
                    {{ __('Staff access · @talmaisrael.com') }}
                </p>
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
