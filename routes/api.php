<?php

use App\Http\Controllers\Api\FacebookGrupoController;
use App\Http\Controllers\Api\BotPublicacionController;
use App\Http\Middleware\VerifyBotApiToken;
use Illuminate\Support\Facades\Route;

Route::middleware(VerifyBotApiToken::class)
    ->prefix('bot')
    ->group(function (): void {

        /*
        |--------------------------------------------------------------------------
        | PUBLICACIONES
        |--------------------------------------------------------------------------
        */

        Route::get('/publicaciones', [
            BotPublicacionController::class,
            'index',
        ])->name('api.bot.publicaciones');

        /*
        |--------------------------------------------------------------------------
        | FACEBOOK - SINCRONIZACIÓN DE GRUPOS
        |--------------------------------------------------------------------------
        */

        Route::post('/facebook/grupos/sincronizar', [
            FacebookGrupoController::class,
            'sincronizar',
        ])->name('api.bot.facebook.grupos.sincronizar');
    });

