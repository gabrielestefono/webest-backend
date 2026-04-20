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

        DB::statement("ALTER TABLE change_requests MODIFY status ENUM('requested','quoted','revision','client_approved','payment_pending','paid','pending_development','rejected') NOT NULL DEFAULT 'requested'");
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
            ->where('status', 'revision')
            ->update(['status' => 'requested']);

        DB::table('change_requests')
            ->where('status', 'pending_development')
            ->update(['status' => 'paid']);

        DB::statement("ALTER TABLE change_requests MODIFY status ENUM('requested','quoted','client_approved','payment_pending','paid','rejected') NOT NULL DEFAULT 'requested'");
    }
};
