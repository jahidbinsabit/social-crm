<?php

use App\Http\Controllers\Api\InternalEventController;
use Illuminate\Support\Facades\Route;

Route::prefix('internal')->middleware('internal')->group(function () {
    // WhatsApp events
    Route::post('/wa-incoming', [InternalEventController::class, 'waIncoming']);
    Route::post('/wa-qr', [InternalEventController::class, 'waQrReady']);
    Route::post('/wa-event', [InternalEventController::class, 'waEvent']);

    // Telegram events
    Route::post('/tg-incoming', [InternalEventController::class, 'tgIncoming']);
    Route::post('/tg-event', [InternalEventController::class, 'tgEvent']);

    // Engine auto-discovery: list active accounts so engines can reconnect.
    Route::get('/accounts/active', function () {
        return \App\Models\Account::where('status', 'active')
            ->get(['id', 'platform', 'phone_number']);
    });
});
