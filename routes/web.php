<?php

use App\Http\Controllers\MetaOAuthController;
use App\Http\Controllers\SocialAccountFacebookSessionController;
use App\Http\Controllers\SocialAccountWhatsAppQrController;
use App\Http\Controllers\WorkspaceInvitationController;
use App\Http\Controllers\WorkspaceSwitchController;
use App\Http\Middleware\ResolveWorkspaceContext;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/workspace-invitations/{token}', [WorkspaceInvitationController::class, 'accept'])
    ->name('workspace-invitations.accept');

Route::post('/workspace/switch', [WorkspaceSwitchController::class, 'store'])
    ->middleware('auth')
    ->name('workspace.switch');

Route::middleware(['auth', ResolveWorkspaceContext::class])->group(function (): void {
    Route::get('/social-accounts/{socialAccount}/facebook/session', [SocialAccountFacebookSessionController::class, 'show'])
        ->name('social-accounts.facebook.session');

    Route::get('/social-accounts/{socialAccount}/facebook/session/data', [SocialAccountFacebookSessionController::class, 'data'])
        ->name('social-accounts.facebook.session.data');

    Route::get('/social-accounts/{socialAccount}/whatsapp/qr', [SocialAccountWhatsAppQrController::class, 'show'])
        ->name('social-accounts.whatsapp.qr');

    Route::get('/social-accounts/{socialAccount}/whatsapp/qr/data', [SocialAccountWhatsAppQrController::class, 'data'])
        ->name('social-accounts.whatsapp.qr.data');

    Route::get('/social-accounts/{socialAccount}/connect/meta', [MetaOAuthController::class, 'connect'])
        ->name('social-accounts.meta.connect');

    Route::get('/social-accounts/connect/meta/callback', [MetaOAuthController::class, 'callback'])
        ->name('social-accounts.meta.callback');
});
