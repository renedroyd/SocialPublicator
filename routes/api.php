<?php

use App\Http\Controllers\Api\FacebookGroupController;
use App\Http\Controllers\Api\BotPublicacionController;
use App\Http\Middleware\VerifyBotApiToken;
use Illuminate\Support\Facades\Route;

Route::middleware(VerifyBotApiToken::class)
    ->prefix('bot')
    ->group(function (): void {
        Route::get('/publications', [
            BotPublicacionController::class,
            'index',
        ])->name('api.bot.publications');

        Route::post('/facebook/groups/sync', [
            FacebookGroupController::class,
            'sync',
        ])->name('api.bot.facebook.groups.sync');
    });
