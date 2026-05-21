<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Database;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatabaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $databases = $request->user()->currentTeam
            ->databases()
            ->with('server:id,name')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($databases);
    }

    public function show(Request $request, Database $database): JsonResponse
    {
        $this->authorizeTeamAccess($request, $database);

        $database->load(['server:id,name', 'users', 'backups' => fn ($q) => $q->latest()->limit(5)]);

        return response()->json($database);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'server_id' => 'required|uuid|exists:servers,id',
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:mysql,mariadb,postgresql',
            'backup_enabled' => 'nullable|boolean',
            'backup_schedule' => 'nullable|string|max:50',
            'backup_retention_days' => 'nullable|integer|min:1|max:365',
        ]);

        $team = $request->user()->currentTeam;
        $team->servers()->findOrFail($validated['server_id']);

        $database = $team->databases()->create(array_merge(
            $validated,
            ['status' => Database::STATUS_PENDING]
        ));

        return response()->json($database, 201);
    }

    public function update(Request $request, Database $database): JsonResponse
    {
        $this->authorizeTeamAccess($request, $database);

        $validated = $request->validate([
            'backup_enabled' => 'sometimes|boolean',
            'backup_schedule' => 'sometimes|nullable|string|max:50',
            'backup_retention_days' => 'sometimes|nullable|integer|min:1|max:365',
        ]);

        $database->update($validated);

        return response()->json($database->fresh());
    }

    public function destroy(Request $request, Database $database): JsonResponse
    {
        $this->authorizeTeamAccess($request, $database);

        $database->delete();

        return response()->json(['message' => 'Database deleted.']);
    }

    public function backup(Request $request, Database $database): JsonResponse
    {
        $this->authorizeTeamAccess($request, $database);

        $backup = $database->createBackup();

        return response()->json([
            'message' => 'Backup initiated.',
            'backup' => $backup,
        ], 201);
    }

    protected function authorizeTeamAccess(Request $request, Database $database): void
    {
        if ($database->team_id !== $request->user()->currentTeam->id) {
            abort(403, 'Unauthorized access to this database.');
        }
    }
}
