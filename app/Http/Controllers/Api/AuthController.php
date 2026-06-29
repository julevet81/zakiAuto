<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new public account.
     *
     * Public self-registration always creates a "customer" account, plus a
     * linked `customers` row so the new user immediately shows up in the
     * Customers module and can have orders/payments attached to them.
     *
     * Staff accounts (super-admin, admin, agent) are never created through
     * this endpoint — they must be created by an authorized admin through
     * the Users management endpoints, where a role is explicitly assigned.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'phone' => $request->validated('phone'),
                'password' => Hash::make($request->validated('password')),
                'is_active' => true,
            ]);

            $user->assignRole('customer');

            Customer::create([
                'user_id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
            ]);

            return $user;
        });

        $token = $user->createToken($request->input('device_name', 'api-token'))->plainTextToken;

        return response()->json([
            'message' => 'تم إنشاء الحساب بنجاح',
            'user' => new UserResource($user->load('roles')),
            'token' => $token,
        ], 201);
    }

    /**
     * Authenticate and issue a new Sanctum personal access token.
     *
     * Note: this intentionally does NOT revoke the user's other tokens, so
     * a user can be logged in from multiple devices simultaneously. Use
     * logoutAll() to revoke every token at once.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['بيانات الدخول غير صحيحة'],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['هذا الحساب معطل، يرجى التواصل مع الإدارة'],
            ]);
        }

        $token = $user->createToken($request->input('device_name', 'api-token'))->plainTextToken;

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح',
            'user' => new UserResource($user->load('roles')),
            'token' => $token,
        ]);
    }

    /**
     * Return the currently authenticated user, with roles & permissions.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles');

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Revoke only the token used to make this request (log out this device).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'تم تسجيل الخروج بنجاح',
        ]);
    }

    /**
     * Revoke every token belonging to the user (log out of all devices).
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'تم تسجيل الخروج من جميع الأجهزة بنجاح',
        ]);
    }
}
