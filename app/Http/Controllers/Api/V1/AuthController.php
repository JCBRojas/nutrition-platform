<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inicio y cierre de sesión del cliente de escritorio con tokens de Sanctum. Mismas reglas que
 * el login web (AuthService): intentos, dominio autorizado, usuario inactivo y 2FA.
 */
class AuthController extends Controller
{
    public function __construct(private AuthService $auth) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = $this->auth->attempt($data['email'], $data['password'], (string) $request->ip());

        if ($this->auth->needsTwoFactor($user)) {
            return response()->json([
                'two_factor_required' => true,
                'ticket' => $this->auth->twoFactorTicket($user),
            ]);
        }

        return $this->issueToken($user, $data['device_name']);
    }

    public function twoFactor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ticket' => ['required', 'string'],
            'code' => ['nullable', 'string', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = $this->auth->completeTwoFactor($data['ticket'], $data['code'] ?? null, $data['recovery_code'] ?? null, (string) $request->ip());

        return $this->issueToken($user, $data['device_name']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        AuditLogger::log('logout', 'Cierre de sesión en el escritorio', $request->user(), ['client' => 'desktop']);

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    private function issueToken(User $user, string $device): JsonResponse
    {
        $expiresAt = now()->addMinutes(config('desktop.token_minutes'));
        $token = $user->createToken($device, ['*'], $expiresAt);
        AuditLogger::log('login', 'Inicio de sesión en el escritorio', $user, ['client' => 'desktop', 'device' => $device], $user->id);

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }
}
