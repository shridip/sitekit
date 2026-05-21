<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deployment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeploymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $teamId = $request->user()->currentTeam->id;

        $deployments = Deployment::where('team_id', $teamId)
            ->with('webApp:id,name,domain')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($deployments);
    }

    public function show(Request $request, Deployment $deployment): JsonResponse
    {
        $this->authorizeTeamAccess($request, $deployment);

        $deployment->load('webApp:id,name,domain');

        return response()->json($deployment);
    }

    protected function authorizeTeamAccess(Request $request, Deployment $deployment): void
    {
        if ($deployment->team_id !== $request->user()->currentTeam->id) {
            abort(403, 'Unauthorized access to this deployment.');
        }
    }
}
