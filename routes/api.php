<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsAppWebhookController;

// Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);
Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);
