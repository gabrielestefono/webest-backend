<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_role')->default('client');
            $table->unsignedInteger('turn_number')->default(1);
            $table->text('current_content');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'turn_number']);
        });

        Schema::create('order_message_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_message_id')->constrained('order_messages')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->text('content');
            $table->foreignId('edited_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['order_message_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_message_versions');
        Schema::dropIfExists('order_messages');
    }
};

