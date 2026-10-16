<?php

use App\Http\Controllers\TikTokOAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->prefix('integrations/tiktok')->name('tiktok.')->group(function (): void {
    Route::get('/connect', [TikTokOAuthController::class, 'redirect'])->name('connect');
    Route::get('/callback', [TikTokOAuthController::class, 'callback'])->name('callback');
});
