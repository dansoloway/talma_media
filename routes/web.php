<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\MediaDownloadController;
use App\Http\Controllers\MediaThumbnailController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('gallery')
        : redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/gallery')->name('dashboard');

    Route::livewire('gallery', 'pages::media.gallery')->name('gallery');
    Route::livewire('review', 'pages::media.review')->name('review');
    Route::livewire('admin', 'pages::admin.taxonomy')->name('admin');

    Route::get('media/{media}/thumbnail', MediaThumbnailController::class)->name('media.thumbnail');
    Route::get('media/{media}/download', MediaDownloadController::class)->name('media.download');
});

require __DIR__.'/settings.php';
