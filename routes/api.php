<?php

use App\Http\Controllers\Api\BotPublicationController;
use App\Http\Controllers\Api\BotStatusController;
use App\Http\Controllers\Api\FacebookGroupController;
use App\Http\Middleware\VerifyBotApiToken;
use Illuminate\Support\Facades\Route;

Route::middleware(VerifyBotApiToken::class)
    ->prefix('bot')
    ->group(function (): void {
        Route::get('/publications', [
            BotPublicationController::class,
            'index',
        ])->name('api.bot.publications');

        Route::get('/publicaciones', [
            BotPublicationController::class,
            'index',
        ])->name('api.bot.publications.legacy');

        Route::post('/status', [
            BotStatusController::class,
            'store',
        ])->name('api.bot.status');

        Route::post('/facebook/groups/sync', [
            FacebookGroupController::class,
            'sync',
        ])->name('api.bot.facebook.groups.sync');

        Route::post('/facebook/grupos/sincronizar', [
            FacebookGroupController::class,
            'sync',
        ])->name('api.bot.facebook.groups.sync.legacy');
    });
