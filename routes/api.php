<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\Api\CronJobController;
use App\Http\Controllers\Api\DatabaseController;
use App\Http\Controllers\Api\DeploymentController;
use App\Http\Controllers\Api\FirewallRuleController;
use App\Http\Controllers\Api\HealthMonitorController;
use App\Http\Controllers\Api\ServerController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SshKeyController;
use App\Http\Controllers\Api\SupervisorProgramController;
use App\Http\Controllers\Api\WebAppController;
use App\Http\Controllers\HeartbeatController;
use App\Http\Controllers\ProvisioningController;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Authenticated API endpoints (Sanctum token required)
Route::middleware('auth:sanctum')->group(function () {
    // Servers
    Route::apiResource('servers', ServerController::class);

    // Web Apps
    Route::post('/web-apps/{webApp}/deploy', [WebAppController::class, 'deploy'])->name('web-apps.deploy');
    Route::apiResource('web-apps', WebAppController::class);

    // Databases
    Route::post('/databases/{database}/backup', [DatabaseController::class, 'backup'])->name('databases.backup');
    Route::apiResource('databases', DatabaseController::class);

    // Cron Jobs
    Route::apiResource('cron-jobs', CronJobController::class);

    // Firewall Rules
    Route::apiResource('firewall-rules', FirewallRuleController::class);

    // SSH Keys
    Route::apiResource('ssh-keys', SshKeyController::class);

    // Health Monitors
    Route::apiResource('health-monitors', HealthMonitorController::class);

    // Supervisor Programs
    Route::apiResource('supervisor-programs', SupervisorProgramController::class);

    // Deployments (read-only)
    Route::get('/deployments', [DeploymentController::class, 'index'])->name('deployments.index');
    Route::get('/deployments/{deployment}', [DeploymentController::class, 'show'])->name('deployments.show');

    // Services (read-only)
    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    Route::get('/services/{service}', [ServiceController::class, 'show'])->name('services.show');

    // AI
    Route::prefix('ai')->group(function () {
        Route::post('/chat', [AiController::class, 'chat'])->name('api.ai.chat');
        Route::post('/explain', [AiController::class, 'explain'])->name('api.ai.explain');
        Route::get('/conversations', [AiController::class, 'conversations'])->name('api.ai.conversations');
        Route::get('/conversations/{id}', [AiController::class, 'conversation'])->name('api.ai.conversation');
        Route::delete('/conversations/{id}', [AiController::class, 'deleteConversation'])->name('api.ai.conversation.delete');
        Route::get('/usage', [AiController::class, 'usage'])->name('api.ai.usage');
        Route::get('/providers', [AiController::class, 'providers'])->name('api.ai.providers');
    });
});

// Provisioning callback (token-based auth in URL)
Route::post('/provision/callback/{token}', [ProvisioningController::class, 'callback'])
    ->name('api.provision.callback');

// Webhook endpoint for Git providers (signature-based auth)
Route::post('/webhooks/deploy/{webApp}', [WebhookController::class, 'deploy'])
    ->name('webhooks.deploy');

// Heartbeat endpoint for cron job monitoring (token-based auth in URL)
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

// Firewall rule confirmation (token-based auth in URL)
Route::post('/firewall/confirm/{token}', [AgentController::class, 'confirmFirewallRule'])
    ->name('api.firewall.confirm');
