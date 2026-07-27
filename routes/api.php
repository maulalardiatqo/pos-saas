<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MidtransWebhookController;

Route::post('/midtrans/notification', [MidtransWebhookController::class, 'handleNotification']);