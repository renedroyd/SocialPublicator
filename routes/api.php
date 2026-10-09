<?php

use App\Http\Controllers\Api\BotPublicationController;
use App\Http\Controllers\Api\BotStatusController;
use App\Http\Controllers\Api\BotCommandController;
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

        Route::get('/commands/claim', [BotCommandController::class, 'claim'])
            ->name('api.bot.commands.claim');

        Route::post('/commands/{command}/complete', [BotCommandController::class, 'complete'])
            ->name('api.bot.commands.complete');

        Route::post('/commands/{command}/fail', [BotCommandController::class, 'fail'])
            ->name('api.bot.commands.fail');

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
