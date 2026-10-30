<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('billing_type')->default('one_time')->after('base_price');
            $table->string('billing_interval')->nullable()->after('billing_type');
            $table->string('stripe_price_id')->nullable()->after('billing_interval');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'billing_type',
                'billing_interval',
                'stripe_price_id',
            ]);
        });
    }
};

