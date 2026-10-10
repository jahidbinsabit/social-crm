<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AccountsController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\AssignController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Staff\StaffChannelController;
use App\Http\Controllers\Staff\StaffDashboardController;
use App\Http\Controllers\Staff\StaffSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Chat attachments live on the private disk; any signed-in user of the CRM may
// stream them, so this sits outside the admin/staff prefixes.
Route::get('/media/{path}', [MediaController::class, 'show'])
    ->where('path', '.*')
    ->middleware(['auth', 'active'])
    ->name('media.show');

Route::prefix('admin')->middleware(['admin', 'active'])->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/accounts', [AccountsController::class, 'index'])->name('admin.accounts');
    Route::get('/accounts/{account}/chats', [AccountsController::class, 'getAccountChats']);
    Route::get('/accounts/{account}/chats/{client}/messages', [AccountsController::class, 'getClientMessages']);
    Route::post('/accounts/{account}/chats/{client}/send', [AccountsController::class, 'sendMessage']);
    Route::get('/campaigns', [CampaignController::class, 'index'])->name('admin.campaigns');
    Route::post('/campaigns', [CampaignController::class, 'store'])->name('admin.campaigns.store');
    Route::get('/campaigns/{campaign}/details', [CampaignController::class, 'details'])->name('admin.campaigns.details');
    Route::get('/assign', [AssignController::class, 'index'])->name('admin.assign');
    Route::post('/assign/assign', [AssignController::class, 'assign'])->name('admin.assign.assign');
    Route::post('/assign/deassign', [AssignController::class, 'deassign'])->name('admin.assign.deassign');
    Route::post('/assign/auto', [AssignController::class, 'autoAssign'])->name('admin.assign.auto');
    Route::get('/assign/staff/{staff}/accounts', [AssignController::class, 'staffAccounts'])->name('admin.assign.staff-accounts');
    Route::get('/sessions', [SessionController::class, 'index'])->name('admin.sessions');
    Route::post('/sessions/wa/start', [SessionController::class, 'waStartSession'])->name('admin.sessions.wa.start');
    Route::post('/sessions/wa/pair', [SessionController::class, 'waStartPairing'])->name('admin.sessions.wa.pair');
    Route::get('/sessions/wa/qr/{accountId}', [SessionController::class, 'waGetQr'])->name('admin.sessions.wa.qr');
    Route::get('/sessions/wa/pairing-status', [SessionController::class, 'waCheckPairing'])->name('admin.sessions.wa.pairing-status');
    Route::post('/sessions/tg/otp', [SessionController::class, 'tgRequestOtp'])->name('admin.sessions.tg.otp');
    Route::post('/sessions/tg/verify', [SessionController::class, 'tgVerifyOtp'])->name('admin.sessions.tg.verify');
    Route::post('/sessions/{account}/disconnect', [SessionController::class, 'disconnectAccount'])->name('admin.sessions.disconnect');
    Route::delete('/sessions/{account}', [SessionController::class, 'deleteAccount'])->name('admin.sessions.delete');
    Route::post('/sessions/sync-status', [SessionController::class, 'syncStatus'])->name('admin.sessions.sync');
    Route::get('/settings', [SettingsController::class, 'index'])->name('admin.settings');
    Route::post('/settings', [SettingsController::class, 'update'])->name('admin.settings.update');
    Route::get('/staff', [StaffController::class, 'index'])->name('admin.staff');
    Route::post('/staff', [StaffController::class, 'store'])->name('admin.staff.store');
    Route::patch('/staff/{user}/toggle-status', [StaffController::class, 'toggleStatus'])->name('admin.staff.toggle');
    Route::delete('/staff/{user}', [StaffController::class, 'destroy'])->name('admin.staff.destroy');
    Route::get('/horizon', fn() => redirect('/horizon/dashboard'))->name('admin.horizon');
});

Route::prefix('staff')->middleware(['staff', 'active'])->group(function () {
    Route::get('/channel', [StaffChannelController::class, 'index'])->name('staff.channel');
    Route::post('/set-channel', [StaffChannelController::class, 'setChannel'])->name('staff.setChannel');
    Route::get('/dashboard', [StaffDashboardController::class, 'index'])->name('staff.dashboard');
    Route::get('/accounts/{account}/chats', [StaffDashboardController::class, 'getAccountChats']);
    Route::get('/accounts/{account}/chats/{client}/messages', [StaffDashboardController::class, 'getClientMessages']);
    Route::post('/accounts/{account}/chats/{client}/send', [StaffDashboardController::class, 'sendMessage']);
    Route::get('/change-password', [StaffSettingsController::class, 'showChangePasswordForm'])->name('staff.changePassword');
    Route::post('/change-password', [StaffSettingsController::class, 'changePassword'])->name('staff.changePassword.post');
});
