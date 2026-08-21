<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AdminLoginRequest;
use App\Http\Resources\Api\V1\Admin\AdminUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Auth admin: login / logout / me. Без саморегистрации.
 */
final class AuthController extends Controller
{
    public function login(AdminLoginRequest $request): JsonResponse
    {
        $email = (string) $request->validated('email');
        $password = (string) $request->validated('password');

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('admin')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new AdminUserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        $token = $user?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    public function me(Request $request): AdminUserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new AdminUserResource($user);
    }
}
