<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FirewallRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FirewallRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $teamId = $request->user()->currentTeam->id;

        $rules = FirewallRule::where('team_id', $teamId)
            ->with('server:id,name')
            ->orderBy('order')
            ->paginate($request->integer('per_page', 15));

        return response()->json($rules);
    }

    public function show(Request $request, FirewallRule $firewallRule): JsonResponse
    {
        $this->authorizeTeamAccess($request, $firewallRule);

        $firewallRule->load('server:id,name');

        return response()->json($firewallRule);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'server_id' => 'required|uuid|exists:servers,id',
            'direction' => 'required|string|in:in,out',
            'action' => 'required|string|in:allow,deny',
            'protocol' => 'required|string|in:tcp,udp,any',
            'port' => 'required|string|max:20',
            'from_ip' => 'nullable|string|max:45',
            'description' => 'nullable|string|max:255',
        ]);

        $team = $request->user()->currentTeam;
        $team->servers()->findOrFail($validated['server_id']);

        $firewallRule = FirewallRule::create(array_merge(
            $validated,
            [
                'team_id' => $team->id,
                'is_active' => true,
                'from_ip' => $validated['from_ip'] ?? 'any',
            ]
        ));

        $firewallRule->dispatchApply();

        return response()->json($firewallRule, 201);
    }

    public function update(Request $request, FirewallRule $firewallRule): JsonResponse
    {
        $this->authorizeTeamAccess($request, $firewallRule);

        if ($firewallRule->is_system) {
            return response()->json(['error' => 'System rules cannot be modified.'], 403);
        }

        $validated = $request->validate([
            'description' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $firewallRule->update($validated);

        return response()->json($firewallRule->fresh());
    }

    public function destroy(Request $request, FirewallRule $firewallRule): JsonResponse
    {
        $this->authorizeTeamAccess($request, $firewallRule);

        if ($firewallRule->is_system) {
            return response()->json(['error' => 'System rules cannot be deleted.'], 403);
        }

        $firewallRule->delete();

        return response()->json(['message' => 'Firewall rule deleted.']);
    }

    protected function authorizeTeamAccess(Request $request, FirewallRule $firewallRule): void
    {
        if ($firewallRule->team_id !== $request->user()->currentTeam->id) {
            abort(403, 'Unauthorized access to this firewall rule.');
        }
    }
}
