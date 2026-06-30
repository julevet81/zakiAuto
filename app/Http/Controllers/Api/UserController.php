<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ChangeUserRoleRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles')
            ->when($request->filled('role'), fn ($q) => $q->whereHas('roles', fn ($q) => $q->where('name', $request->string('role'))))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search');
                $q->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json(UserResource::collection($users)->response()->getData(true));
    }

    /**
     * Create a staff (or, technically, customer) account from the admin
     * panel. This is the ONLY place admin/agent accounts are created —
     * see StoreUserRequest's class doc. When role=agent, also creates the
     * linked Agent profile in the same transaction.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'phone' => $request->validated('phone'),
                'password' => Hash::make($request->validated('password')),
                'is_active' => $request->input('is_active', true),
                'email_verified_at' => now(),
            ]);

            $user->assignRole($request->validated('role'));

            if ($request->validated('role') === 'agent') {
                Agent::create([
                    'user_id' => $user->id,
                    'name' => $request->input('agent.name', $user->name),
                    'phone' => $request->input('agent.phone', $user->phone),
                    'address' => $request->input('agent.address'),
                ]);
            }

            return $user;
        });

        return response()->json([
            'message' => 'تم إنشاء المستخدم بنجاح',
            'data' => new UserResource($user->load('roles')),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return response()->json([
            'data' => new UserResource($user->load(['roles', 'agent', 'customer'])),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث بيانات المستخدم بنجاح',
            'data' => new UserResource($user->load('roles')),
        ]);
    }

    /**
     * Delete a user account. Blocked from deleting one's own account (see
     * UserPolicy::delete), and from deleting an account that still has
     * an attached Agent/Customer profile with operational history —
     * deactivate (is_active=false) instead of deleting in that case.
     */
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        if ($user->agent?->customers()->exists() || $user->agent?->orders()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف هذا المستخدم لوجود عملاء أو طلبات مرتبطة بملفه كوكيل، يمكنك تعطيل الحساب بدلاً من حذفه',
            ], 422);
        }

        $user->delete();

        return response()->json(['message' => 'تم حذف المستخدم بنجاح']);
    }

    /**
     * Reassign a user's role. Removes every previously held role first
     * (a user should always hold exactly one of the four roles, never a
     * combination) before assigning the new one.
     */
    public function changeRole(ChangeUserRoleRequest $request, User $user): JsonResponse
    {
        $user->syncRoles([$request->validated('role')]);

        return response()->json([
            'message' => 'تم تحديث دور المستخدم بنجاح',
            'data' => new UserResource($user->load('roles')),
        ]);
    }

    /**
     * Toggle a user's active flag — the soft "disable login" switch
     * checked in AuthController::login(), without deleting the account
     * or losing its historical records.
     */
    public function toggleActive(User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user->update(['is_active' => ! $user->is_active]);

        return response()->json([
            'message' => $user->is_active ? 'تم تفعيل الحساب' : 'تم تعطيل الحساب',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Admin-initiated password reset (e.g. the user forgot their password
     * and contacted staff directly, no email-based reset flow exists yet).
     * Revokes all existing tokens so the old password's sessions can't
     * linger after the reset.
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user->update(['password' => Hash::make($request->input('password'))]);
        $user->tokens()->delete();

        return response()->json(['message' => 'تم إعادة تعيين كلمة المرور بنجاح']);
    }
}
