<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user has 2FA enabled
        if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
            return response()->json([
                'two_factor' => true,
                'message' => 'Two-factor authentication required.',
                'otp_token' => encrypt($user->id . '|' . now()->addMinutes(10)->timestamp),
            ]);
        }

        $deviceName = $request->device_name ?? ($request->userAgent() ?? 'api-token');

        return response()->json([
            'two_factor' => false,
            'token' => $user->createToken($deviceName)->plainTextToken,
            'user' => $user->only(['id', 'name', 'email']),
            'role' => $this->getUserRole($user),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $user->ownedTeams()->save(\App\Models\Team::forceCreate([
                'user_id' => $user->id,
                'name' => explode(' ', $user->name, 2)[0] . "'s Team",
                'personal_team' => true,
            ]));

            return $user;
        });

        $deviceName = $request->device_name ?? ($request->userAgent() ?? 'api-token');

        return response()->json([
            'token' => $user->createToken($deviceName)->plainTextToken,
            'user' => $user->only(['id', 'name', 'email']),
        ], 201);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'otp_token' => 'required|string',
            'code' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        // Decrypt and validate the OTP token
        try {
            $decrypted = decrypt($request->otp_token);
            [$userId, $expiresAt] = explode('|', $decrypted);

            if (now()->timestamp > (int) $expiresAt) {
                return response()->json(['error' => 'OTP token has expired. Please login again.'], 401);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid OTP token.'], 401);
        }

        $user = User::find($userId);

        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $code = $request->input('code');

        // Try OTP code
        $valid = false;

        try {
            $google2fa = new Google2FA();
            $secret = decrypt($user->two_factor_secret);
            $valid = $google2fa->verifyKey($secret, $code);
        } catch (\Exception $e) {
            // Fall through to recovery code check
        }

        // Try recovery code if OTP failed
        if (!$valid) {
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);

            if (is_array($recoveryCodes) && in_array(trim($code), $recoveryCodes)) {
                $valid = true;
                $remainingCodes = array_values(array_diff($recoveryCodes, [trim($code)]));
                $user->forceFill([
                    'two_factor_recovery_codes' => encrypt(json_encode($remainingCodes)),
                ])->save();
            }
        }

        if (!$valid) {
            throw ValidationException::withMessages([
                'code' => ['The provided two factor authentication code was invalid.'],
            ]);
        }

        $deviceName = $request->device_name ?? ($request->userAgent() ?? 'api-token');

        return response()->json([
            'token' => $user->createToken($deviceName)->plainTextToken,
            'user' => $user->only(['id', 'name', 'email']),
            'role' => $this->getUserRole($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    protected function getUserRole(User $user): ?string
    {
        $team = $user->currentTeam;

        if (!$team) {
            return null;
        }

        if ($user->ownsTeam($team)) {
            return 'owner';
        }

        return $user->teamRole($team)?->key;
    }
}
