<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirectResponse|RedirectResponse
    {
        $domain = (string) config('services.google.allowed_email_domain', 'talmaisrael.com');

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->with(['hd' => $domain, 'prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        $allowedDomain = Str::lower((string) config('services.google.allowed_email_domain', 'talmaisrael.com'));

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => __('Google sign-in expired. Please try again.')]);
        } catch (Throwable) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => __('Google sign-in failed. Please try again.')]);
        }

        $email = Str::lower((string) $googleUser->getEmail());
        $domain = Str::after($email, '@');

        if ($email === '' || $domain !== $allowedDomain) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => __('Only @:domain accounts can sign in.', ['domain' => $allowedDomain]),
                ]);
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $user = new User([
                'google_id' => $googleUser->getId(),
                'name' => $googleUser->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Str::password(32),
            ]);
            $user->email_verified_at = now();
            $user->save();
        } else {
            $user->forceFill([
                'google_id' => $googleUser->getId(),
                'name' => $googleUser->getName() ?: $user->name,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('gallery', absolute: false));
    }
}
