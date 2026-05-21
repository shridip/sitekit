<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\HeartbeatController;
use App\Http\Controllers\ProvisioningController;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth API endpoints (public)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('api.auth.login');
    Route::post('/register', [AuthController::class, 'register'])->name('api.auth.register');
    Route::post('/otp-verify', [AuthController::class, 'verifyOtp'])->name('api.auth.otp-verify');
    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('api.auth.logout');
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/provision/callback/{token}', [ProvisioningController::class, 'callback'])
    ->name('api.provision.callback');

// Webhook endpoint for Git providers
Route::post('/webhooks/deploy/{webApp}', [WebhookController::class, 'deploy'])
    ->name('webhooks.deploy');

// Heartbeat endpoint for cron job monitoring
Route::get('/heartbeat/{token}', [HeartbeatController::class, 'ping'])
    ->name('api.heartbeat.ping');

// Agent API endpoints (authenticated via agent token)
Route::prefix('agent')->middleware('auth.agent')->group(function () {
    Route::post('/heartbeat', [AgentController::class, 'heartbeat'])
        ->name('api.agent.heartbeat');
    Route::get('/config', [AgentController::class, 'config'])
        ->name('api.agent.config');
    Route::get('/jobs', [AgentController::class, 'jobs'])
        ->name('api.agent.jobs');
    Route::post('/jobs/{jobId}/complete', [AgentController::class, 'jobComplete'])
        ->name('api.agent.job.complete');
});

// Firewall rule confirmation (no auth - token in URL)
Route::post('/firewall/confirm/{token}', [AgentController::class, 'confirmFirewallRule'])
    ->name('api.firewall.confirm');

// AI API endpoints (authenticated via Sanctum)
Route::prefix('ai')->middleware('auth:sanctum')->group(function () {
    Route::post('/chat', [AiController::class, 'chat'])->name('api.ai.chat');
    Route::post('/explain', [AiController::class, 'explain'])->name('api.ai.explain');
    Route::get('/conversations', [AiController::class, 'conversations'])->name('api.ai.conversations');
    Route::get('/conversations/{id}', [AiController::class, 'conversation'])->name('api.ai.conversation');
    Route::delete('/conversations/{id}', [AiController::class, 'deleteConversation'])->name('api.ai.conversation.delete');
    Route::get('/usage', [AiController::class, 'usage'])->name('api.ai.usage');
    Route::get('/providers', [AiController::class, 'providers'])->name('api.ai.providers');
});
