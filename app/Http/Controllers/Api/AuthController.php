<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\UserResource;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a public customer profile only.
     *
     * Customers are not system users and never receive login credentials or
     * Sanctum tokens. They can only use public unauthenticated endpoints.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $customer = DB::transaction(function () use ($request) {
            return Customer::create([
                'user_id' => null,
                'agent_id' => null,
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'phone' => $request->validated('phone'),
                'national_id' => $request->validated('national_id'),
                'passport_no' => $request->validated('passport_no'),
                'address' => $request->validated('address'),
            ]);
        });

        return response()->json([
            'message' => 'تم إنشاء ملف العميل بنجاح',
            'data' => new CustomerResource($customer),
        ], 201);
    }

    /**
     * Authenticate staff users and issue a Sanctum personal access token.
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

        if ($user->hasRole('customer')) {
            throw ValidationException::withMessages([
                'email' => ['العملاء لا يملكون صلاحية الدخول إلى النظام. يرجى استخدام رابط تتبع الطلب العام.'],
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
     * Return the currently authenticated user, with roles and permissions.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles');

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Update the authenticated user's password.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->validated('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['كلمة المرور الحالية غير صحيحة.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        return response()->json([
            'message' => 'تم تحديث كلمة المرور بنجاح.',
        ]);
    }

    /**
     * Revoke only the token used to make this request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'تم تسجيل الخروج بنجاح',
        ]);
    }

    /**
     * Revoke every token belonging to the user.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'تم تسجيل الخروج من جميع الأجهزة بنجاح',
        ]);
    }
}
