<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SshKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SshKeyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sshKeys = $request->user()->currentTeam
            ->sshKeys()
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($sshKeys);
    }

    public function show(Request $request, SshKey $sshKey): JsonResponse
    {
        $this->authorizeTeamAccess($request, $sshKey);

        $sshKey->load('servers:id,name');

        return response()->json($sshKey);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'public_key' => 'required|string',
        ]);

        if (!SshKey::isValidPublicKey($validated['public_key'])) {
            return response()->json(['error' => 'Invalid SSH public key format.'], 422);
        }

        $sshKey = SshKey::create(array_merge(
            $validated,
            [
                'team_id' => $request->user()->currentTeam->id,
                'user_id' => $request->user()->id,
            ]
        ));

        return response()->json($sshKey, 201);
    }

    public function update(Request $request, SshKey $sshKey): JsonResponse
    {
        $this->authorizeTeamAccess($request, $sshKey);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
        ]);

        $sshKey->update($validated);

        return response()->json($sshKey->fresh());
    }

    public function destroy(Request $request, SshKey $sshKey): JsonResponse
    {
        $this->authorizeTeamAccess($request, $sshKey);

        $sshKey->delete();

        return response()->json(['message' => 'SSH key deleted.']);
    }

    protected function authorizeTeamAccess(Request $request, SshKey $sshKey): void
    {
        if ($sshKey->team_id !== $request->user()->currentTeam->id) {
            abort(403, 'Unauthorized access to this SSH key.');
        }
    }
}
