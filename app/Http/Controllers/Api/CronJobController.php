<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CronJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CronJobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $teamId = $request->user()->currentTeam->id;

        $cronJobs = CronJob::where('team_id', $teamId)
            ->with('server:id,name')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($cronJobs);
    }

    public function show(Request $request, CronJob $cronJob): JsonResponse
    {
        $this->authorizeTeamAccess($request, $cronJob);

        $cronJob->load('server:id,name');

        return response()->json($cronJob);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'server_id' => 'required|uuid|exists:servers,id',
            'name' => 'required|string|max:255',
            'command' => 'required|string|max:1000',
            'schedule' => 'required|string|max:100',
            'user' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $team = $request->user()->currentTeam;
        $team->servers()->findOrFail($validated['server_id']);

        $cronJob = CronJob::create(array_merge(
            $validated,
            [
                'team_id' => $team->id,
                'user' => $validated['user'] ?? 'sitekit',
                'is_active' => $validated['is_active'] ?? true,
            ]
        ));

        $cronJob->syncToServer();

        return response()->json($cronJob, 201);
    }

    public function update(Request $request, CronJob $cronJob): JsonResponse
    {
        $this->authorizeTeamAccess($request, $cronJob);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'command' => 'sometimes|string|max:1000',
            'schedule' => 'sometimes|string|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        $cronJob->update($validated);
        $cronJob->syncToServer();

        return response()->json($cronJob->fresh());
    }

    public function destroy(Request $request, CronJob $cronJob): JsonResponse
    {
        $this->authorizeTeamAccess($request, $cronJob);

        $cronJob->delete();

        return response()->json(['message' => 'Cron job deleted.']);
    }

    protected function authorizeTeamAccess(Request $request, CronJob $cronJob): void
    {
        if ($cronJob->team_id !== $request->user()->currentTeam->id) {
            abort(403, 'Unauthorized access to this cron job.');
        }
    }
}
