<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE change_requests MODIFY status ENUM('pending','approved','requested','quoted','client_approved','payment_pending','paid','rejected') NOT NULL DEFAULT 'pending'");
        }

        DB::table('change_requests')
            ->where('status', 'pending')
            ->update(['status' => 'requested']);

        DB::table('change_requests')
            ->where('status', 'approved')
            ->update(['status' => 'quoted']);

        if (! Schema::hasColumn('change_requests', 'payment_link')) {
            Schema::table('change_requests', function (Blueprint $table): void {
                $table->string('payment_link')->nullable()->after('impact_price');
            });
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE change_requests MODIFY status ENUM('requested','quoted','client_approved','payment_pending','paid','rejected') NOT NULL DEFAULT 'requested'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE change_requests MODIFY status ENUM('pending','approved','requested','quoted','client_approved','payment_pending','paid','rejected') NOT NULL DEFAULT 'requested'");
        }

        DB::table('change_requests')
            ->where('status', 'requested')
            ->update(['status' => 'pending']);

        DB::table('change_requests')
            ->whereIn('status', ['quoted', 'client_approved', 'payment_pending', 'paid'])
            ->update(['status' => 'approved']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE change_requests MODIFY status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
        }

        if (Schema::hasColumn('change_requests', 'payment_link')) {
            Schema::table('change_requests', function (Blueprint $table): void {
                $table->dropColumn('payment_link');
            });
        }
    }
};
