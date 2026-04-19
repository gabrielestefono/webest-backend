<?php

use App\Enums\Permission as AppPermission;
use App\Enums\Role as AppRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (AppPermission::values() as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $adminRole = Role::firstOrCreate([
            'name' => AppRole::Admin->value,
            'guard_name' => 'web',
        ]);

        $clientRole = Role::firstOrCreate([
            'name' => AppRole::Client->value,
            'guard_name' => 'web',
        ]);

        $adminRole->syncPermissions(AppPermission::adminDefaults());
        $clientRole->syncPermissions(AppPermission::clientDefaults());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $adminRole = Role::query()
            ->where('name', AppRole::Admin->value)
            ->where('guard_name', 'web')
            ->first();

        $clientRole = Role::query()
            ->where('name', AppRole::Client->value)
            ->where('guard_name', 'web')
            ->first();

        if ($adminRole !== null) {
            $adminRole->revokePermissionTo(AppPermission::adminDefaults());
        }

        if ($clientRole !== null) {
            $clientRole->revokePermissionTo(AppPermission::clientDefaults());
        }

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', AppPermission::values())
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
