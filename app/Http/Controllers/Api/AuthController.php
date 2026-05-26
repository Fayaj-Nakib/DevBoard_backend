<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        // If 2FA is enabled, prompt for TOTP before issuing a token
        if ($user->two_factor_confirmed_at && $user->two_factor_secret) {
            Auth::logout();

            return response()->json(['requires_2fa' => true], 200);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function twoFactorChallenge(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'code' => 'required|string',
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->two_factor_confirmed_at || ! $user->two_factor_secret) {
            // 2FA not set up — just issue the token
            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json(['user' => $user, 'token' => $token]);
        }

        $secret = Crypt::decryptString($user->two_factor_secret);
        $g2fa = new Google2FA;
        $valid = $g2fa->verifyKey($secret, $request->code);

        if (! $valid) {
            Auth::logout();

            return response()->json(['message' => 'Invalid 2FA code.'], 422);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function updateMe(Request $request): JsonResponse
    {
        $request->validate([
            'digest_enabled' => 'sometimes|boolean',
            'digest_hour' => 'sometimes|integer|min:0|max:23',
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->update($request->only('digest_enabled', 'digest_hour'));

        return response()->json($user->fresh());
    }
}
