<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $servers = $request->user()->currentTeam
            ->servers()
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($servers);
    }

    public function show(Request $request, Server $server): JsonResponse
    {
        $this->authorizeTeamAccess($request, $server);

        $server->load(['services', 'webApps', 'databases']);

        return response()->json($server);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'nullable|ip',
            'ssh_port' => 'nullable|integer|min:1|max:65535',
            'provider' => 'nullable|string|in:custom,digitalocean,linode,vultr,hetzner,aws',
        ]);

        $server = $request->user()->currentTeam->servers()->create(array_merge(
            $validated,
            ['status' => Server::STATUS_PENDING]
        ));

        return response()->json($server, 201);
    }

    public function update(Request $request, Server $server): JsonResponse
    {
        $this->authorizeTeamAccess($request, $server);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'ip_address' => 'sometimes|ip',
            'ssh_port' => 'sometimes|integer|min:1|max:65535',
            'alert_load_threshold' => 'sometimes|nullable|numeric|min:0|max:100',
            'alert_memory_threshold' => 'sometimes|nullable|numeric|min:0|max:100',
            'alert_disk_threshold' => 'sometimes|nullable|numeric|min:0|max:100',
            'resource_alerts_enabled' => 'sometimes|boolean',
        ]);

        $server->update($validated);

        return response()->json($server->fresh());
    }

    public function destroy(Request $request, Server $server): JsonResponse
    {
        $this->authorizeTeamAccess($request, $server);

        $server->delete();

        return response()->json(['message' => 'Server deleted.']);
    }

    protected function authorizeTeamAccess(Request $request, Server $server): void
    {
        if ($server->team_id !== $request->user()->currentTeam->id) {
            abort(403, 'Unauthorized access to this server.');
        }
    }
}
