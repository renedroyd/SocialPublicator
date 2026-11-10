<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('social-publicator:sync-dubai-catalog')
    ->hourly()
    ->withoutOverlapping()
    ->when(fn (): bool => filled(config('services.dubai_shop.api_base_url'))
        && filled(config('services.dubai_shop.api_token')));
