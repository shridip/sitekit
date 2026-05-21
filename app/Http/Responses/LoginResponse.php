<?php

namespace App\Http\Responses;

use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Http\RedirectResponse;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        $user = $request->user();

        if (!$user) {
            return redirect('/app/login');
        }

        $team = $user->currentTeam;

        if (!$team) {
            return redirect('/app');
        }

        // Team owner always gets full dashboard
        if ($user->ownsTeam($team)) {
            return redirect()->intended('/app');
        }

        $role = $user->teamRole($team)?->key;

        return match ($role) {
            'admin' => redirect()->intended('/app'),
            'developer' => redirect()->intended('/app/web-apps'),
            'readonly' => redirect()->intended('/app/servers'),
            default => redirect()->intended('/app'),
        };
    }
}
