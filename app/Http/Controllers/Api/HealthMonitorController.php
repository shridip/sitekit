<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HealthMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthMonitorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $teamId = $request->user()->currentTeam->id;

        $monitors = HealthMonitor::where('team_id', $teamId)
            ->with('server:id,name')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($monitors);
    }

    public function show(Request $request, HealthMonitor $healthMonitor): JsonResponse
    {
        $this->authorizeTeamAccess($request, $healthMonitor);

        $healthMonitor->load('server:id,name');

        return response()->json($healthMonitor);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'server_id' => 'nullable|uuid|exists:servers,id',
            'web_app_id' => 'nullable|uuid|exists:web_apps,id',
            'type' => 'required|string|in:http,https,tcp,ping,heartbeat,ssl_expiry',
            'name' => 'required|string|max:255',
            'url' => 'nullable|url|max:500',
            'host' => 'nullable|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'interval_seconds' => 'nullable|integer|min:30|max:3600',
            'timeout_seconds' => 'nullable|integer|min:1|max:60',
            'failure_threshold' => 'nullable|integer|min:1|max:10',
        ]);

        $team = $request->user()->currentTeam;

        if (isset($validated['server_id'])) {
            $team->servers()->findOrFail($validated['server_id']);
        }

        $monitor = HealthMonitor::create(array_merge(
            $validated,
            [
                'team_id' => $team->id,
                'status' => HealthMonitor::STATUS_PENDING,
                'is_active' => true,
            ]
        ));

        return response()->json($monitor, 201);
    }

    public function update(Request $request, HealthMonitor $healthMonitor): JsonResponse
    {
        $this->authorizeTeamAccess($request, $healthMonitor);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'url' => 'sometimes|nullable|url|max:500',
            'interval_seconds' => 'sometimes|integer|min:30|max:3600',
            'timeout_seconds' => 'sometimes|integer|min:1|max:60',
            'failure_threshold' => 'sometimes|integer|min:1|max:10',
            'is_active' => 'sometimes|boolean',
        ]);

        $healthMonitor->update($validated);

        return response()->json($healthMonitor->fresh());
    }

    public function destroy(Request $request, HealthMonitor $healthMonitor): JsonResponse
    {
        $this->authorizeTeamAccess($request, $healthMonitor);

        $healthMonitor->delete();

        return response()->json(['message' => 'Health monitor deleted.']);
    }

    protected function authorizeTeamAccess(Request $request, HealthMonitor $healthMonitor): void
    {
        if ($healthMonitor->team_id !== $request->user()->currentTeam->id) {
            abort(403, 'Unauthorized access to this health monitor.');
        }
    }
}
