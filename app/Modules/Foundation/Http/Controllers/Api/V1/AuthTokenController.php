<?php

namespace App\Modules\Foundation\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Foundation\Http\Requests\Api\IssueTokenRequest;
use App\Modules\Foundation\Http\Resources\Api\ApiUserResource;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Sanctum\PersonalAccessToken;
use OpenApi\Attributes as OA;

/**
 * Sign mobile and third-party apps in with API tokens (Laravel Sanctum).
 */
class AuthTokenController extends Controller
{
    #[OA\Post(
        path: '/api/v1/auth/token',
        summary: 'Sign in and get an API token',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email', 'password', 'device_name'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
                new OA\Property(property: 'device_name', type: 'string', example: 'Sara\'s iPhone'),
                new OA\Property(property: 'code', description: 'Authenticator code, for accounts with two-factor authentication', type: 'string'),
                new OA\Property(property: 'recovery_code', description: 'Instead of `code`', type: 'string'),
            ],
        )),
        tags: ['Authentication'],
        responses: [
            new OA\Response(response: 201, description: 'Signed in', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'token', type: 'string'),
                new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
                new OA\Property(property: 'user', ref: '#/components/schemas/User'),
            ])),
            new OA\Response(response: 422, description: 'Wrong credentials, or a two-factor code is needed', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 429, description: 'Too many attempts'),
        ],
    )]
    public function store(IssueTokenRequest $request, TwoFactorAuthenticationProvider $twoFactor): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->string('password')->value(), $user->password)) {
            event(new Failed('sanctum', $user, ['email' => $request->validated('email')]));

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $this->ensureTwoFactorIsPassed($request, $user, $twoFactor);

        $expiresAt = now()->addDays(config()->integer('foundation.api.token_expiration_days'));
        $token = $user->createToken($request->string('device_name')->value(), ['*'], $expiresAt);

        event(new Login('sanctum', $user, false));

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => ApiUserResource::make($user->load('roles')),
        ], 201);
    }

    #[OA\Delete(
        path: '/api/v1/auth/token',
        summary: 'Sign out: revoke the token used for this request',
        security: [['bearer' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(response: 204, description: 'Signed out'),
            new OA\Response(response: 401, description: 'Not signed in'),
        ],
    )]
    public function destroy(Request $request): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }

    /**
     * Accounts with two-factor authentication also need a valid authenticator
     * code or an unused recovery code, as on the sign-in page.
     */
    protected function ensureTwoFactorIsPassed(IssueTokenRequest $request, User $user, TwoFactorAuthenticationProvider $twoFactor): void
    {
        if (! $user->hasEnabledTwoFactorAuthentication()) {
            return;
        }

        $code = $request->validated('code');
        $recoveryCode = $request->validated('recovery_code');

        if (is_string($code) && $twoFactor->verify(decrypt((string) $user->two_factor_secret), $code)) {
            return;
        }

        $matchingRecoveryCode = is_string($recoveryCode)
            ? collect($user->recoveryCodes())->first(fn (string $stored): bool => hash_equals($stored, $recoveryCode))
            : null;

        if ($matchingRecoveryCode !== null) {
            $user->replaceRecoveryCode($recoveryCode);

            return;
        }

        throw ValidationException::withMessages([
            'code' => __($code || $recoveryCode ? 'The provided two factor authentication code was invalid.' : 'A two factor authentication code is required.'),
        ]);
    }
}
