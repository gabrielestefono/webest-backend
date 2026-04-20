<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE change_requests MODIFY impact_price DECIMAL(10,2) NULL DEFAULT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('change_requests')
            ->whereNull('impact_price')
            ->update(['impact_price' => 0]);

        DB::statement('ALTER TABLE change_requests MODIFY impact_price DECIMAL(10,2) NOT NULL DEFAULT 0');
    }
};
