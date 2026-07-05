<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove customer accounts from the system-user surface.
     */
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return;
        }

        $customerRoleIds = DB::table('roles')
            ->where('name', 'customer')
            ->pluck('id');

        if ($customerRoleIds->isEmpty()) {
            return;
        }

        $customerUserIds = DB::table('model_has_roles')
            ->whereIn('role_id', $customerRoleIds)
            ->where('model_type', User::class)
            ->pluck('model_id');

        if ($customerUserIds->isNotEmpty()) {
            if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'user_id')) {
                DB::table('customers')
                    ->whereIn('user_id', $customerUserIds)
                    ->update(['user_id' => null]);
            }

            if (Schema::hasTable('personal_access_tokens')) {
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->whereIn('tokenable_id', $customerUserIds)
                    ->delete();
            }

            DB::table('users')
                ->whereIn('id', $customerUserIds)
                ->update([
                    'is_active' => false,
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        DB::table('model_has_roles')
            ->whereIn('role_id', $customerRoleIds)
            ->delete();

        if (Schema::hasTable('role_has_permissions')) {
            DB::table('role_has_permissions')
                ->whereIn('role_id', $customerRoleIds)
                ->delete();
        }

        DB::table('roles')
            ->whereIn('id', $customerRoleIds)
            ->delete();
    }

    /**
     * This migration is intentionally not reversible without knowing which
     * user accounts were real customers before cleanup.
     */
    public function down(): void
    {
        //
    }
};
