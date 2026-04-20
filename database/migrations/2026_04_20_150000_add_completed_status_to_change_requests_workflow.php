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

        DB::statement("ALTER TABLE change_requests MODIFY status ENUM('requested','awaiting_quote','quoted','revision','client_approved','payment_pending','paid','pending_development','completed','rejected') NOT NULL DEFAULT 'requested'");
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
            ->where('status', 'completed')
            ->update(['status' => 'pending_development']);

        DB::statement("ALTER TABLE change_requests MODIFY status ENUM('requested','awaiting_quote','quoted','revision','client_approved','payment_pending','paid','pending_development','rejected') NOT NULL DEFAULT 'requested'");
    }
};
