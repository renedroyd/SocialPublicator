<?php

use App\Http\Controllers\Api\BotCommandController;
use App\Http\Controllers\Api\BotExecutionController;
use App\Http\Controllers\Api\BotPublicationController;
use App\Http\Controllers\Api\BotStatusController;
use App\Http\Controllers\Api\Integrations\DubaiShopWebhookController;
use App\Http\Controllers\Api\FacebookGroupController;
use App\Http\Controllers\Api\WhatsAppGroupController;
use App\Http\Middleware\VerifyBotApiToken;
use App\Http\Middleware\VerifyDubaiShopWebhookSignature;
use Illuminate\Support\Facades\Route;

Route::post('/integrations/dubai-shop/v1/events', [DubaiShopWebhookController::class, 'store'])
    ->middleware([
        'throttle:120,1',
        VerifyDubaiShopWebhookSignature::class,
    ])
    ->name('api.integrations.dubai-shop.events');

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

        Route::post('/executions/start', [BotExecutionController::class, 'start'])
            ->name('api.bot.executions.start');

        Route::post('/executions/{execution}/complete', [BotExecutionController::class, 'complete'])
            ->name('api.bot.executions.complete');

        Route::post('/executions/{execution}/fail', [BotExecutionController::class, 'fail'])
            ->name('api.bot.executions.fail');

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

        Route::post('/whatsapp/groups/sync', [
            WhatsAppGroupController::class,
            'sync',
        ])->name('api.bot.whatsapp.groups.sync');

        Route::post('/facebook/groups/sync', [
            FacebookGroupController::class,
            'sync',
        ])->name('api.bot.facebook.groups.sync');

        Route::post('/facebook/grupos/sincronizar', [
            FacebookGroupController::class,
            'sync',
        ])->name('api.bot.facebook.groups.sync.legacy');
    });
