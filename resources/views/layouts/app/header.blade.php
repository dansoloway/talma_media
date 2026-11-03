<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-talma-sun text-talma-sand">
        <flux:header container class="border-b border-talma-haze bg-white">
            <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

            <x-app-logo href="{{ route('gallery') }}" />

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="photo" :href="route('gallery')" :current="request()->routeIs('gallery')" wire:navigate>
                    {{ __('Gallery') }}
                </flux:navbar.item>
                <flux:navbar.item icon="clipboard-document-check" :href="route('review')" :current="request()->routeIs('review')" wire:navigate>
                    {{ __('Review') }}
                </flux:navbar.item>
                <flux:navbar.item icon="cog-6-tooth" :href="route('admin')" :current="request()->routeIs('admin')" wire:navigate>
                    {{ __('Admin') }}
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            <x-desktop-user-menu />
        </flux:header>

        <flux:sidebar collapsible="mobile" sticky class="border-e border-talma-haze bg-white lg:hidden">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('gallery') }}" />
                <flux:sidebar.collapse />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Media')">
                    <flux:sidebar.item icon="photo" :href="route('gallery')" :current="request()->routeIs('gallery')" wire:navigate>
                        {{ __('Gallery') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="clipboard-document-check" :href="route('review')" :current="request()->routeIs('review')" wire:navigate>
                        {{ __('Review') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="cog-6-tooth" :href="route('admin')" :current="request()->routeIs('admin')" wire:navigate>
                        {{ __('Admin') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>
        </flux:sidebar>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
