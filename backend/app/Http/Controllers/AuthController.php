<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\ApiToken;
use App\Models\TwoFactorChallenge;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity)
    {
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'user',
            'two_factor_enabled' => false,
        ]);

        $this->activity->record($user, 'auth.register', 'user', $user);

        return response()->json([
            'message' => 'Usuario creado. Verifica el segundo factor para entrar.',
            ...$this->twoFactorResponse($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales incorrectas.'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Usuario desactivado.'], 403);
        }

        if ($user->two_factor_enabled) {
            $this->activity->record($user, 'auth.2fa_requested', 'user', $user);

            return response()->json([
                'message' => 'Segundo factor requerido.',
                ...$this->twoFactorResponse($user),
            ]);
        }

        return response()->json($this->tokenPayload($user));
    }

    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challengeId' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
        ]);

        $challenge = TwoFactorChallenge::query()
            ->with('user')
            ->find($data['challengeId']);

        if (! $challenge || ! $challenge->isUsable() || ! Hash::check($data['code'], $challenge->code_hash)) {
            throw ValidationException::withMessages([
                'code' => ['Codigo de verificacion incorrecto o expirado.'],
            ]);
        }

        $challenge->forceFill(['consumed_at' => now()])->save();
        $this->activity->record($challenge->user, 'auth.login', 'user', $challenge->user);

        return response()->json($this->tokenPayload($challenge->user));
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('api_token');

        if ($token instanceof ApiToken) {
            $token->delete();
        }

        $this->activity->record($request->user(), 'auth.logout', 'user', $request->user());

        return response()->json(['message' => 'Sesion cerrada.']);
    }

    private function twoFactorResponse(User $user): array
    {
        $code = (string) random_int(100000, 999999);
        $challenge = TwoFactorChallenge::query()->create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        return [
            'requiresTwoFactor' => true,
            'challengeId' => $challenge->id,
            'expiresAt' => $challenge->expires_at->toIso8601String(),
            'debugCode' => app()->environment(['local', 'testing']) ? $code : null,
        ];
    }

    private function tokenPayload(User $user): array
    {
        $plainToken = bin2hex(random_bytes(32));
        $expiresAt = now()->addDays(7);

        $user->apiTokens()->create([
            'name' => 'web',
            'token_hash' => hash('sha256', $plainToken),
            'abilities' => ['*'],
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $plainToken,
            'tokenType' => 'Bearer',
            'expiresAt' => $expiresAt->toIso8601String(),
            'user' => new UserResource($user),
        ];
    }
}
