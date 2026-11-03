<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Welcome back')"
            :description="__('Sign in with your @talmaisrael.com Google account')"
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        @if ($errors->any())
            <flux:callout variant="danger">
                {{ $errors->first() }}
            </flux:callout>
        @endif

        <flux:button
            href="{{ route('auth.google') }}"
            variant="primary"
            class="w-full"
            icon="globe-alt"
            data-test="google-login-button"
        >
            {{ __('Continue with Google') }}
        </flux:button>

        <div class="relative text-center text-sm text-talma-sea-deep/50">
            <span>{{ __('or use email') }}</span>
        </div>

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="you@talmaisrael.com"
            />

            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::auth>
