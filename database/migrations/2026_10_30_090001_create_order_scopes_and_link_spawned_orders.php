<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->json('included_items');
            $table->json('excluded_items');
            $table->json('delivery_criteria');
            $table->json('revision_rules');
            $table->json('client_responsibilities');
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('parent_order_id')
                ->nullable()
                ->after('user_id')
                ->constrained('orders')
                ->nullOnDelete();
        });

        Schema::table('order_adjustments', function (Blueprint $table): void {
            $table->foreignId('spawned_order_id')
                ->nullable()
                ->after('requester_id')
                ->constrained('orders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_adjustments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('spawned_order_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_order_id');
        });

        Schema::dropIfExists('order_scopes');
    }
};

