<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        abort_if(
            (bool) Cache::get('registration.disabled', false),
            403,
            'Регистрация временно приостановлена'
        );

        /** @var User $user */
        $user = User::create($request->validated());

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $login = (string) $request->string('login');

        /** @var User|null $user */
        $user = User::query()
            ->where('email', $login)
            ->orWhere('login', $login)
            ->first();

        if ($user === null || ! Hash::check((string) $request->string('password'), $user->password)) {
            return response()->json(['message' => 'Неверные учётные данные'], 401);
        }

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken('api')->plainTextToken,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Вы вышли из системы']);
    }

    public function me(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user->loadCount(['publications', 'comments', 'subscribers']));
    }
}
