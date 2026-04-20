<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('change_requests', 'change_weight')) {
            return;
        }

        Schema::table('change_requests', function (Blueprint $table): void {
            $table->unsignedTinyInteger('change_weight')->nullable()->after('impact_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('change_requests', 'change_weight')) {
            return;
        }

        Schema::table('change_requests', function (Blueprint $table): void {
            $table->dropColumn('change_weight');
        });
    }
};
