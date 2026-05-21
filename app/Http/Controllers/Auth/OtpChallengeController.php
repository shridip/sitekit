<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use PragmaRX\Google2FA\Google2FA;

class OtpChallengeController extends Controller
{
    public function show(Request $request)
    {
        if (!$request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        return view('auth.otp-challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('login.id');

        if (!$userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if (!$user) {
            return redirect()->route('login');
        }

        $request->validate([
            'code' => 'required|string',
        ]);

        $code = $request->input('code');

        // Try OTP code first
        if ($this->verifyOtpCode($user, $code)) {
            return $this->completeLogin($request, $user);
        }

        // Try recovery code
        if ($this->verifyRecoveryCode($user, $code)) {
            return $this->completeLogin($request, $user);
        }

        throw ValidationException::withMessages([
            'code' => __('The provided two factor authentication code was invalid.'),
        ]);
    }

    protected function verifyOtpCode(User $user, string $code): bool
    {
        $google2fa = new Google2FA();
        $secret = decrypt($user->two_factor_secret);

        return $google2fa->verifyKey($secret, $code);
    }

    protected function verifyRecoveryCode(User $user, string $code): bool
    {
        $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);

        if (!is_array($recoveryCodes)) {
            return false;
        }

        $code = trim($code);

        if (!in_array($code, $recoveryCodes)) {
            return false;
        }

        // Remove used recovery code
        $remainingCodes = array_values(array_diff($recoveryCodes, [$code]));

        $user->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode($remainingCodes)),
        ])->save();

        return true;
    }

    protected function completeLogin(Request $request, User $user): RedirectResponse
    {
        $remember = $request->session()->get('login.remember', false);

        Auth::login($user, $remember);

        $request->session()->forget(['login.id', 'login.remember']);
        $request->session()->regenerate();

        return $this->roleBasedRedirect($user);
    }

    protected function roleBasedRedirect(User $user): RedirectResponse
    {
        $team = $user->currentTeam;

        if (!$team) {
            return redirect('/app');
        }

        // Team owner always gets full access
        if ($user->ownsTeam($team)) {
            return redirect('/app');
        }

        $role = $user->teamRole($team)?->key;

        return match ($role) {
            'admin' => redirect('/app'),
            'developer' => redirect('/app/web-apps'),
            'readonly' => redirect('/app/servers'),
            default => redirect('/app'),
        };
    }
}
