<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    private Google2FA $g2fa;

    public function __construct()
    {
        $this->g2fa = new Google2FA();
    }

    /** POST /user/two-factor-authentication — generate secret, return QR URI */
    public function enable(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $secret = $this->g2fa->generateSecretKey();

        $user->update([
            'two_factor_secret'          => Crypt::encryptString($secret),
            'two_factor_confirmed_at'    => null,
            'two_factor_recovery_codes'  => null,
        ]);

        $qrUri = $this->g2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );

        return response()->json([
            'qr_code_url'  => $qrUri,
            'manual_entry' => $secret,
        ]);
    }

    /** POST /user/confirmed-two-factor-authentication — verify TOTP, activate 2FA */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);

        /** @var \App\Models\User $user */
        $user = $request->user();

        abort_if(!$user->two_factor_secret, 422);

        $secret = Crypt::decryptString($user->two_factor_secret);
        $valid  = $this->g2fa->verifyKey($secret, $request->code);

        if (!$valid) {
            return response()->json(['message' => 'Invalid code.'], 422);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->update([
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($recoveryCodes)),
        ]);

        return response()->json(['recovery_codes' => $recoveryCodes]);
    }

    /** DELETE /user/two-factor-authentication — disable 2FA */
    public function disable(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);

        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($user->two_factor_confirmed_at && $user->two_factor_secret) {
            $secret = Crypt::decryptString($user->two_factor_secret);
            $valid  = $this->g2fa->verifyKey($secret, $request->code);
            if (!$valid) {
                return response()->json(['message' => 'Invalid code.'], 422);
            }
        }

        $user->update([
            'two_factor_secret'         => null,
            'two_factor_confirmed_at'   => null,
            'two_factor_recovery_codes' => null,
        ]);

        return response()->json(['message' => '2FA disabled.']);
    }

    /** GET /user/two-factor-recovery-codes */
    public function recoveryCodes(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        abort_if(!$user->two_factor_confirmed_at, 422);

        $codes = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true);

        return response()->json(['recovery_codes' => $codes]);
    }

    /** POST /user/two-factor-recovery-codes — regenerate */
    public function regenerateCodes(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        abort_if(!$user->two_factor_confirmed_at, 422);

        $codes = $this->generateRecoveryCodes();
        $user->update(['two_factor_recovery_codes' => Crypt::encryptString(json_encode($codes))]);

        return response()->json(['recovery_codes' => $codes]);
    }

    /** GET /user/two-factor-status */
    public function status(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json([
            'enabled'   => (bool) $user->two_factor_confirmed_at,
            'confirmed' => (bool) $user->two_factor_confirmed_at,
        ]);
    }

    private function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))->map(fn() => Str::random(10) . '-' . Str::random(10))->all();
    }
}
