<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $services = $request->user()->currentTeam
            ->services()
            ->with('server:id,name')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($services);
    }

    public function show(Request $request, Service $service): JsonResponse
    {
        $this->authorizeTeamAccess($request, $service);

        $service->load('server:id,name');

        return response()->json($service);
    }

    protected function authorizeTeamAccess(Request $request, Service $service): void
    {
        $teamServerIds = $request->user()->currentTeam->servers()->pluck('id');

        if (!$teamServerIds->contains($service->server_id)) {
            abort(403, 'Unauthorized access to this service.');
        }
    }
}
