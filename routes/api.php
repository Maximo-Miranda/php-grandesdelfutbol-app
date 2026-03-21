<?php

use App\Http\Controllers\Api\BunnyWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/bunny', BunnyWebhookController::class)->name('webhooks.bunny');
