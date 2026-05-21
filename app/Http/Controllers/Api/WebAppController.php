<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\WebApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebAppController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $webApps = $request->user()->currentTeam
            ->webApps()
            ->with('server:id,name')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($webApps);
    }

    public function show(Request $request, WebApp $webApp): JsonResponse
    {
        $this->authorizeTeamAccess($request, $webApp);

        $webApp->load(['server:id,name', 'deployments' => fn ($q) => $q->latest()->limit(5)]);

        return response()->json($webApp);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'server_id' => 'required|uuid|exists:servers,id',
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255',
            'app_type' => 'required|string|in:php,nodejs,static',
            'web_server' => 'nullable|string|in:nginx,nginx_apache',
            'php_version' => 'nullable|string|max:10',
            'node_version' => 'nullable|string|max:10',
            'repository' => 'nullable|string|max:255',
            'branch' => 'nullable|string|max:255',
            'source_provider_id' => 'nullable|uuid|exists:source_providers,id',
            'public_path' => 'nullable|string|max:255',
            'auto_deploy' => 'nullable|boolean',
        ]);

        $team = $request->user()->currentTeam;

        // Verify server belongs to team
        $server = $team->servers()->findOrFail($validated['server_id']);

        $webApp = $team->webApps()->create(array_merge(
            $validated,
            ['status' => WebApp::STATUS_PENDING]
        ));

        return response()->json($webApp, 201);
    }

    public function update(Request $request, WebApp $webApp): JsonResponse
    {
        $this->authorizeTeamAccess($request, $webApp);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'domain' => 'sometimes|string|max:255',
            'php_version' => 'sometimes|nullable|string|max:10',
            'node_version' => 'sometimes|nullable|string|max:10',
            'repository' => 'sometimes|nullable|string|max:255',
            'branch' => 'sometimes|nullable|string|max:255',
            'public_path' => 'sometimes|nullable|string|max:255',
            'deploy_script' => 'sometimes|nullable|string',
            'auto_deploy' => 'sometimes|boolean',
            'environment_variables' => 'sometimes|nullable|array',
        ]);

        $webApp->update($validated);

        return response()->json($webApp->fresh());
    }

    public function destroy(Request $request, WebApp $webApp): JsonResponse
    {
        $this->authorizeTeamAccess($request, $webApp);

        $webApp->delete();

        return response()->json(['message' => 'Web app deleted.']);
    }

    public function deploy(Request $request, WebApp $webApp): JsonResponse
    {
        $this->authorizeTeamAccess($request, $webApp);

        $deployment = Deployment::create([
            'web_app_id' => $webApp->id,
            'team_id' => $webApp->team_id,
            'user_id' => $request->user()->id,
            'source_provider_id' => $webApp->source_provider_id,
            'repository' => $webApp->repository,
            'branch' => $webApp->branch,
            'trigger' => Deployment::TRIGGER_MANUAL,
        ]);

        $deployment->dispatchJob();

        return response()->json([
            'message' => 'Deployment started.',
            'deployment' => $deployment,
        ], 201);
    }

    protected function authorizeTeamAccess(Request $request, WebApp $webApp): void
    {
        if ($webApp->team_id !== $request->user()->currentTeam->id) {
            abort(403, 'Unauthorized access to this web app.');
        }
    }
}
