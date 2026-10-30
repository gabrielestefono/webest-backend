<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('figma_url')->nullable()->after('description');
            $table->decimal('final_price', 10, 2)->nullable()->after('status');
            $table->unsignedInteger('estimated_days')->nullable()->after('final_price');
            $table->string('contract_url')->nullable()->after('estimated_days');
            $table->timestamp('contract_signed_at')->nullable()->after('contract_url');
            $table->string('stripe_checkout_url', 1024)->nullable()->after('contract_signed_at');
            $table->string('stripe_session_id')->nullable()->after('stripe_checkout_url');
            $table->string('staging_url')->nullable()->after('stripe_session_id');
            $table->string('repository_url')->nullable()->after('staging_url');
            $table->timestamp('deadline')->nullable()->after('repository_url');
            $table->timestamp('client_completed_at')->nullable()->after('deadline');
            $table->timestamp('provider_completed_at')->nullable()->after('client_completed_at');
            $table->timestamp('warranty_until')->nullable()->after('provider_completed_at');
        });

        Schema::create('order_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['order_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_products');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'figma_url',
                'final_price',
                'estimated_days',
                'contract_url',
                'contract_signed_at',
                'stripe_checkout_url',
                'stripe_session_id',
                'staging_url',
                'repository_url',
                'deadline',
                'client_completed_at',
                'provider_completed_at',
                'warranty_until',
            ]);
        });
    }
};

