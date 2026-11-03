<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Drive change sync — inactive for now
|--------------------------------------------------------------------------
|
| Enable after AI tagging is tuned. Until then, run manually:
|   php artisan media:sync-changes
|
| use Illuminate\Support\Facades\Schedule;
|
| Schedule::command('media:sync-changes')
|     ->everyTenMinutes()
|     ->withoutOverlapping()
|     ->onOneServer();
|
*/
