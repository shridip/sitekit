<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupervisorProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupervisorProgramController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $teamId = $request->user()->currentTeam->id;

        $programs = SupervisorProgram::where('team_id', $teamId)
            ->with('server:id,name')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($programs);
    }

    public function show(Request $request, SupervisorProgram $supervisorProgram): JsonResponse
    {
        $this->authorizeTeamAccess($request, $supervisorProgram);

        $supervisorProgram->load('server:id,name');

        return response()->json($supervisorProgram);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'server_id' => 'required|uuid|exists:servers,id',
            'name' => 'required|string|max:255',
            'command' => 'required|string|max:1000',
            'directory' => 'nullable|string|max:500',
            'user' => 'nullable|string|max:100',
            'numprocs' => 'nullable|integer|min:1|max:100',
            'autostart' => 'nullable|boolean',
            'autorestart' => 'nullable|boolean',
            'startsecs' => 'nullable|integer|min:0|max:600',
            'stopwaitsecs' => 'nullable|integer|min:0|max:600',
        ]);

        $team = $request->user()->currentTeam;
        $team->servers()->findOrFail($validated['server_id']);

        $program = SupervisorProgram::create(array_merge(
            $validated,
            [
                'team_id' => $team->id,
                'status' => SupervisorProgram::STATUS_PENDING,
                'user' => $validated['user'] ?? 'sitekit',
            ]
        ));

        return response()->json($program, 201);
    }

    public function update(Request $request, SupervisorProgram $supervisorProgram): JsonResponse
    {
        $this->authorizeTeamAccess($request, $supervisorProgram);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'command' => 'sometimes|string|max:1000',
            'directory' => 'sometimes|nullable|string|max:500',
            'numprocs' => 'sometimes|integer|min:1|max:100',
            'autostart' => 'sometimes|boolean',
            'autorestart' => 'sometimes|boolean',
        ]);

        $supervisorProgram->update($validated);

        return response()->json($supervisorProgram->fresh());
    }

    public function destroy(Request $request, SupervisorProgram $supervisorProgram): JsonResponse
    {
        $this->authorizeTeamAccess($request, $supervisorProgram);

        $supervisorProgram->delete();

        return response()->json(['message' => 'Supervisor program deleted.']);
    }

    protected function authorizeTeamAccess(Request $request, SupervisorProgram $supervisorProgram): void
    {
        if ($supervisorProgram->team_id !== $request->user()->currentTeam->id) {
            abort(403, 'Unauthorized access to this supervisor program.');
        }
    }
}
