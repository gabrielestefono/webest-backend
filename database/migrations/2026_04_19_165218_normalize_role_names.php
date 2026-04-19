<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('roles')
            ->where('guard_name', 'web')
            ->where('name', 'Admin')
            ->update(['name' => 'admin']);

        DB::table('roles')
            ->where('guard_name', 'web')
            ->where('name', 'Customer')
            ->update(['name' => 'client']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')
            ->where('guard_name', 'web')
            ->where('name', 'admin')
            ->update(['name' => 'Admin']);

        DB::table('roles')
            ->where('guard_name', 'web')
            ->where('name', 'client')
            ->update(['name' => 'Customer']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
