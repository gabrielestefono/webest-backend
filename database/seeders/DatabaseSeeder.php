<?php

namespace Database\Seeders;

use App\Enums\Permission as AppPermission;
use App\Enums\Role as AppRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
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

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $user->syncRoles([AppRole::Client->value]);
    }
}
