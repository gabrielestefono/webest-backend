<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->text('client_description');
            $table->string('decision')->default('pending');
            $table->text('provider_notes')->nullable();
            $table->string('billing_mode')->nullable();
            $table->unsignedInteger('estimated_hours')->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->text('addendum_scope')->nullable();
            $table->decimal('addendum_price', 10, 2)->nullable();
            $table->decimal('counter_price', 10, 2)->nullable();
            $table->text('counter_notes')->nullable();
            $table->unsignedInteger('addendum_extra_days')->nullable();
            $table->string('contract_reference')->nullable();
            $table->string('stripe_payment_link', 1024)->nullable();
            $table->string('stripe_session_id')->nullable();
            $table->string('payment_status')->default('not_applicable');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_adjustments');
    }
};

