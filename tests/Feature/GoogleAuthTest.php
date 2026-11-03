<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function fakeGoogleUser(string $id, string $name, string $email): SocialiteUser
{
    $user = new SocialiteUser;
    $user->id = $id;
    $user->name = $name;
    $user->email = $email;

    return $user;
}

it('redirects to Google for OAuth', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('scopes')->andReturnSelf();
    $provider->shouldReceive('with')->andReturnSelf();
    $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com'));

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('auth.google'))->assertRedirect('https://accounts.google.com');
});

it('rejects Google accounts outside the allowed domain', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn(fakeGoogleUser('99', 'Outsider', 'person@gmail.com'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    expect(User::query()->count())->toBe(0);
});

it('creates and logs in an allowed Google staff user', function () {
    config(['services.google.allowed_email_domain' => 'talmaisrael.com']);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn(fakeGoogleUser('123', 'Talma Staff', 'staff@talmaisrael.com'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('gallery'));

    $user = User::query()->where('email', 'staff@talmaisrael.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->google_id)->toBe('123')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(auth()->id())->toBe($user->id);
});
