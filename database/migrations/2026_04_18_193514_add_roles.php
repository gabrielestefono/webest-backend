<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        Role::firstOrCreate([
            'name' => 'client',
            'guard_name' => 'web',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Role::query()
            ->whereIn('name', ['admin', 'client'])
            ->where('guard_name', 'web')
            ->delete();
    }
};
