<?php

use App\Http\Controllers\WorkspaceInvitationController;
use App\Http\Controllers\WorkspaceSwitchController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/workspace-invitations/{token}', [WorkspaceInvitationController::class, 'accept'])
    ->name('workspace-invitations.accept');

Route::post('/workspace/switch', [WorkspaceSwitchController::class, 'store'])
    ->middleware('auth')
    ->name('workspace.switch');
