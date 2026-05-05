<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

use App\Http\Resources\UserResource;
use App\Models\ApiToken;
use App\Models\TwoFactorChallenge;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const TWO_FACTOR_TTL_MINUTES = 10;
    private const TWO_FACTOR_MAX_ATTEMPTS = 5;
    private const TWO_FACTOR_RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(private readonly ActivityLogService $activity)
    {
    }

    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'name' => trim((string) $request->input('name', '')),
            'email' => Str::lower(trim((string) $request->input('email', ''))),
        ]);

        $data = $request->validate([
            'name' => ['bail', 'required', 'string', 'min:2', 'max:120'],
            'email' => ['bail', 'required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['bail', 'required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'name.min' => 'El nombre debe tener al menos 2 caracteres.',
            'name.max' => 'El nombre no puede superar 120 caracteres.',
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'Introduce un email valido.',
            'email.unique' => 'Ya existe una cuenta con este email.',
            'password.required' => 'La password es obligatoria.',
            'password.confirmed' => 'Las passwords no coinciden.',
            'password.min' => 'La password debe tener al menos 8 caracteres.',
            'password.letters' => 'La password debe incluir al menos una letra.',
            'password.numbers' => 'La password debe incluir al menos un numero.',
        ]);

        $user = DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'user',
                'is_active' => true,
                'two_factor_enabled' => false,
            ]);

            $this->activity->record($user, 'auth.register', 'user', $user);

            return $user;
        });

        return response()->json($this->tokenPayload($user), 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email', ''))),
        ]);

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
            return response()->json($this->twoFactorResponse($user));
        }

        $this->activity->record($user, 'auth.login', 'user', $user);
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

        if (! $challenge || ! $challenge->isUsable(self::TWO_FACTOR_MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'code' => ['Codigo de verificacion incorrecto o expirado.'],
            ]);
        }

        if (! Hash::check($data['code'], $challenge->code_hash)) {
            $challenge->registerFailedAttempt(self::TWO_FACTOR_MAX_ATTEMPTS);

            throw ValidationException::withMessages([
                'code' => ['Codigo de verificacion incorrecto o expirado.'],
            ]);
        }

        if (! $challenge->user->is_active) {
            return response()->json(['message' => 'Usuario desactivado.'], 403);
        }

        $challenge->forceFill(['consumed_at' => now()])->save();
        $this->activity->record($challenge->user, 'auth.login', 'user', $challenge->user);

        return response()->json($this->tokenPayload($challenge->user));
    }

    public function resendTwoFactor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challengeId' => ['required', 'string'],
        ]);

        $challenge = TwoFactorChallenge::query()
            ->with('user')
            ->find($data['challengeId']);

        if (! $challenge || ! $challenge->isUsable(self::TWO_FACTOR_MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'challengeId' => ['El codigo ha expirado. Vuelve a iniciar sesion.'],
            ]);
        }

        if (! $challenge->user->is_active) {
            return response()->json(['message' => 'Usuario desactivado.'], 403);
        }

        $resendAvailableAt = $challenge->created_at->copy()->addSeconds(self::TWO_FACTOR_RESEND_COOLDOWN_SECONDS);

        if ($resendAvailableAt->isFuture()) {
            return response()->json([
                'message' => 'Espera unos segundos antes de reenviar el codigo.',
                'resendAvailableAt' => $resendAvailableAt->toIso8601String(),
            ], 429);
        }

        return response()->json($this->twoFactorResponse($challenge->user));
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
        $user->twoFactorChallenges()
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->update(['consumed_at' => now()]);

        $code = Str::padLeft((string) random_int(0, 999999), 6, '0');
        $challenge = TwoFactorChallenge::query()->create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TWO_FACTOR_TTL_MINUTES),
        ]);

        if (! app()->environment('testing')) {
            $user->notify(new TwoFactorCodeNotification($code, self::TWO_FACTOR_TTL_MINUTES));
        }

        return [
            'requiresTwoFactor' => true,
            'challengeId' => $challenge->id,
            'expiresAt' => $challenge->expires_at->toIso8601String(),
            'resendAvailableAt' => $challenge->created_at
                ->copy()
                ->addSeconds(self::TWO_FACTOR_RESEND_COOLDOWN_SECONDS)
                ->toIso8601String(),
            'attemptsRemaining' => self::TWO_FACTOR_MAX_ATTEMPTS,
            'user' => new UserResource($user),
            'debugCode' => app()->environment('testing') ? $code : null,
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
